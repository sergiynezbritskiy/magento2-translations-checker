#!/usr/bin/bash

# USAGE
# bash bin/scripts/20-dummy-module-install.sh
#
# Creates the dummy module build/module-dummy (a copy of tests/fixtures/module, so it can be
# edited or broken freely) and registers it in the sandbox as a `path` repository.

source "${PWD}/bin/utils/bootstrap.sh"

ROOT_DIR="${PWD}"
MAGENTO_DIR="${ROOT_DIR}/build/magento"
FIXTURE_DIR="${ROOT_DIR}/tests/fixtures/module"
MODULE_DIR="${ROOT_DIR}/build/module-dummy"

VERBOSE=$(env VERBOSE '0')

if [ "${VERBOSE}" == '1' ]; then
  QUIET=""
else
  QUIET="--quiet"
fi

run "rm -rf ${MODULE_DIR}"
run "cp -R ${FIXTURE_DIR} ${MODULE_DIR}"
run "cd ${MAGENTO_DIR} && XDEBUG_MODE=off composer config repositories.module-dummy path ${MODULE_DIR}"
run "cd ${MAGENTO_DIR} && XDEBUG_MODE=off composer require sergiynezbritskiy/module-dummy:@dev --no-update ${QUIET}"
