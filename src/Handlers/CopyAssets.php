<?php

namespace PHPNomad\Static\Handlers;

use PHPNomad\Events\Interfaces\CanHandle;
use PHPNomad\Events\Interfaces\Event;
use PHPNomad\Static\Events\StaticCompileRequested;
use PHPNomad\Static\Providers\ConfigProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * @extends CanHandle<StaticCompileRequested>
 */
class CopyAssets implements CanHandle
{
    public function __construct(
      protected ConfigProvider $configProvider
    ) {}

    public function handle(Event $event): void
    {
        $outputDir = 'dist';
        $templateDir = $this->configProvider->getTemplateDirectory();
        $assetDirs = ['assets'];

        foreach ($assetDirs as $dir) {
            $sourcePath = $templateDir . '/' . $dir;
            if (!is_dir($sourcePath)) {
                continue;
            }

            $destPath = $outputDir . '/public/' . $dir;
            echo sprintf("Copying assets from %s to %s\n", $sourcePath, $destPath);

            $this->copyDirectory($sourcePath, $destPath);
        }
    }

    protected function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }

        $iterator = new RecursiveIteratorIterator(
          new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
          RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                mkdir($destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            } else {
                copy($item, $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName());
            }
        }
    }
}