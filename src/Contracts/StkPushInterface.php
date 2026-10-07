<?php

declare(strict_types=1);

namespace Jengo\Pesa\Contracts;

use Jengo\Pesa\DTO\StkRequest;
use Jengo\Pesa\DTO\StkResponse;
use Jengo\Pesa\DTO\TransactionQueryResponse;

interface StkPushInterface
{
    /**
     * Trigger an STK Push (Lipa Na M-Pesa Online / Prompt).
     */
    public function stkPush(StkRequest $request): StkResponse;

    /**
     * Query the status of an ongoing or completed STK push.
     */
    public function queryStkStatus(string $checkoutRequestId): TransactionQueryResponse;
}
