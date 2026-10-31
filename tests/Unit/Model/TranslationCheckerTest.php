<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Tests\Unit\Model;

use SergiyNezbritskiy\TranslationsChecker\Model\TranslationChecker;
use PHPUnit\Framework\TestCase;

final class TranslationCheckerTest extends TestCase
{
    private TranslationChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new TranslationChecker();
    }

    public function testFullyTranslatedDictionaryHasNoViolations(): void
    {
        $result = $this->checker->check('uk_UA', ['Back', 'Save'], ['Back' => 'Назад', 'Save' => 'Зберегти']);

        self::assertSame('uk_UA', $result->locale);
        self::assertSame(['Back', 'Save'], $result->translated);
        self::assertFalse($result->hasViolations());
    }

    public function testPhraseAbsentFromDictionaryIsMissing(): void
    {
        $result = $this->checker->check('uk_UA', ['Back', 'Save'], ['Back' => 'Назад']);

        self::assertSame(['Save'], $result->missing);
        self::assertSame(['Back'], $result->translated);
        self::assertTrue($result->hasViolations());
    }

    public function testEmptyTranslationIsUntranslated(): void
    {
        $result = $this->checker->check('uk_UA', ['Back'], ['Back' => '']);

        self::assertSame(['Back'], $result->untranslated);
        self::assertSame([], $result->missing);
        self::assertTrue($result->hasViolations());
    }

    public function testTranslationEqualToPhraseIsUntranslated(): void
    {
        $result = $this->checker->check('uk_UA', ['Back'], ['Back' => 'Back']);

        self::assertSame(['Back'], $result->untranslated);
        self::assertSame([], $result->translated);
    }

    public function testAllowedIdenticalTranslationIsTranslated(): void
    {
        $checker = new TranslationChecker(['ID', '(+ %1)']);

        $result = $checker->check(
            'uk_UA',
            ['ID', '(+ %1)', 'Back'],
            ['ID' => 'ID', '(+ %1)' => '(+ %1)', 'Back' => 'Back'],
        );

        self::assertSame(['ID', '(+ %1)'], $result->translated);
        self::assertSame(['Back'], $result->untranslated);
    }

    public function testAllowedIdenticalPhraseMayStillBeTranslated(): void
    {
        $checker = new TranslationChecker(['ID']);

        $result = $checker->check('uk_UA', ['ID'], ['ID' => 'Ід.']);

        self::assertSame(['ID'], $result->translated);
        self::assertFalse($result->hasViolations());
    }

    public function testAllowedIdenticalPhraseWithEmptyTranslationIsUntranslated(): void
    {
        $checker = new TranslationChecker(['ID']);

        $result = $checker->check('uk_UA', ['ID'], ['ID' => '']);

        self::assertSame(['ID'], $result->untranslated);
    }

    public function testAllowedIdenticalPhraseWithoutRowIsStillMissing(): void
    {
        $checker = new TranslationChecker(['ID']);

        $result = $checker->check('uk_UA', ['ID'], []);

        self::assertSame(['ID'], $result->missing);
        self::assertSame([], $result->translated);
        self::assertTrue($result->hasViolations());
    }

    public function testAllowedIdenticalDoesNotAffectObsoleteDetection(): void
    {
        $checker = new TranslationChecker(['Gone']);

        $result = $checker->check('uk_UA', ['Back'], ['Back' => 'Назад', 'Gone' => 'Gone']);

        self::assertSame(['Gone'], $result->obsolete);
        self::assertSame(['Back'], $result->translated);
        self::assertTrue($result->hasViolations());
    }

    public function testDictionaryRowWithoutSourcePhraseIsObsolete(): void
    {
        $result = $this->checker->check('uk_UA', ['Back'], ['Back' => 'Назад', 'Gone' => 'Зникло']);

        self::assertSame(['Gone'], $result->obsolete);
        self::assertTrue($result->hasViolations());
    }

    public function testNumericPhrasesAreReportedAsStrings(): void
    {
        $result = $this->checker->check('uk_UA', ['Back'], ['Back' => 'Назад', '42' => 'сорок два']);

        self::assertSame(['42'], $result->obsolete);
    }

    public function testNoPhrasesAndEmptyDictionaryIsClean(): void
    {
        $result = $this->checker->check('uk_UA', [], []);

        self::assertFalse($result->hasViolations());
        self::assertSame([], $result->translated);
    }
}
