<?php

declare(strict_types=1);

namespace Jengo\Pesa\Drivers;

use CodeIgniter\HTTP\CURLRequest;
use Config\Services;
use Jengo\Pesa\Config\Pesa as PesaConfig;
use Jengo\Pesa\Contracts\GatewayInterface;
use Jengo\Pesa\Entities\PesaTransaction;
use Jengo\Pesa\Events\PaymentInitiated;
use Jengo\Pesa\Models\PesaTransactionModel;

abstract class AbstractGateway implements GatewayInterface
{
    protected array $config;
    protected PesaConfig $pesaConfig;
    protected ?PesaTransactionModel $transactionModel = null;
    protected ?CURLRequest $httpClient = null;

    public function __construct(array $config = [], ?PesaConfig $pesaConfig = null)
    {
        $this->pesaConfig = $pesaConfig ?? config('Pesa') ?? new PesaConfig();
        $this->config = $config;

        if ($this->pesaConfig->enableLedger) {
            $this->transactionModel = new PesaTransactionModel();
        }
    }

    /**
     * Get or initialize the HTTP client.
     */
    protected function getHttpClient(array $options = []): CURLRequest
    {
        if ($this->httpClient === null) {
            $this->httpClient = Services::curlrequest($options);
        }
        return $this->httpClient;
    }

    /**
     * Set a custom HTTP client (useful for unit testing and mocking).
     */
    public function setHttpClient(CURLRequest $client): static
    {
        $this->httpClient = $client;
        return $this;
    }

    /**
     * Record a pending payment in the ledger.
     */
    protected function recordPendingTransaction(
        string $gatewayReference,
        string $type,
        float|int $amount,
        string $currency = 'KES',
        ?string $reference = null,
        ?string $phone = null,
        ?string $name = null,
        ?string $email = null,
        array $rawRequest = []
    ): ?PesaTransaction {
        if (! $this->transactionModel) {
            return null;
        }

        $id = $this->generateUuid();

        $data = [
            'id'                => $id,
            'reference'         => $reference,
            'gateway'           => $this->getName(),
            'gateway_reference' => $gatewayReference,
            'type'              => $type,
            'status'            => 'pending',
            'amount'            => $amount,
            'currency'          => $currency,
            'payer_phone'       => $phone,
            'payer_name'        => $name,
            'payer_email'       => $email,
            'raw_request'       => $rawRequest,
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ];

        $transaction = new PesaTransaction($data);
        $this->transactionModel->insert($transaction);

        Events::trigger('pesa.payment_initiated', new PaymentInitiated($transaction));

        return $transaction;
    }

    /**
     * Simple UUIDv4 generation helper.
     */
    protected function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
