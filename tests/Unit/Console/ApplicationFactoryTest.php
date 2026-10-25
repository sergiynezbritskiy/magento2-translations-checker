<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Tests\Unit\Console;

use SergiyNezbritskiy\TranslationsChecker\Console\ApplicationFactory;
use SergiyNezbritskiy\TranslationsChecker\Tests\Support\InMemoryPhraseCollector;
use SergiyNezbritskiy\TranslationsChecker\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * @SuppressWarnings("PHPMD.StaticAccess") The factory under test is a static constructor by design.
 */
final class ApplicationFactoryTest extends TestCase
{
    private TemporaryDirectory $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = new TemporaryDirectory('application');
        mkdir($this->tmpDir->path('i18n'));
    }

    protected function tearDown(): void
    {
        $this->tmpDir->remove();
    }

    public function testCheckIsTheDefaultAndOnlyCommand(): void
    {
        file_put_contents($this->tmpDir->path('i18n/uk_UA.csv'), "Back,Назад\n");
        $tester = $this->createTester(['Back']);

        $exitCode = $tester->run(['--source' => $this->tmpDir->path(), '--languages' => ['uk_UA']]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('uk_UA: 1 ok', $tester->getDisplay());
    }

    public function testUsageErrorsExitWithCodeTwo(): void
    {
        $tester = $this->createTester(['Back']);

        $exitCode = $tester->run(['--source' => $this->tmpDir->path()]);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('At least one --languages=<locale> is required', $tester->getDisplay());
    }

    public function testDefaultCollectorIsMagentoBacked(): void
    {
        $application = ApplicationFactory::create();

        self::assertSame('magento2-translations-checker', $application->getName());
        self::assertTrue($application->has('check'));
    }

    /**
     * @param list<string> $phrases
     */
    private function createTester(array $phrases): ApplicationTester
    {
        $application = ApplicationFactory::create(new InMemoryPhraseCollector($phrases));
        $application->setAutoExit(false);

        return new ApplicationTester($application);
    }
}
