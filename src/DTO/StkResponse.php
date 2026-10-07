<?php

declare(strict_types=1);

namespace Jengo\Pesa\DTO;

final class StkResponse
{
    public function __construct(
        public bool $successful,
        public string $merchantRequestId,
        public string $checkoutRequestId,
        public string $responseCode,
        public string $responseDescription,
        public string $customerMessage,
        public ?string $reference = null,
        public array $raw = [],
    ) {
    }

    public function isPending(): bool
    {
        return $this->successful && $this->responseCode === '0';
    }
}
