<?php

namespace PHPNomad\Static\Http;

use PHPNomad\Auth\Interfaces\User;
use PHPNomad\Http\Interfaces\Request as CoreRequest;

class Request implements CoreRequest
{
    protected array $headers = [];
    protected array $params = [];

    public function __construct()
    {
        $this->headers = $this->getAllHeaders();
        $this->params = $_REQUEST;
    }

    /** @inheritDoc */
    public function getUser(): ?User
    {
        return null;
    }

    /** @inheritDoc */
    public function getHeader(string $name)
    {
        return $this->headers[$name] ?? null;
    }

    /** @inheritDoc */
    public function setHeader(string $name, $value): void
    {
        $this->headers[$name] = $value;
    }

    /** @inheritDoc */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /** @inheritDoc */
    public function getParam(string $name)
    {
        return $this->getParamValue($name, $this->params);
    }

    /** @inheritDoc */
    public function hasParam(string $name): bool
    {
        return $this->getParamValue($name, $this->params) !== null;
    }

    /** @inheritDoc */
    public function setParam(string $name, $value): void
    {
        $this->params[$name] = $value;
    }

    /** @inheritDoc */
    public function removeParam(string $name): void
    {
        unset($this->params[$name]);
    }

    /** @inheritDoc */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Retrieve all HTTP headers.
     *
     * @return array<string, string>
     */
    protected function getAllHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            }
        }
        return $headers;
    }

    /**
     * Retrieve a parameter value, supporting dot notation for nested parameters.
     *
     * @param string $name Parameter name, supporting dot notation.
     * @param array $params Array of parameters to search in.
     * @return mixed|null
     */
    protected function getParamValue(string $name, array $params)
    {
        $keys = explode('.', $name);
        foreach ($keys as $key) {
            if (!isset($params[$key])) {
                return null;
            }
            $params = $params[$key];
        }
        return $params;
    }
}
