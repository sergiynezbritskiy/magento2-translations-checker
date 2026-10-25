set shell := ["bash", "-cu"]

export DOCKER_COMPOSE := "docker compose -f docker-compose.yml"

default:
    just --list --unsorted

#############################################
#################### PHP ####################
#############################################

# Access the project's php container, or run a command in it, e.g. `just php composer run all`
[group('php')]
@php *args:
    if [ -n "{{args}}" ]; then \
      {{DOCKER_COMPOSE}} run --rm php /bin/bash -c "{{args}}"; \
    else \
      {{DOCKER_COMPOSE}} run --rm php /bin/bash; \
    fi

# Run codesniffer, mess detector, phpstan and phpunit (unit suite; integration needs `just m2-up`)
[group('php')]
all:
    just php composer run all

#############################################
################## MAGENTO2 #################
#############################################

# Access the Magento php container (sergiynezbritskiy/magento2-php85), or run a command in it
[group('magento2')]
@m2-php *args:
    if [ -n "{{args}}" ]; then \
      {{DOCKER_COMPOSE}} run --rm magento /bin/bash -c "{{args}}"; \
    else \
      {{DOCKER_COMPOSE}} run --rm magento /bin/bash; \
    fi

# Install a Magento 2 code base into build/magento, link this checker and a dummy module into it
[group('magento2')]
m2-install:
    mkdir -p ~/.environment build # create before docker does, otherwise they are owned by root
    just m2-php bash ./bin/scripts/10-project-create.sh
    just m2-php bash ./bin/scripts/15-checker-install.sh
    just m2-php bash ./bin/scripts/20-dummy-module-install.sh
    just m2-php bash ./bin/scripts/30-project-install.sh
    @echo "[INFO] Magento 2 installed into build/magento. Try: just i18n"
alias m2-up := m2-install

# Remove the Magento 2 sandbox from build/
[group('magento2')]
m2-uninstall:
    just m2-php rm -rf build/magento build/module-dummy build/_
    @echo "[INFO] Removed."
alias m2-rm := m2-uninstall

[group('magento2')]
m2-reinstall:
    just m2-rm
    time just m2-up

# Run bin/magento of the sandbox, e.g. `just m2 i18n:collect-phrases build/module-dummy -o /dev/stdout`
[group('magento2')]
m2 *args:
    just m2-php XDEBUG_MODE=off php build/magento/bin/magento {{args}}
alias m := m2

# Run the checker from the sandbox's vendor/bin against the dummy module
[group('magento2')]
i18n *args='--languages=ru_RU,uk_UA':
    just m2-php XDEBUG_MODE=off php build/magento/vendor/bin/i18n --source=build/magento/vendor/sergiynezbritskiy/module-dummy {{args}}

# Regenerate the dummy module's en_US.csv with Magento's own command (parity reference)
[group('magento2')]
collect-phrases:
    just m2 i18n:collect-phrases build/module-dummy -o build/module-dummy/i18n/en_US.csv

#############################################
#################### TESTS ##################
#############################################

# Run the integration tests of the collector against the sandbox in build/magento
[group('tests')]
test-integration:
    {{DOCKER_COMPOSE}} run --rm -e MAGENTO_ROOT=/var/www/html/build/magento php vendor/bin/phpunit --testsuite integration
