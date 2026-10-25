source "${PWD}/bin/utils/print.sh"
source "${PWD}/bin/utils/env.sh"

DEBUG=$(env DEBUG '0')
DRY_RUN=$(env DRY_RUN '0')

function run() {
    local RUN_COMMAND=$1;
    if [ "${DEBUG}" -gt 0 ]; then
        grey "${RUN_COMMAND}"
    fi
    if [ "${DRY_RUN}" -eq "0" ]; then
        ( eval "${RUN_COMMAND}" )
    fi
    # shellcheck disable=SC2181
    if [ $? -ne 0 ]; then
      red FAIL;
      exit 1;
    fi
}
