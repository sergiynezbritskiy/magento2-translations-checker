<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Tests\Unit\Report;

use SergiyNezbritskiy\TranslationsChecker\Model\LanguageResult;
use SergiyNezbritskiy\TranslationsChecker\Report\ConsoleReporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class ConsoleReporterTest extends TestCase
{
    private ConsoleReporter $reporter;

    protected function setUp(): void
    {
        $this->reporter = new ConsoleReporter();
    }

    public function testViolationsArePrintedPerLocaleWithSummary(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL);
        $results = [
            new LanguageResult('ru_RU', ['Back'], ['Save'], ['Reset'], ['Gone']),
            new LanguageResult('uk_UA', ['Back', 'Save', 'Reset']),
        ];

        $this->reporter->render($output, '/project/src', 3, $results);
        $display = $output->fetch();

        self::assertStringContainsString('[ru_RU] missing      "Save"', $display);
        self::assertStringContainsString('[ru_RU] untranslated "Reset"', $display);
        self::assertStringContainsString('[ru_RU] obsolete     "Gone"', $display);
        self::assertStringNotContainsString('[ru_RU] ok', $display);
        self::assertStringContainsString('Collected 3 phrase(s) from /project/src.', $display);
        self::assertStringContainsString('ru_RU: 1 ok, 1 missing, 1 untranslated, 1 obsolete.', $display);
        self::assertStringContainsString('uk_UA: 3 ok, 0 missing, 0 untranslated, 0 obsolete.', $display);
    }

    public function testOkPhrasesAreOnlyPrintedWhenVerbose(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);

        $this->reporter->render($output, '/project/src', 1, [new LanguageResult('uk_UA', ['Back'])]);

        self::assertStringContainsString('[uk_UA] ok           "Back"', $output->fetch());
    }

    public function testSkippedLocaleOnlyAppearsInSummary(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_VERBOSE);

        $this->reporter->render($output, '/project/src', 1, [], ['en_US']);
        $display = $output->fetch();

        self::assertStringNotContainsString('[en_US]', $display);
        self::assertStringContainsString('en_US: skipped (source locale is not checked).', $display);
    }

    public function testMarkupInPhrasesIsNotInterpretedAsConsoleTags(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);

        $this->reporter->render($output, '/project/src', 1, [new LanguageResult('uk_UA', [], ['<b>Bold</b>'])]);

        self::assertStringContainsString('"<b>Bold</b>"', $output->fetch());
    }
}
