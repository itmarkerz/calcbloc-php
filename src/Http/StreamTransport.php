<?php

declare(strict_types=1);

namespace CalcBloc\Http;

use CalcBloc\TransportException;

/**
 * Transport using PHP's HTTP stream wrapper (the fallback when ext-curl is not loaded; needs allow_url_fopen and
 * ext-openssl for HTTPS).
 */
final class StreamTransport implements Transport
{
    public function get(string $url, array $headers, float $timeout): Response
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $lines),
                'timeout' => $timeout,
                'ignore_errors' => true, // read the JSON error body of 4xx/5xx responses
                'follow_location' => 1,
                'max_redirects' => 3,
            ],
        ]);
        $error = null;
        set_error_handler(static function (int $no, string $message) use (&$error): bool {
            $error = $message;

            return true;
        });
        $http_response_header = [];
        try {
            $body = file_get_contents($url, false, $context);
        } finally {
            restore_error_handler();
        }
        // PHP 8.4+ exposes the headers through a function; earlier versions only through $http_response_header.
        $responseHeaders = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : $http_response_header;
        if ($body === false || $responseHeaders === []) {
            throw new TransportException(sprintf('Request to %s failed: %s', $url, $error ?? 'no response'));
        }
        $status = 0;
        $received = [];
        foreach ($responseHeaders as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~', $line, $m)) {
                $status = (int) $m[1];
                $received = []; // keep the final response's headers only
            } elseif (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $received[strtolower(trim($name))] = trim($value);
            }
        }

        return new Response($status, $received, $body);
    }
}
