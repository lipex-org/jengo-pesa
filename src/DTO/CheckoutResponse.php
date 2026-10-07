<?php

declare(strict_types=1);

namespace Jengo\Pesa\DTO;

final class CheckoutResponse
{
    public function __construct(
        public bool $successful,
        public string $redirectUrl,
        public string $gatewayReference,
        public ?string $reference = null,
        public array $raw = [],
    ) {
    }
}
