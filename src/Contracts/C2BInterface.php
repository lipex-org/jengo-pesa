<?php

declare(strict_types=1);

namespace Jengo\Pesa\Contracts;

interface C2BInterface
{
    /**
     * Register C2B Validation and Confirmation URLs for Paybill or Buy Goods.
     *
     * @param string $shortCode Paybill or Till number
     * @param string $responseType 'Completed' or 'Cancelled'
     * @param string $validationUrl URL called by M-Pesa to validate incoming transaction
     * @param string $confirmationUrl URL called by M-Pesa once transaction has completed
     */
    public function registerC2BUrls(
        string $shortCode,
        string $responseType,
        string $validationUrl,
        string $confirmationUrl
    ): array;
}
