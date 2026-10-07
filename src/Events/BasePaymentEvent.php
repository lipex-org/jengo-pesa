<?php

declare(strict_types=1);

namespace Jengo\Pesa\Events;

use Jengo\Pesa\Entities\PesaTransaction;

abstract class BasePaymentEvent
{
    public function __construct(
        public PesaTransaction $transaction,
        public array $metadata = [],
    ) {
    }
}
