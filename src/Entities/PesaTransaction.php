<?php

declare(strict_types=1);

namespace Jengo\Pesa\Entities;

use CodeIgniter\Entity\Entity;

/**
 * @property string      $id
 * @property string|null $reference
 * @property string      $gateway
 * @property string      $gateway_reference
 * @property string|null $receipt_number
 * @property string      $type
 * @property string      $status
 * @property float       $amount
 * @property string      $currency
 * @property string|null $payer_phone
 * @property string|null $payer_name
 * @property string|null $payer_email
 * @property string|null $failure_reason
 * @property array|null  $raw_request
 * @property array|null  $raw_response
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property string|null $completed_at
 */
class PesaTransaction extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'completed_at'];

    protected $casts = [
        'amount'       => 'float',
        'raw_request'  => 'json-array',
        'raw_response' => 'json-array',
    ];

    public function isSuccessful(): bool
    {
        return $this->attributes['status'] === 'successful';
    }

    public function isPending(): bool
    {
        return $this->attributes['status'] === 'pending';
    }

    public function isFailed(): bool
    {
        return in_array($this->attributes['status'], ['failed', 'cancelled'], true);
    }
}
