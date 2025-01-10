#!/usr/bin/env php
<?php

require_once './vendor/autoload.php';

use PHPNomad\Events\Interfaces\EventStrategy;
use PHPNomad\Static\Application;
use PHPNomad\Static\Events\StaticCompileInitiated;
use PHPNomad\Static\Events\StaticCompileRequested;

$application = (new Application(__FILE__))
  ->cli()
  ->setConfig('docsRoot', './configs/app.json');

$application->get(EventStrategy::class)
            ->broadcast(new StaticCompileInitiated());

$application->get(EventStrategy::class)
            ->broadcast(new StaticCompileRequested());