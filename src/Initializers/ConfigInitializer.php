<?php

namespace PHPNomad\Static\Initializers;

use PHPNomad\ArrayJsonConfig\Strategies\ArrayConfigStrategy;
use PHPNomad\Config\Interfaces\ConfigFileLoaderStrategy;
use PHPNomad\Config\Interfaces\ConfigStrategy;
use PHPNomad\Config\Services\ConfigService;
use PHPNomad\ArrayJsonConfig\Strategies\JsonFileLoader;
use PHPNomad\Di\Interfaces\CanSetContainer;
use PHPNomad\Di\Traits\HasSettableContainer;
use PHPNomad\Loader\Interfaces\HasClassDefinitions;
use PHPNomad\Loader\Interfaces\Loadable;

class ConfigInitializer implements HasClassDefinitions, Loadable, CanSetContainer
{
    use HasSettableContainer;

    /**
     * @var string[]
     */
    protected array $configs;

    public function __construct(array $configs)
    {
        $this->configs = $configs;
    }

    public function getClassDefinitions(): array
    {
        return [
          JsonFileLoader::class => ConfigFileLoaderStrategy::class,
          ArrayConfigStrategy::class => ConfigStrategy::class
        ];
    }

    public function load(): void
    {
        foreach ($this->configs as $key => $config) {
            $this->container->get(ConfigService::class)->registerConfig($key, $config);
        }
    }
}