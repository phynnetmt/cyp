<?php

namespace Cypher\RuntimeEngine\Http;

class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        public readonly array $headers = [],
    ) {}

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }
        echo $this->body;
    }

    public function toArray(): array
    {
        return [
            'status_code' => $this->statusCode,
            'body' => $this->body,
            'headers' => $this->headers,
        ];
    }
}
