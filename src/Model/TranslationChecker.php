<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Model;

/**
 * Compares the collected phrases with one dictionary. Pure logic, no I/O.
 *
 * A translation that equals its phrase counts as untranslated: Magento drops such rows when
 * loading dictionaries (\Magento\Framework\Translate::_addData), so at runtime they behave
 * exactly like missing ones.
 */
final class TranslationChecker
{
    /**
     * @param list<string> $phrases
     * @param array<int|string, string> $dictionary phrase => translation
     */
    public function check(string $locale, array $phrases, array $dictionary): LanguageResult
    {
        $translated = [];
        $missing = [];
        $untranslated = [];

        foreach ($phrases as $phrase) {
            if (!array_key_exists($phrase, $dictionary)) {
                $missing[] = $phrase;

                continue;
            }

            $translation = $dictionary[$phrase];

            if ($translation === '' || $translation === $phrase) {
                $untranslated[] = $phrase;

                continue;
            }

            $translated[] = $phrase;
        }

        $obsolete = array_map(
            static fn (int|string $phrase): string => (string) $phrase,
            array_keys(array_diff_key($dictionary, array_fill_keys($phrases, true))),
        );

        return new LanguageResult($locale, $translated, $missing, $untranslated, $obsolete);
    }
}
