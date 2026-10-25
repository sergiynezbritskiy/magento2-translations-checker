<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Collector;

use InvalidArgumentException;
use SergiyNezbritskiy\TranslationsChecker\Exception\UsageException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Collects phrases by calling the very same Magento code that backs
 * `bin/magento i18n:collect-phrases`.
 *
 * \Magento\Setup\Console\Command\I18nCollectPhrasesCommand::execute() does nothing more than
 * `ServiceLocator::getDictionaryGenerator()->generate($directory, $output, $withContext)`.
 * This class issues the same call with a temporary output file and reads the result back with
 * Magento's own dictionary loader (the one `i18n:pack` uses), so the phrase list is produced by
 * Magento, not re-implemented here.
 *
 * The Magento classes are not Composer dependencies of this package: they are provided by the
 * Magento 2 project this tool is installed into (its vendor/autoload.php maps
 * Magento\Setup\ to setup/src/Magento/Setup/ and ships magento/framework and
 * magento/module-email, which the HTML adapter needs).
 *
 * Call collect() once per process: Magento's ServiceLocator is a singleton and its parser keeps
 * the phrases of every previous run, which is fine for a one-shot command and for this CLI.
 */
final class MagentoPhraseCollector implements PhraseCollectorInterface
{
    private const SERVICE_LOCATOR = '\Magento\Setup\Module\I18n\ServiceLocator';
    private const FACTORY = '\Magento\Setup\Module\I18n\Factory';
    private const CSV_LOADER = '\Magento\Setup\Module\I18n\Dictionary\Loader\File\Csv';

    public function collect(string $sourceDir): array
    {
        $this->assertMagentoIsAvailable();

        $tempCsv = tempnam(sys_get_temp_dir(), 'i18n-');

        if ($tempCsv === false) {
            throw new RuntimeException('Unable to create a temporary file for the phrase dictionary.');
        }

        try {
            if (!$this->generateDictionary($sourceDir, $tempCsv)) {
                return [];
            }

            return $this->readDictionary($tempCsv);
        } finally {
            if (is_file($tempCsv)) {
                unlink($tempCsv);
            }
        }
    }

    private function assertMagentoIsAvailable(): void
    {
        if (class_exists(self::SERVICE_LOCATOR) && class_exists(self::CSV_LOADER)) {
            return;
        }

        throw new UsageException(
            'Magento 2 i18n classes (Magento\Setup\Module\I18n) are not available. Install '
            . 'magento2-translations-checker as a dev dependency of a Magento 2 project and run it '
            . 'from that project\'s vendor/bin, or preload the project\'s vendor/autoload.php.',
        );
    }

    /**
     * Returns false when Magento found no phrases at all (its generator reports that as an
     * exception, while for a translation check an empty source is simply nothing to verify).
     */
    private function generateDictionary(string $sourceDir, string $outputFile): bool
    {
        $serviceLocator = self::SERVICE_LOCATOR;

        try {
            $serviceLocator::getDictionaryGenerator()->generate($sourceDir, $outputFile, false);
        } catch (UnexpectedValueException) {
            // Magento: "No phrases found in the specified dictionary file."
            return false;
        } catch (InvalidArgumentException $e) {
            // Magento: "Specified path doesn't exist" / "Cannot open file for write dictionary".
            throw new UsageException($e->getMessage(), $e);
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function readDictionary(string $csvFile): array
    {
        $factoryClass = self::FACTORY;
        $loaderClass = self::CSV_LOADER;
        $loader = new $loaderClass(new $factoryClass());
        $phrases = [];

        foreach ($loader->load($csvFile)->getPhrases() as $phrase) {
            $phrases[] = (string) $phrase->getPhrase();
        }

        return $phrases;
    }
}
