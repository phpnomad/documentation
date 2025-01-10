<?php

namespace PHPNomad\Static\Events;

use PHPNomad\Events\Interfaces\Event;
use PHPNomad\Http\Interfaces\Response;

class RequestInitiated implements Event
{
    protected Response $response;

    public function __construct(public readonly string $uri)
    {

    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * @param Response $response
     * @return $this
     */
    public function setResponse(Response $response)
    {
        $this->response = $response;

        return $this;
    }

    public static function getId(): string
    {
        return 'requestInitiated';
    }
}