<?php

declare(strict_types=1);

namespace Jengo\Pesa\Events;

class PaymentFailed extends BasePaymentEvent
{
    public function __construct(
        \Jengo\Pesa\Entities\PesaTransaction $transaction,
        public string $reason = '',
        array $metadata = []
    ) {
        parent::__construct($transaction, $metadata);
    }
}
