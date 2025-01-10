<?php

namespace PHPNomad\Static\Initializers;

use PHPNomad\Loader\Interfaces\HasClassDefinitions;
use PHPNomad\Static\Http\Request;
use PHPNomad\Static\Http\Response;
use PHPNomad\Static\Providers\ConfigProvider;
use PHPNomad\Template\Interfaces\CanRender;
use PHPNomad\Twig\Integration\Interfaces\TwigConfigProvider;
use PHPNomad\Twig\Integration\Strategies\TwigEngine;

class CoreInitializer implements HasClassDefinitions
{
    public function getClassDefinitions() : array
    {
        return [
          Request::class => \PHPNomad\Http\Interfaces\Request::class,
          Response::class => \PHPNomad\Http\Interfaces\Response::class,
          TwigEngine::class => CanRender::class,
          ConfigProvider::class => [
            TwigConfigProvider::class
          ],
        ];
    }
}