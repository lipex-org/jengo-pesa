<?php

declare(strict_types=1);

namespace Jengo\Pesa\DTO;

final class StkRequest
{
    public function __construct(
        public string $phone,
        public float|int $amount,
        public string $accountReference,
        public string $transactionDesc = 'Payment',
        public ?string $callbackUrl = null,
        public ?string $reference = null,
        public array $metadata = [],
    ) {
        $this->phone = self::sanitizePhone($phone);
    }

    /**
     * Sanitize and format Kenyan/International MSISDN numbers.
     * Converts 0712345678, +254712345678, 254712345678 -> 254712345678
     */
    public static function sanitizePhone(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone) ?? '';

        if (str_starts_with($cleaned, '0')) {
            return '254' . substr($cleaned, 1);
        }

        if (str_starts_with($cleaned, '7') || str_starts_with($cleaned, '1')) {
            return '254' . $cleaned;
        }

        return $cleaned;
    }
}
