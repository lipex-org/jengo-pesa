<?php

declare(strict_types=1);

use Jengo\Pesa\Contracts\GatewayInterface;
use Jengo\Pesa\DTO\StkRequest;
use Jengo\Pesa\Pesa;

if (! function_exists('pesa')) {
    /**
     * Helper to access Pesa gateway driver.
     */
    function pesa(?string $gateway = null): GatewayInterface
    {
        return Pesa::gateway($gateway);
    }
}

if (! function_exists('pesa_format_phone')) {
    /**
     * Helper to sanitize and format MSISDN phone numbers for payment gateways (e.g. 2547XXXXXXXX).
     */
    function pesa_format_phone(string $phone): string
    {
        return StkRequest::sanitizePhone($phone);
    }
}
