<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Model;

/**
 * Compares the collected phrases with one dictionary. Pure logic, no I/O.
 *
 * A translation that equals its phrase counts as untranslated: Magento drops such rows when
 * loading dictionaries (\Magento\Framework\Translate::_addData), so at runtime they behave
 * exactly like missing ones. Phrases passed as $allowedIdentical are the exception: for them an
 * identical translation is intended ("ID", "(+ %1)") and counts as translated. Their row is still
 * required, so a phrase never added to the dictionary is reported as missing.
 */
final class TranslationChecker
{
    /** @var array<int|string, true> phrase => true */
    private readonly array $allowedIdentical;

    /**
     * @param string[] $allowedIdentical phrases whose translation may equal the phrase
     */
    public function __construct(array $allowedIdentical = [])
    {
        $this->allowedIdentical = array_fill_keys($allowedIdentical, true);
    }

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

            if ($this->isUntranslated($phrase, $dictionary[$phrase])) {
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

    private function isUntranslated(string $phrase, string $translation): bool
    {
        if ($translation === '') {
            return true;
        }

        return $translation === $phrase && !isset($this->allowedIdentical[$phrase]);
    }
}
