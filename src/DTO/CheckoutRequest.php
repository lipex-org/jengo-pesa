<?php

declare(strict_types=1);

namespace Jengo\Pesa\DTO;

final class CheckoutRequest
{
    public function __construct(
        public float|int $amount,
        public string $currency,
        public string $description,
        public string $callbackUrl,
        public ?string $reference = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $cancelUrl = null,
        public array $metadata = [],
    ) {
    }
}
