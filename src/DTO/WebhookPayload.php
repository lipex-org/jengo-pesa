<?php

declare(strict_types=1);

namespace Jengo\Pesa\DTO;

final class WebhookPayload
{
    public function __construct(
        public string $gateway,
        public string $event, // payment.success, payment.failed, payment.reversed, etc.
        public string $gatewayReference,
        public ?string $receiptNumber = null,
        public ?float $amount = null,
        public ?string $currency = 'KES',
        public ?string $phone = null,
        public ?string $payerName = null,
        public ?string $payerEmail = null,
        public ?string $failureReason = null,
        public ?string $reference = null,
        public array $raw = [],
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->event === 'payment.success';
    }
}
