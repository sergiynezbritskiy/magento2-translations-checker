<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Report;

use SergiyNezbritskiy\TranslationsChecker\Model\LanguageResult;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @SuppressWarnings("PHPMD.StaticAccess") OutputFormatter::escape() only exists as a static helper.
 */
final class ConsoleReporter
{
    private const STATUS_WIDTH = 12;

    /**
     * @param LanguageResult[] $results
     * @param list<string> $skippedLocales locales that were requested but are never checked
     */
    public function render(
        OutputInterface $output,
        string $sourceDir,
        int $phraseCount,
        array $results,
        array $skippedLocales = [],
    ): void {
        foreach ($results as $result) {
            $this->renderDetails($output, $result);
        }

        $output->writeln('');
        $output->writeln(
            sprintf('Collected %d phrase(s) from %s.', $phraseCount, OutputFormatter::escape($sourceDir)),
        );

        foreach ($results as $result) {
            $output->writeln($this->formatSummary($result));
        }

        foreach ($skippedLocales as $locale) {
            $output->writeln(sprintf('%s: skipped (source locale is not checked).', $locale));
        }
    }

    private function renderDetails(OutputInterface $output, LanguageResult $result): void
    {
        foreach ($result->missing as $phrase) {
            $this->renderPhrase($output, $result->locale, 'missing', 'error', $phrase);
        }

        foreach ($result->untranslated as $phrase) {
            $this->renderPhrase($output, $result->locale, 'untranslated', 'error', $phrase);
        }

        foreach ($result->obsolete as $phrase) {
            $this->renderPhrase($output, $result->locale, 'obsolete', 'error', $phrase);
        }

        if (!$output->isVerbose()) {
            return;
        }

        foreach ($result->translated as $phrase) {
            $this->renderPhrase($output, $result->locale, 'ok', 'info', $phrase);
        }
    }

    private function renderPhrase(
        OutputInterface $output,
        string $locale,
        string $status,
        string $tag,
        string $phrase,
    ): void {
        $output->writeln(sprintf(
            '[%s] <%s>%s</%s> "%s"',
            $locale,
            $tag,
            str_pad($status, self::STATUS_WIDTH),
            $tag,
            OutputFormatter::escape($phrase),
        ));
    }

    private function formatSummary(LanguageResult $result): string
    {
        return sprintf(
            '%s: <info>%d ok</info>, %s, %s, %s.',
            $result->locale,
            count($result->translated),
            $this->formatCount(count($result->missing), 'missing'),
            $this->formatCount(count($result->untranslated), 'untranslated'),
            $this->formatCount(count($result->obsolete), 'obsolete'),
        );
    }

    private function formatCount(int $count, string $label): string
    {
        $text = sprintf('%d %s', $count, $label);

        return $count > 0 ? sprintf('<error>%s</error>', $text) : $text;
    }
}
