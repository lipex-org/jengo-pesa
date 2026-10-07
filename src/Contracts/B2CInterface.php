<?php

declare(strict_types=1);

namespace Jengo\Pesa\Contracts;

use Jengo\Pesa\DTO\DisbursementRequest;
use Jengo\Pesa\DTO\DisbursementResponse;

interface B2CInterface
{
    /**
     * Send money / disbursement to a customer mobile phone.
     */
    public function disburse(DisbursementRequest $request): DisbursementResponse;
}
