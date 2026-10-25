#!/usr/bin/bash

# USAGE
# bash bin/scripts/15-checker-install.sh
#
# Registers this package in the sandbox the same way module projects register a module:
# a Composer `path` repository that Composer symlinks into vendor/. The repository points at
# build/_ rather than at the project root, because the root contains build/ itself and a
# symlink to it would be a directory containing itself. build/_ exposes composer.json and src/
# through relative symlinks (edits are picked up live) and a COPY of bin/: Composer refuses to
# create a vendor/bin proxy for a binary whose real path lies outside the package directory.

source "${PWD}/bin/utils/bootstrap.sh"

ROOT_DIR="${PWD}"
MAGENTO_DIR="${ROOT_DIR}/build/magento"
LINK_DIR="${ROOT_DIR}/build/_"

VERBOSE=$(env VERBOSE '0')

if [ "${VERBOSE}" == '1' ]; then
  QUIET=""
else
  QUIET="--quiet"
fi

run "rm -rf ${LINK_DIR}"
run "mkdir -p ${LINK_DIR}/bin"
run "ln -sfn ../../composer.json ${LINK_DIR}/composer.json"
run "ln -sfn ../../src ${LINK_DIR}/src"
run "cp ${ROOT_DIR}/bin/i18n ${LINK_DIR}/bin/i18n"
run "cd ${MAGENTO_DIR} && XDEBUG_MODE=off composer config repositories.magento2-translations-checker path ${LINK_DIR}"
run "cd ${MAGENTO_DIR} && XDEBUG_MODE=off composer require --dev sergiynezbritskiy/magento2-translations-checker:@dev --no-update ${QUIET}"
