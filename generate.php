#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

use PHPNomad\Events\Interfaces\EventStrategy;
use PHPNomad\Static\Application;
use PHPNomad\Static\Events\StaticCompileRequested;

(new Application(__FILE__))
  ->cli()
  ->setConfig('docsRoot','./configs/app.json')
  ->get(EventStrategy::class)
  ->broadcast(new StaticCompileRequested());