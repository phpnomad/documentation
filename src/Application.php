<?php

namespace PHPNomad\Static;

use PHPNomad\Di\Container;
use PHPNomad\Di\Exceptions\DiException;
use PHPNomad\Loader\Bootstrapper;
use PHPNomad\Loader\Exceptions\LoaderException;
use PHPNomad\Static\Initializers\ConfigInitializer;
use PHPNomad\Static\Initializers\ConsoleInitializer;
use PHPNomad\Static\Initializers\CoreInitializer;
use PHPNomad\Static\Initializers\RouterInitializer;
use PHPNomad\Symfony\Component\EventDispatcherIntegration\Initializer as SymfonyEventInitializer;

final class Application
{
    protected string    $file;
    protected array     $configs = [];
    protected Container $container;

    public function __construct(string $file)
    {
        $this->file = $file;
        $this->container = new Container();
    }

    /**
     * @param $key
     * @param $path
     *
     * @return $this
     */
    public function setConfig($key, $path) : Application
    {
        $this->configs[$key] = $path;

        return $this;
    }

    /**
     * @return Application
     * @throws LoaderException
     * @return $this
     */
    public function init() : Application
    {
        (new Bootstrapper(
          $this->container,
          new ConfigInitializer($this->configs),
          new SymfonyEventInitializer(),
          new CoreInitializer()
        ))->load();

        return $this;
    }

    public function cli(): Application
    {
        $this->init();

        (new Bootstrapper(
          $this->container,
          new ConsoleInitializer(),
          new RouterInitializer()

        ))->load();

        return $this;
    }

    public function dev() : Application
    {
        $this->init();

        (new Bootstrapper(
          $this->container,
          new RouterInitializer()
        ))->load();

        return $this;
    }

    /**
     * Get an instance of the class, with dependencies autowired
     *
     * @template T of object
     * @param class-string<T> $abstract
     *
     * @return T
     * @throws DiException
     */
    public function get(string $abstract)
    {
        return $this->container->get($abstract);
    }
}