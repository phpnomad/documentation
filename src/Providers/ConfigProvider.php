<?php

namespace PHPNomad\Static\Providers;

use PHPNomad\Config\Interfaces\ConfigStrategy;
use PHPNomad\Twig\Integration\Interfaces\TwigConfigProvider;

class ConfigProvider implements TwigConfigProvider
{
    public function __construct(protected ConfigStrategy $config)
    {

    }

    public function getTemplateDirectory() : string
    {
        return $this->config->get('app.templateRoot', 'public');
    }

    public function getDocsRootDirectory(): string
    {
        return $this->config->get('app.docsRoot', 'public/docs');
    }
}