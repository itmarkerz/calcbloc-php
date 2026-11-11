<?php

declare(strict_types=1);

namespace CalcBloc\Http;

use CalcBloc\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Adapter for any PSR-18 HTTP client (Guzzle 7, Symfony HttpClient's Psr18Client, php-http clients, ...).
 *
 * The PSR interfaces are not a dependency of this package: install your client as usual and pass it here with a
 * PSR-17 request factory. The timeout is the client's own setting; configure it on the client.
 *
 *     $client = new CalcBloc\Client(transport: new Psr18Transport($psr18Client, $psr17Factory)); // key from CALCBLOC_API_KEY
 */
final class Psr18Transport implements Transport
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
    ) {
    }

    public function get(string $url, array $headers, float $timeout): Response
    {
        $request = $this->requestFactory->createRequest('GET', $url);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException(sprintf('Request to %s failed: %s', $url, $e->getMessage()), $e);
        }
        $received = [];
        foreach ($response->getHeaders() as $name => $values) {
            $received[(string) $name] = implode(', ', $values);
        }

        return new Response($response->getStatusCode(), $received, (string) $response->getBody());
    }
}
