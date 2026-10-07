<?php

declare(strict_types=1);

namespace Jengo\Pesa\DTO;

final class DisbursementRequest
{
    public function __construct(
        public string $phone,
        public float|int $amount,
        public string $commandId = 'BusinessPayment', // BusinessPayment | SalaryPayment | PromotionPayment
        public string $remarks = 'Disbursement',
        public string $occasion = '',
        public ?string $reference = null,
        public array $metadata = [],
    ) {
        $this->phone = StkRequest::sanitizePhone($phone);
    }
}
