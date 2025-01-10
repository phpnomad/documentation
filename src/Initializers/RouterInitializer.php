<?php

namespace PHPNomad\Static\Initializers;

use PHPNomad\Events\Interfaces\HasListeners;
use PHPNomad\Static\Events\RequestInitiated;
use PHPNomad\Static\Handlers\DispatchRequest;

class RouterInitializer implements HasListeners
{
    public function getListeners() : array
    {
        return [
          RequestInitiated::class => DispatchRequest::class,
        ];
    }
}