<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Model;

/**
 * Outcome of checking one locale's dictionary against the collected phrases.
 */
final class LanguageResult
{
    /**
     * @param list<string> $translated   phrases with a proper translation (or an allowed identical one)
     * @param list<string> $missing      phrases absent from the dictionary
     * @param list<string> $untranslated phrases present with an empty or (not allowed) identical translation
     * @param list<string> $obsolete     dictionary phrases that no longer exist in the source
     */
    public function __construct(
        public readonly string $locale,
        public readonly array $translated = [],
        public readonly array $missing = [],
        public readonly array $untranslated = [],
        public readonly array $obsolete = [],
    ) {
    }

    public function hasViolations(): bool
    {
        return $this->missing !== [] || $this->untranslated !== [] || $this->obsolete !== [];
    }
}
