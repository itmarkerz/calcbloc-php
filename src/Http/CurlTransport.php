<?php

declare(strict_types=1);

namespace CalcBloc\Http;

use CalcBloc\TransportException;

/**
 * Transport using ext-curl (the default when the extension is loaded).
 */
final class CurlTransport implements Transport
{
    public function get(string $url, array $headers, float $timeout): Response
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new TransportException('Could not initialise cURL.');
        }
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $received = [];
        curl_setopt_array($handle, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_CONNECTTIMEOUT_MS => (int) ceil(min($timeout, 10.0) * 1000),
            CURLOPT_TIMEOUT_MS => (int) ceil($timeout * 1000),
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$received): int {
                if (preg_match('~^HTTP/\S+\s+\d+~', $line)) {
                    $received = []; // a redirect or 100 Continue: keep the final response's headers only
                } elseif (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $received[strtolower(trim($name))] = trim($value);
                }

                return strlen($line);
            },
        ]);
        $body = curl_exec($handle);
        if ($body === false || !is_string($body)) {
            $message = curl_error($handle);
            throw new TransportException(sprintf('Request to %s failed: %s', $url, $message !== '' ? $message : 'no response'));
        }
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        return new Response($status, $received, $body);
    }
}
