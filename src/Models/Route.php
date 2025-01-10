<?php

namespace PHPNomad\Static\Models;

use PHPNomad\Static\Interfaces\WebController;

readonly class Route
{
    /**
     * @param string $endpoint
     * @param WebController $controller
     */
    public function __construct(
      public string $endpoint,
      public WebController $controller
    )
    {

    }
}