<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Console;

use SergiyNezbritskiy\TranslationsChecker\Collector\MagentoPhraseCollector;
use SergiyNezbritskiy\TranslationsChecker\Collector\PhraseCollectorInterface;
use Symfony\Component\Console\Application;

final class ApplicationFactory
{
    public static function create(?PhraseCollectorInterface $collector = null): Application
    {
        $application = new Application('magento2-translations-checker');
        $command = new CheckCommand($collector ?? new MagentoPhraseCollector());

        $application->add($command);
        $application->setDefaultCommand((string) $command->getName(), true);

        return $application;
    }
}
