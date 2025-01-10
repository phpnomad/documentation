<?php

namespace PHPNomad\Static\Handlers;

namespace PHPNomad\Static\Handlers;

use PHPNomad\Events\Interfaces\CanHandle;
use PHPNomad\Events\Interfaces\Event;
use PHPNomad\Static\Events\StaticCompileInitiated;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * @extends CanHandle<StaticCompileInitiated>
 */
class CleanupCompileDirectory implements CanHandle
{
    protected string $distPath = 'dist';

    public function handle(Event $event): void
    {
        if (!file_exists($this->distPath)) {
            mkdir($this->distPath, 0777, true);
            echo sprintf("Created dist directory at %s\n", $this->distPath);
            return;
        }

        if (!is_dir($this->distPath)) {
            unlink($this->distPath);
            mkdir($this->distPath, 0777, true);
            echo sprintf("Recreated dist directory at %s\n", $this->distPath);
            return;
        }

        $this->emptyDirectory($this->distPath);
        echo sprintf("Cleaned dist directory at %s\n", $this->distPath);
    }

    /**
     * Recursively remove directory contents while keeping the directory
     */
    protected function emptyDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
          RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }
    }
}