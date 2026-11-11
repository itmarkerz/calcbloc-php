<?php

declare(strict_types=1);

namespace CalcBloc\Http;

use CalcBloc\TransportException;

/**
 * Sends one HTTP GET request. Implement it to route the client through your own HTTP stack, or wrap any PSR-18
 * client with Psr18Transport.
 */
interface Transport
{
    /**
     * @param array<string, string> $headers Request headers.
     *
     * @throws TransportException When no HTTP response was received.
     */
    public function get(string $url, array $headers, float $timeout): Response;
}
