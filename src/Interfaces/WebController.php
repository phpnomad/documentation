<?php

namespace PHPNomad\Static\Interfaces;

use PHPNomad\Http\Interfaces\Request;
use PHPNomad\Http\Interfaces\Response;
use PHPNomad\Static\Models\Route;

interface WebController
{
    /**
     * Modifies the provided response, adding the body.
     *
     * @param Route   $route
     * @param Request $request
     *
     * @return Response
     */
    public function content(Route $route, Request $request): Response;
}