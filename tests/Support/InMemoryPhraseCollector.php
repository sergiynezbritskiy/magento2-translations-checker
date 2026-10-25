<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Tests\Support;

use SergiyNezbritskiy\TranslationsChecker\Collector\PhraseCollectorInterface;

final class InMemoryPhraseCollector implements PhraseCollectorInterface
{
    /** @var list<string> */
    public array $requestedDirs = [];

    /**
     * @param list<string> $phrases
     */
    public function __construct(private readonly array $phrases)
    {
    }

    public function collect(string $sourceDir): array
    {
        $this->requestedDirs[] = $sourceDir;

        return $this->phrases;
    }
}
