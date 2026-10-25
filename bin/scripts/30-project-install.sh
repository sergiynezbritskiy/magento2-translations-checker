#!/usr/bin/bash

# USAGE
# bash bin/scripts/30-project-install.sh
#
# Resolves and installs the sandbox's dependencies (Magento, this checker, the dummy module).
# `composer update` rather than `install`: the project template ships a lock file that does not
# know about the packages added by the previous scripts.

source "${PWD}/bin/utils/bootstrap.sh"

ROOT_DIR="${PWD}"
MAGENTO_DIR="${ROOT_DIR}/build/magento"

VERBOSE=$(env VERBOSE '0')

if [ "${VERBOSE}" == '1' ]; then
  QUIET=""
else
  QUIET="--quiet"
fi

run "cd ${MAGENTO_DIR} && XDEBUG_MODE=off composer update ${QUIET}"
