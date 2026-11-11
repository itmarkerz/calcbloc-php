<?php

declare(strict_types=1);

namespace CalcBloc\Tests\Support;

use CalcBloc\Http\Response;
use CalcBloc\Http\Transport;

/**
 * Records requests and replies with queued responses. No network.
 */
final class FakeTransport implements Transport
{
    /** @var list<array{url: string, headers: array<string, string>, timeout: float}> */
    public array $requests = [];

    /** @var list<Response> */
    private array $queue = [];

    /**
     * @param array<string, mixed>|string $body
     * @param array<string, string> $headers
     */
    public function queue(int $status, array|string $body, array $headers = []): self
    {
        $this->queue[] = new Response(
            $status,
            $headers + ['Content-Type' => is_string($body) ? 'text/csv; charset=utf-8' : 'application/json; charset=utf-8'],
            is_string($body) ? $body : (string) json_encode($body),
        );

        return $this;
    }

    public function get(string $url, array $headers, float $timeout): Response
    {
        $this->requests[] = ['url' => $url, 'headers' => $headers, 'timeout' => $timeout];
        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \LogicException("Unexpected request to $url");
        }

        return $next;
    }

    /**
     * @return array{url: string, headers: array<string, string>, timeout: float}
     */
    public function last(): array
    {
        $last = end($this->requests);
        if ($last === false) {
            throw new \LogicException('No request was sent.');
        }

        return $last;
    }
}
