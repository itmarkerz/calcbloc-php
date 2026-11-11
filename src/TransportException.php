<?php

declare(strict_types=1);

namespace CalcBloc;

/**
 * The request did not get an HTTP response (DNS failure, refused connection, timeout, TLS error).
 * getErrorCode() is `transport_error`; getStatus() is null.
 */
final class TransportException extends CalcBlocException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 'transport_error', null, null, $previous);
    }
}
