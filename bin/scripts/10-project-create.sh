#!/usr/bin/bash

# USAGE
# bash bin/scripts/10-project-create.sh {repo.magento.com username} {repo.magento.com password} {edition} {version}
# e.g. bash bin/scripts/10-project-create.sh "" "" community 2.4.9
#
# Creates a Magento 2 project (code base only, nothing is installed into a database) in build/magento.

source "${PWD}/bin/utils/bootstrap.sh"

ROOT_DIR="${PWD}"
MAGENTO_DIR="${ROOT_DIR}/build/magento"

VERBOSE=$(env VERBOSE '0')

USERNAME=$1
[ -z "${USERNAME}" ] && USERNAME=$(env REPO_MAGENTO_COM_USERNAME)

PASSWORD=$2
[ -z "${PASSWORD}" ] && PASSWORD=$(env REPO_MAGENTO_COM_PASSWORD)

EDITION=$3
[ -z "${EDITION}" ] && EDITION=$(env MAGENTO2_EDITION 'community')

VERSION=$4
[ -z "${VERSION}" ] && VERSION=$(env MAGENTO2_VERSION 'latest')

if [ "${VERSION}" == 'latest' ]; then
  VERSION=""
else
  VERSION=":${VERSION}"
fi

if [ "${VERBOSE}" == '1' ]; then
  QUIET=""
else
  QUIET="--quiet"
fi

# composer create-project refuses a non-empty target directory
run "rm -rf ${MAGENTO_DIR}"
run "XDEBUG_MODE=off composer create-project --repository-url=https://${USERNAME}:${PASSWORD}@repo.magento.com/ magento/project-${EDITION}-edition${VERSION} --no-install ${MAGENTO_DIR} ${QUIET}"
run "cd ${MAGENTO_DIR} && XDEBUG_MODE=off composer config http-basic.repo.magento.com ${USERNAME} ${PASSWORD}"
