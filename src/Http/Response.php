<?php

namespace PHPNomad\Static\Http;

class Response implements \PHPNomad\Http\Interfaces\Response
{
    protected int $status = 200;
    protected array $headers = [];
    protected ?string $body = null;
    protected ?string $errorMessage = null;

    /** @inheritDoc */
    public function setStatus(int $code)
    {
        $this->status = $code;
        return $this;
    }

    /** @inheritDoc */
    public function getStatus(): int
    {
        return $this->status;
    }

    /** @inheritDoc */
    public function setHeader(string $name, string $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /** @inheritDoc */
    public function getHeader(string $name)
    {
        return $this->headers[$name] ?? null;
    }

    /** @inheritDoc */
    public function setBody(string $body)
    {
        $this->body = $body;
        $this->setHeader('Content-Type', 'text/html');

        return $this;
    }

    /** @inheritDoc */
    public function getBody(): string
    {
        return $this->body ?? '';
    }

    /** @inheritDoc */
    public function setJson($data)
    {
        $this->body = json_encode($data);
        $this->setHeader('Content-Type', 'application/json');
        return $this;
    }

    /** @inheritDoc */
    public function getJson(): array
    {
        return json_decode($this->body ?? '{}', true) ?? [];
    }

    /** @inheritDoc */
    public function setError(string $message, int $code = 400)
    {
        $this->errorMessage = $message;
        $this->setStatus($code);
        $this->setJson(['error' => $message]);
        return $this;
    }

    /** @inheritDoc */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /** @inheritDoc */
    public function getResponse(): object
    {
        return (object)[
            'status' => $this->status,
            'headers' => $this->headers,
            'body' => $this->body,
        ];
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}