<?php

use PHPNomad\Events\Interfaces\EventStrategy;
use PHPNomad\Static\Application;
use PHPNomad\Static\Events\RequestInitiated;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;

require_once './vendor/autoload.php';

$whoops = new Run();
$whoops->pushHandler(new PrettyPageHandler());
$whoops->register();

$event = new RequestInitiated(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

(new Application(__FILE__))
  ->setConfig('app','./configs/app.json')
  ->dev()
  ->get(EventStrategy::class)
  ->broadcast($event);

$response = $event->getResponse();

http_response_code($response->getStatus());

// Send headers
foreach ($response->getHeaders() as $name => $value) {
    header("$name: $value");
}

// Output the response body
echo $event->getResponse()->getBody();