<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Tests\Unit\Dictionary;

use SergiyNezbritskiy\TranslationsChecker\Dictionary\DictionaryReader;
use SergiyNezbritskiy\TranslationsChecker\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\TestCase;

final class DictionaryReaderTest extends TestCase
{
    private TemporaryDirectory $tmpDir;
    private DictionaryReader $reader;

    protected function setUp(): void
    {
        $this->tmpDir = new TemporaryDirectory('dictionary');
        $this->reader = new DictionaryReader();
    }

    protected function tearDown(): void
    {
        $this->tmpDir->remove();
    }

    public function testReadsPhraseTranslationPairs(): void
    {
        $file = $this->write("Back,Назад\n\"Save and Continue Edit\",\"Зберегти та продовжити\"\n");

        $pairs = $this->reader->read($file);

        self::assertSame(['Back' => 'Назад', 'Save and Continue Edit' => 'Зберегти та продовжити'], $pairs);
    }

    public function testDoubledQuotesAreUnescapedLikeMagentoDoes(): void
    {
        $file = $this->write("\"Object with id \"\"%1\"\" does not exist.\",\"Об'єкт з ID \"\"%1\"\" не знайдено.\"\n");

        $pairs = $this->reader->read($file);

        self::assertSame(['Object with id "%1" does not exist.' => "Об'єкт з ID \"%1\" не знайдено."], $pairs);
    }

    public function testBackslashIsNotAnEscapeCharacter(): void
    {
        $file = $this->write("\"Path \\\\ separator\",\"Роздільник \\\\ шляху\"\n");

        $pairs = $this->reader->read($file);

        self::assertSame(['Path \\\\ separator' => 'Роздільник \\\\ шляху'], $pairs);
    }

    public function testLaterDuplicateOverridesEarlierOne(): void
    {
        $file = $this->write("Back,Назад\nBack,Повернутися\n");

        $pairs = $this->reader->read($file);

        self::assertSame(['Back' => 'Повернутися'], $pairs);
    }

    public function testRowWithoutTranslationColumnYieldsEmptyTranslation(): void
    {
        $file = $this->write("Back\n");

        $pairs = $this->reader->read($file);

        self::assertSame(['Back' => ''], $pairs);
    }

    public function testContextColumnsAreIgnored(): void
    {
        $file = $this->write("Back,Назад,module,Vendor_Module\n");

        $pairs = $this->reader->read($file);

        self::assertSame(['Back' => 'Назад'], $pairs);
    }

    public function testBlankLinesAndEmptyPhrasesAreSkipped(): void
    {
        $file = $this->write("\nBack,Назад\n\n,orphan\n");

        $pairs = $this->reader->read($file);

        self::assertSame(['Back' => 'Назад'], $pairs);
    }

    public function testMissingFileIsAnEmptyDictionary(): void
    {
        self::assertSame([], $this->reader->read($this->tmpDir->path('xx_XX.csv')));
    }

    private function write(string $content): string
    {
        $file = $this->tmpDir->path('uk_UA.csv');
        file_put_contents($file, $content);

        return $file;
    }
}
