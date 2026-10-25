#!/usr/bin/env bash

function _get_env_description() {
    local key="$1"
    local file="$2"

    [ -f "${file}" ] || return

    local -a pending=()
    while IFS= read -r line || [[ -n "${line}" ]]; do
        if [[ "${line}" == '#: '* ]]; then
            pending+=("${line:3}")
        elif [[ "${line}" == "${key}="* ]]; then
            [[ ${#pending[@]} -gt 0 ]] && printf '%s\n' "${pending[@]}"
            return
        else
            pending=()
        fi
    done < "${file}"
}

function _save_env() {
    local key="$1"
    local value="$2"
    local file="$3"
    local description="$4"

    mkdir -p "$(dirname "${file}")"
    touch "${file}"

    if grep -qE "^${key}=" "${file}" 2>/dev/null; then
        local tmp
        tmp=$(mktemp)
        local -a pending=()
        while IFS= read -r line || [[ -n "${line}" ]]; do
            if [[ "${line}" == '#: '* ]]; then
                pending+=("${line}")
            elif [[ "${line}" == "${key}="* ]]; then
                if [[ -n "${description}" ]]; then
                    while IFS= read -r desc_line; do
                        echo "#: ${desc_line}"
                    done <<< "${description}"
                fi
                echo "${key}=${value}"
                pending=()
            else
                [[ ${#pending[@]} -gt 0 ]] && printf '%s\n' "${pending[@]}"
                pending=()
                echo "${line}"
            fi
        done < "${file}" > "${tmp}"
        mv "${tmp}" "${file}"
    else
        {
            if [[ -n "${description}" ]]; then
                while IFS= read -r desc_line; do
                    echo "#: ${desc_line}"
                done <<< "${description}"
            fi
            echo "${key}=${value}"
        } >> "${file}"
    fi
}

# env <KEY> [DEFAULT]
#
# Returns the value of an environment variable, with a layered lookup:
#   1. Project .env file (${PWD}/.env)
#   2. Global .env file (~/.environment/.env)
#   3. DEFAULT argument, if provided
#   4. Interactive prompt (shows description from .env / .env.sample if available)
#      Prompts to save the entered value back to project or global .env.
#
# In non-interactive mode (NON_INTERACTIVE=1) step 4 prints an error and exits 1.
#
# Examples:
#   # Required variable — prompts if missing
#   DB_PASSWORD=$(env MARIADB_PASSWORD)
#
#   # Optional variable with a default
#   EDITION=$(env MAGENTO2_EDITION 'community')
#
#   # Non-interactive — fails fast if not set
#   NON_INTERACTIVE=1 bash ./bin/scripts/30-project-install.sh
function env() {
    local key="$1"
    local default="${2-}"
    local value
    local project_env="${PWD}/.env"
    local global_env="${HOME}/.environment/.env"

    value=$(
        set -a
        # shellcheck disable=SC1090
        [ -f "${global_env}" ] && source "${global_env}" 2>/dev/null || true
        if [ -f "${project_env}" ]; then
            tmp=$(mktemp)
            grep -vE '^[A-Za-z_][A-Za-z0-9_]*=\s*$' "${project_env}" > "${tmp}" 2>/dev/null || true
            # shellcheck disable=SC1090
            source "${tmp}" 2>/dev/null || true
            rm -f "${tmp}"
        fi
        set +a
        printf '%s' "${!key}"
    )

    if [[ -z "${value}" ]] && [[ -n "${default}" ]]; then
        echo "${default}"
        return
    fi

    if [[ -z "${value}" ]]; then
        if [[ "${NON_INTERACTIVE:-0}" == "1" ]]; then
            echo "[ERROR] Required environment variable ${key} is not set" >&2
            exit 1
        fi

        local description
        description=$(_get_env_description "${key}" "${project_env}")
        [[ -z "${description}" ]] && description=$(_get_env_description "${key}" "${PWD}/.env.sample")
        [[ -n "${description}" ]] && echo "${description}" > /dev/tty

        printf 'Enter value for %s: ' "${key}" > /dev/tty
        read -r value < /dev/tty

        printf 'Save %s? [ y - project / g - global / n - no ]: ' "${key}" > /dev/tty
        read -r answer < /dev/tty
        case "${answer}" in
            y) _save_env "${key}" "${value}" "${project_env}" "${description}" ;;
            g) _save_env "${key}" "${value}" "${global_env}" "${description}" ;;
        esac
    fi

    echo "${value}"
}
