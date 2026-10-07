<?php

declare(strict_types=1);

namespace Jengo\Pesa\Models;

use CodeIgniter\Model;
use Jengo\Pesa\Entities\PesaTransaction;

class PesaTransactionModel extends Model
{
    protected $table = 'pesa_transactions';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = false;
    protected $returnType = PesaTransaction::class;
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'id',
        'reference',
        'gateway',
        'gateway_reference',
        'receipt_number',
        'type',
        'status',
        'amount',
        'currency',
        'payer_phone',
        'payer_name',
        'payer_email',
        'failure_reason',
        'raw_request',
        'raw_response',
        'completed_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Find a transaction by gateway and its unique gateway reference.
     */
    public function findByGatewayReference(string $gateway, string $gatewayReference): ?PesaTransaction
    {
        return $this->where('gateway', $gateway)
            ->where('gateway_reference', $gatewayReference)
            ->first();
    }

    /**
     * Find a transaction by internal reference.
     */
    public function findByReference(string $reference): ?PesaTransaction
    {
        return $this->where('reference', $reference)->first();
    }
}
