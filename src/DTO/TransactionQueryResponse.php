<?php

declare(strict_types=1);

namespace Jengo\Pesa\DTO;

final class TransactionQueryResponse
{
    public function __construct(
        public bool $successful,
        public string $status, // pending, successful, failed, reversed
        public ?string $receiptNumber = null,
        public ?float $amount = null,
        public ?string $phone = null,
        public ?string $resultCode = null,
        public ?string $resultDesc = null,
        public array $raw = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->successful && $this->status === 'successful';
    }
}
