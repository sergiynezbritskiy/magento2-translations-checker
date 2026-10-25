<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Tests\Unit\Collector;

use SergiyNezbritskiy\TranslationsChecker\Collector\MagentoPhraseCollector;
use SergiyNezbritskiy\TranslationsChecker\Exception\UsageException;
use PHPUnit\Framework\TestCase;

final class MagentoPhraseCollectorTest extends TestCase
{
    public function testExplainsHowToInstallWhenMagentoIsNotAvailable(): void
    {
        if (class_exists('\Magento\Setup\Module\I18n\ServiceLocator')) {
            self::markTestSkipped('Magento classes are loaded; the guard cannot trigger.');
        }

        $this->expectException(UsageException::class);
        $this->expectExceptionMessage('Magento 2 i18n classes (Magento\Setup\Module\I18n) are not available.');

        (new MagentoPhraseCollector())->collect(__DIR__);
    }
}
