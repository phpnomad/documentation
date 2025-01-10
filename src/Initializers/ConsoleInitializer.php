<?php

namespace PHPNomad\Static\Initializers;

use PHPNomad\Events\Interfaces\HasListeners;
use PHPNomad\Static\Events\StaticCompileInitiated;
use PHPNomad\Static\Events\StaticCompileRequested;
use PHPNomad\Static\Handlers\CleanupCompileDirectory;
use PHPNomad\Static\Handlers\CopyAssets;
use PHPNomad\Static\Handlers\GenerateStaticSite;

class ConsoleInitializer implements HasListeners
{
    public function getListeners() : array
    {
        return [
          StaticCompileInitiated::class => CleanupCompileDirectory::class,
          StaticCompileRequested::class => [
            GenerateStaticSite::class,
            CopyAssets::class,
          ],
        ];
    }
}