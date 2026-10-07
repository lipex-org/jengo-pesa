<?php

declare(strict_types=1);

namespace Jengo\Pesa\Exceptions;

class GatewayRequestException extends PesaException
{
    public function __construct(
        string $message,
        public int $statusCode = 0,
        public array $responseBody = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }
}
