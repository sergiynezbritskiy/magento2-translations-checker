<?php

declare(strict_types=1);

namespace SergiyNezbritskiy\TranslationsChecker\Tests\Support;

final class TemporaryDirectory
{
    private readonly string $path;

    public function __construct(string $area)
    {
        $this->path = sys_get_temp_dir() . '/magento2-translations-checker-' . $area . '-test-' . uniqid();
        mkdir($this->path, 0777, true);
    }

    public function path(string $relative = ''): string
    {
        return $relative === '' ? $this->path : $this->path . '/' . ltrim($relative, '/');
    }

    public function remove(): void
    {
        $this->removeDirectory($this->path);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach ((array) scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);

                continue;
            }

            unlink($path);
        }

        rmdir($dir);
    }
}
