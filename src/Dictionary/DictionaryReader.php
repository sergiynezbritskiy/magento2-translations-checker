<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Dictionary;

use RuntimeException;

/**
 * Reads a Magento i18n dictionary (`<locale>.csv`) into phrase => translation pairs.
 *
 * Mirrors how Magento itself consumes these files at runtime
 * (\Magento\Framework\File\Csv::getDataPairs, used by \Magento\Framework\Translate):
 * comma delimiter, double-quote enclosure, no escape character, column 0 is the phrase,
 * column 1 the translation, extra (context) columns are ignored and a later duplicate
 * phrase overrides an earlier one.
 */
final class DictionaryReader
{
    /**
     * @return array<int|string, string> phrase => translation (PHP turns numeric phrases into int keys);
     *                                   empty when the file does not exist
     */
    public function read(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Unable to read dictionary file "%s".', $path));
        }

        $pairs = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $phrase = (string) ($row[0] ?? '');

            if ($phrase === '') {
                continue;
            }

            $pairs[$phrase] = (string) ($row[1] ?? '');
        }

        fclose($handle);

        return $pairs;
    }
}
