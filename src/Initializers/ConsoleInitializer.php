<?php

namespace PHPNomad\Static\Initializers;

use PHPNomad\Events\Interfaces\HasListeners;
use PHPNomad\Static\Events\StaticCompileRequested;
use PHPNomad\Static\Handlers\GenerateStaticSite;

class ConsoleInitializer implements HasListeners
{
    public function getListeners() : array
    {
        return [
          StaticCompileRequested::class => GenerateStaticSite::class,
        ];
    }
}