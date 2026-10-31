<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Tests\Unit\Console;

use SergiyNezbritskiy\TranslationsChecker\Console\CheckCommand;
use SergiyNezbritskiy\TranslationsChecker\Exception\UsageException;
use SergiyNezbritskiy\TranslationsChecker\Tests\Support\InMemoryPhraseCollector;
use SergiyNezbritskiy\TranslationsChecker\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckCommandTest extends TestCase
{
    private const FIXTURES_I18N = __DIR__ . '/../../fixtures/module/i18n';

    /** Every phrase of tests/fixtures/module, as Magento's collector returns them. */
    private const FIXTURE_PHRASES = [
        'Hello %1',
        'Phrase object',
        "It's escaped",
        'Template phrase',
        'Email directive',
        'Knockout binding',
        'Translate attribute',
        'Js in template',
        'Mage phrase',
        'Js phrase',
        'Xml section label',
        'Xml group label',
        'Xml group comment',
    ];

    private TemporaryDirectory $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = new TemporaryDirectory('command');
        mkdir($this->tmpDir->path('i18n'));
    }

    protected function tearDown(): void
    {
        $this->tmpDir->remove();
    }

    public function testFullyTranslatedDictionariesPass(): void
    {
        $this->writeDictionary('uk_UA', "Back,Назад\nSave,Зберегти\n");
        $this->writeDictionary('ru_RU', "Back,Назад\nSave,Сохранить\n");
        $tester = $this->createTester(['Back', 'Save']);

        $exitCode = $tester->execute([
            '--source' => $this->tmpDir->path(),
            '--languages' => ['uk_UA', 'ru_RU'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Collected 2 phrase(s)', $tester->getDisplay());
        self::assertStringContainsString('uk_UA: 2 ok, 0 missing, 0 untranslated, 0 obsolete.', $tester->getDisplay());
        self::assertStringContainsString('ru_RU: 2 ok, 0 missing, 0 untranslated, 0 obsolete.', $tester->getDisplay());
    }

    public function testMissingPhraseFailsTheCheck(): void
    {
        $this->writeDictionary('uk_UA', "Back,Назад\n");
        $tester = $this->createTester(['Back', 'Save']);

        $exitCode = $tester->execute(['--source' => $this->tmpDir->path(), '--languages' => ['uk_UA']]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('[uk_UA] missing      "Save"', $tester->getDisplay());
    }

    public function testTranslationsDirectoryDefaultsToI18nInsideSource(): void
    {
        $this->writeDictionary('uk_UA', "Back,Назад\n");
        $collector = new InMemoryPhraseCollector(['Back']);
        $tester = $this->createTester($collector);

        $exitCode = $tester->execute(['--source' => $this->tmpDir->path(), '--languages' => ['uk_UA']]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertSame([realpath($this->tmpDir->path())], $collector->requestedDirs);
    }

    public function testExplicitTranslationsDirectoryIsUsed(): void
    {
        $tester = $this->createTester(self::FIXTURE_PHRASES);

        $exitCode = $tester->execute([
            '--source' => $this->tmpDir->path(),
            '--translations' => self::FIXTURES_I18N,
            '--languages' => ['ru_RU', 'uk_UA'],
        ]);
        $display = $tester->getDisplay();

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('[ru_RU] missing      "Template phrase"', $display);
        self::assertStringContainsString('[ru_RU] untranslated "Phrase object"', $display);
        self::assertStringContainsString('[ru_RU] obsolete     "Old phrase"', $display);
        self::assertStringContainsString('ru_RU: 11 ok, 1 missing, 1 untranslated, 1 obsolete.', $display);
        self::assertStringContainsString('uk_UA: 13 ok, 0 missing, 0 untranslated, 0 obsolete.', $display);
    }

    public function testCommaSeparatedLanguagesAreAccepted(): void
    {
        $this->writeDictionary('uk_UA', "Back,Назад\n");
        $this->writeDictionary('ru_RU', "Back,Назад\n");
        $tester = $this->createTester(['Back']);

        $exitCode = $tester->execute(['--source' => $this->tmpDir->path(), '--languages' => ['uk_UA, ru_RU,']]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('uk_UA: 1 ok', $tester->getDisplay());
        self::assertStringContainsString('ru_RU: 1 ok', $tester->getDisplay());
    }

    public function testIdenticalTranslationFailsWithoutAllowIdentical(): void
    {
        $this->writeDictionary('uk_UA', "ID,ID\n");
        $tester = $this->createTester(['ID']);

        $exitCode = $tester->execute(['--source' => $this->tmpDir->path(), '--languages' => ['uk_UA']]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('[uk_UA] untranslated "ID"', $tester->getDisplay());
    }

    public function testAllowIdenticalAcceptsIdenticalTranslations(): void
    {
        $this->writeDictionary('uk_UA', "ID,ID\n\"(+ %1)\",\"(+ %1)\"\nBack,Назад\n");
        $this->writeDictionary('ru_RU', "ID,ID\n\"(+ %1)\",\"(+ %1)\"\nBack,Назад\n");
        $tester = $this->createTester(['ID', '(+ %1)', 'Back']);

        $exitCode = $tester->execute([
            '--source' => $this->tmpDir->path(),
            '--languages' => ['uk_UA,ru_RU'],
            '--allow-identical' => ['ID', '(+ %1)'],
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('uk_UA: 3 ok, 0 missing, 0 untranslated, 0 obsolete.', $tester->getDisplay());
        self::assertStringContainsString('ru_RU: 3 ok, 0 missing, 0 untranslated, 0 obsolete.', $tester->getDisplay());
    }

    public function testAllowIdenticalStillReportsMissingRow(): void
    {
        $this->writeDictionary('uk_UA', "Back,Назад\n");
        $tester = $this->createTester(['ID', 'Back']);

        $exitCode = $tester->execute([
            '--source' => $this->tmpDir->path(),
            '--languages' => ['uk_UA'],
            '--allow-identical' => ['ID'],
        ]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('[uk_UA] missing      "ID"', $tester->getDisplay());
    }

    public function testAllowIdenticalValueIsNotSplitOnCommas(): void
    {
        $this->writeDictionary('uk_UA', "\"Yes, No\",\"Yes, No\"\nYes,Yes\nNo,Так\n");
        $tester = $this->createTester(['Yes, No', 'Yes', 'No']);

        $exitCode = $tester->execute([
            '--source' => $this->tmpDir->path(),
            '--languages' => ['uk_UA'],
            '--allow-identical' => ['Yes, No'],
        ]);
        $display = $tester->getDisplay();

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('[uk_UA] untranslated "Yes"', $display);
        self::assertStringNotContainsString('"Yes, No"', $display);
        self::assertStringContainsString('uk_UA: 2 ok, 0 missing, 1 untranslated, 0 obsolete.', $display);
    }

    public function testSourceLocaleIsSkipped(): void
    {
        $this->writeDictionary('en_US', "Back,Back\n");
        $tester = $this->createTester(['Back']);

        $exitCode = $tester->execute(['--source' => $this->tmpDir->path(), '--languages' => ['en_US']]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('en_US: skipped (source locale is not checked).', $tester->getDisplay());
    }

    public function testMissingDictionaryFileReportsEveryPhraseAsMissing(): void
    {
        $tester = $this->createTester(['Back', 'Save']);

        $exitCode = $tester->execute(['--source' => $this->tmpDir->path(), '--languages' => ['uk_UA']]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('uk_UA: 0 ok, 2 missing, 0 untranslated, 0 obsolete.', $tester->getDisplay());
    }

    public function testNoPhrasesAndNoDictionaryRowsIsClean(): void
    {
        $this->writeDictionary('uk_UA', '');
        $tester = $this->createTester([]);

        $exitCode = $tester->execute(['--source' => $this->tmpDir->path(), '--languages' => ['uk_UA']]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Collected 0 phrase(s)', $tester->getDisplay());
    }

    public function testOkPhrasesAreShownWithVerboseFlag(): void
    {
        $this->writeDictionary('uk_UA', "Back,Назад\n");
        $tester = $this->createTester(['Back']);

        $tester->execute(
            ['--source' => $this->tmpDir->path(), '--languages' => ['uk_UA']],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE],
        );

        self::assertStringContainsString('[uk_UA] ok           "Back"', $tester->getDisplay());
    }

    public function testMissingLanguagesOptionThrowsUsageException(): void
    {
        $tester = $this->createTester(['Back']);

        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('At least one --languages=<locale> is required');

        $tester->execute(['--source' => $this->tmpDir->path()]);
    }

    public function testBlankLanguagesOptionThrowsUsageException(): void
    {
        $tester = $this->createTester(['Back']);

        $this->expectException(UsageException::class);

        $tester->execute(['--source' => $this->tmpDir->path(), '--languages' => [' , ']]);
    }

    public function testMissingSourceOptionThrowsUsageException(): void
    {
        $tester = $this->createTester(['Back']);

        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('--source is required.');

        $tester->execute(['--languages' => ['uk_UA']]);
    }

    public function testNonExistentSourceDirectoryThrowsUsageException(): void
    {
        $tester = $this->createTester(['Back']);

        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('--source: directory "' . $this->tmpDir->path('nope') . '" does not exist.');

        $tester->execute(['--source' => $this->tmpDir->path('nope'), '--languages' => ['uk_UA']]);
    }

    public function testNonExistentTranslationsDirectoryThrowsUsageException(): void
    {
        $tester = $this->createTester(['Back']);

        $this->expectException(UsageException::class);
        $this->expectExceptionMessage(
            '--translations: directory "' . $this->tmpDir->path('nope') . '" does not exist.',
        );

        $tester->execute([
            '--source' => $this->tmpDir->path(),
            '--translations' => $this->tmpDir->path('nope'),
            '--languages' => ['uk_UA'],
        ]);
    }

    public function testUsageExceptionCarriesInvalidExitCode(): void
    {
        $exception = new UsageException('boom');

        self::assertSame(Command::INVALID, $exception->getCode());
    }

    /**
     * @param list<string>|InMemoryPhraseCollector $phrases
     */
    private function createTester(array|InMemoryPhraseCollector $phrases): CommandTester
    {
        $collector = $phrases instanceof InMemoryPhraseCollector ? $phrases : new InMemoryPhraseCollector($phrases);

        return new CommandTester(new CheckCommand($collector));
    }

    private function writeDictionary(string $locale, string $content): void
    {
        file_put_contents($this->tmpDir->path('i18n/' . $locale . '.csv'), $content);
    }
}
