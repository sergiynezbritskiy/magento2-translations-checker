<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Tests\Integration\Collector;

use Composer\Autoload\ClassLoader;
use SergiyNezbritskiy\TranslationsChecker\Collector\MagentoPhraseCollector;
use SergiyNezbritskiy\TranslationsChecker\Exception\UsageException;
use SergiyNezbritskiy\TranslationsChecker\Tests\Support\TemporaryDirectory;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Runs the collector against a real Magento 2 code base.
 *
 * Set MAGENTO_ROOT to a Magento 2 installation (the directory holding setup/ and vendor/).
 * Only the namespaces the collector needs are autoloaded from there; Magento's full
 * vendor/autoload.php is deliberately not included because it would shadow this project's
 * PHPUnit with Magento's own version.
 *
 * Each test runs in its own process: Magento's ServiceLocator is a process-wide singleton whose
 * parser keeps every phrase it has ever seen, exactly like the one-shot `i18n:collect-phrases`
 * command assumes. The CLI calls the collector once per process, so this only matters here.
 */
#[RunTestsInSeparateProcesses]
final class MagentoPhraseCollectorTest extends TestCase
{
    private const FIXTURE_MODULE = __DIR__ . '/../../fixtures/module';

    private const EXPECTED_PHRASES = [
        'Email directive',
        'Hello %1',
        "It's escaped",
        'Js in template',
        'Js phrase',
        'Knockout binding',
        'Mage phrase',
        'Phrase object',
        'Template phrase',
        'Translate attribute',
        'Xml group comment',
        'Xml group label',
        'Xml section label',
    ];

    protected function setUp(): void
    {
        $magentoRoot = getenv('MAGENTO_ROOT');

        if (!is_string($magentoRoot) || $magentoRoot === '') {
            self::markTestSkipped('Set MAGENTO_ROOT=/path/to/magento to run the Magento integration test.');
        }

        self::registerMagentoAutoloader(rtrim($magentoRoot, '/'));
    }

    public function testCollectsTheSamePhrasesAsCollectPhrasesCommand(): void
    {
        $phrases = (new MagentoPhraseCollector())->collect(self::FIXTURE_MODULE);
        sort($phrases);

        self::assertSame(self::EXPECTED_PHRASES, $phrases);
    }

    public function testDirectoryWithoutPhrasesYieldsEmptyList(): void
    {
        $dir = new TemporaryDirectory('collector');
        file_put_contents($dir->path('plain.php'), "<?php\n\$noPhrases = true;\n");

        try {
            self::assertSame([], (new MagentoPhraseCollector())->collect($dir->path()));
        } finally {
            $dir->remove();
        }
    }

    public function testMagentoPathErrorsBecomeUsageErrors(): void
    {
        $this->expectException(UsageException::class);
        $this->expectExceptionMessage("Specified path doesn't exist");

        (new MagentoPhraseCollector())->collect(self::FIXTURE_MODULE . '/does-not-exist');
    }

    private static function registerMagentoAutoloader(string $magentoRoot): void
    {
        $loader = new ClassLoader();
        $loader->addPsr4('Magento\\Setup\\', $magentoRoot . '/setup/src/Magento/Setup/');
        $loader->addPsr4('Magento\\Framework\\', $magentoRoot . '/vendor/magento/framework/');
        $loader->addPsr4('Magento\\Email\\', $magentoRoot . '/vendor/magento/module-email/');
        $loader->addPsr4('Laminas\\Filter\\', $magentoRoot . '/vendor/laminas/laminas-filter/src/');
        $loader->register();
    }
}
