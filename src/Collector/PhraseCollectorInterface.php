<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Collector;

/**
 * Produces the list of translatable phrases found under a source directory.
 *
 * The contract is the output of `bin/magento i18n:collect-phrases <dir>`: every distinct
 * phrase, already unquoted and unescaped, in the order Magento emits it.
 */
interface PhraseCollectorInterface
{
    /**
     * @return list<string>
     */
    public function collect(string $sourceDir): array;
}
