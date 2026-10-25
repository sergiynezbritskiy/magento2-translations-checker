# magento2-translations-checker

CLI tool that verifies the `i18n` dictionaries of a Magento 2 module are complete. It collects
the translatable phrases exactly like `bin/magento i18n:collect-phrases` does and fails when a
dictionary is missing a phrase, leaves it untranslated, or still carries a phrase that no longer
exists in the code. Intended for CI/CD pipelines.

This is not a Magento module. It is a standalone Composer package, but it only works inside a
Magento 2 project because it calls Magento's own phrase collector at runtime.

## Installation

Install it as a dev dependency **of the Magento 2 project** (the directory with `bin/magento`),
not of the module:

```shell
composer require --dev sergiynezbritskiy/magento2-translations-checker
```

The project's `vendor/autoload.php` provides `Magento\Setup\Module\I18n`, `magento/framework` and
`magento/module-email`, which the collector needs. They are therefore not declared as dependencies
of this package.

## Usage

```shell
vendor/bin/i18n --source=/path/to/module/src --translations=/path/to/module/src/i18n --languages=ru_RU --languages=uk_UA
```

### Options

| Option                  | Description                                                                                                  |
|-------------------------|--------------------------------------------------------------------------------------------------------------|
| `--source=<dir>`        | Required. Directory to collect phrases from, the same one you pass to `i18n:collect-phrases`.               |
| `--translations=<dir>`  | Directory with `<locale>.csv` dictionaries. Defaults to `<source>/i18n`.                                     |
| `--languages=<locale>`  | Required, repeatable. Locales to check. `--languages=ru_RU,uk_UA` is accepted too.                           |
| `--colors`/`--no-colors`| Use colors in screen output (off by default).                                                                |
| `-v`                    | Also list the phrases that are translated correctly.                                                         |

### What is checked

For every locale `<L>` the file `<translations>/<L>.csv` is compared with the collected phrases:

- **missing**: the phrase has no row in the dictionary;
- **untranslated**: the row exists but the translation is empty or identical to the phrase
  (Magento drops such rows when loading a dictionary, so they are untranslated at runtime);
- **obsolete**: the dictionary has a row for a phrase that no longer exists in `--source`.

`en_US` is the locale the phrases are written in, so it is never checked and is reported as skipped
if listed. A dictionary file that does not exist is treated as empty.

Example output:

```
[ru_RU] missing      "Delete Object"
[ru_RU] untranslated "Back"
[uk_UA] obsolete     "Old phrase"

Collected 57 phrase(s) from /var/www/html/_/src.
ru_RU: 55 ok, 1 missing, 1 untranslated, 0 obsolete.
uk_UA: 56 ok, 0 missing, 0 untranslated, 1 obsolete.
```

### Exit codes

| Code | Meaning                                                                   |
|------|---------------------------------------------------------------------------|
| 0    | Every listed dictionary is complete                                       |
| 1    | At least one missing, untranslated or obsolete phrase                     |
| 2    | Usage error (missing options, unknown directory, Magento classes missing) |

### How phrases are collected

The tool calls `\Magento\Setup\Module\I18n\ServiceLocator::getDictionaryGenerator()->generate()`,
which is what `bin/magento i18n:collect-phrases` executes, and reads the generated dictionary back
with Magento's own loader. The phrase list is therefore identical to the `en_US.csv` produced by
that command for the same directory, including its quirks: everything under `--source` is scanned,
`Test/` directories included, and the parsed file types are `.php`, `.phtml`, `.html`, `.js` and `.xml`.

### Standalone usage (without installing into Magento)

When launched through a Magento project's `vendor/bin/i18n`, the binary uses that project's
autoloader (Composer's bin proxy passes it along), which also covers installs from a `path`
repository. From a bare checkout the Magento autoloader has to be preloaded instead:

```shell
php -d auto_prepend_file=/path/to/magento/vendor/autoload.php bin/i18n --source=... --languages=uk_UA
```

## Development

```shell
docker compose build
docker compose run --rm php composer install
docker compose run --rm php composer run all
```

`composer run all` runs codesniffer, mess detector, phpstan and the phpunit test suite with
coverage (reports are written to `var/reports/`). The same is available as `just all`.

### Magento 2 sandbox

The collector itself can only run inside a Magento 2 code base, so the project can install a
disposable one into `build/` the way the module projects do:

```shell
just m2-up      # composer create-project into build/magento, link this checker and a dummy module, composer update
just i18n       # run build/magento/vendor/bin/i18n against the dummy module (ru_RU has one violation of each kind)
just i18n --languages=uk_UA
just collect-phrases   # regenerate the dummy module's en_US.csv with bin/magento i18n:collect-phrases
just test-integration  # run the collector's integration tests against build/magento
just m2-rm      # remove the sandbox
```

What `just m2-up` produces:

- `build/magento`: a Magento 2 project (code base only; no database, no `setup:install`), created
  in the `sergiynezbritskiy/magento2-php85` container as user `magento`, so the files belong to you.
- `build/_`: relative symlinks to this package's `composer.json` and `src/` plus a copy of `bin/`.
  It is registered in the sandbox as a Composer `path` repository, so
  `build/magento/vendor/sergiynezbritskiy/magento2-translations-checker` is a symlink and source
  edits are picked up live (`bin/i18n` is copied because Composer refuses a `vendor/bin` proxy whose
  target resolves outside the package; rerun `just m2-up` after changing it). The indirection avoids
  linking the project root, which contains `build/` itself.
- `build/module-dummy`: a copy of `tests/fixtures/module`, registered as a `path` repository too.
  Edit or break it freely; `just m2-up` recreates it.

Credentials for repo.magento.com are read from `.env` (see `.env.sample`) or, when empty there,
from `~/.environment/.env`, exactly like in the module projects. Missing values are prompted for.
