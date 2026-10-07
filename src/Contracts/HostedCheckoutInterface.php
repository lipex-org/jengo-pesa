<?php

declare(strict_types=1);

namespace Jengo\Pesa\Contracts;

use Jengo\Pesa\DTO\CheckoutRequest;
use Jengo\Pesa\DTO\CheckoutResponse;

interface HostedCheckoutInterface
{
    /**
     * Create a hosted checkout session or redirect URL.
     */
    public function checkout(CheckoutRequest $request): CheckoutResponse;
}
