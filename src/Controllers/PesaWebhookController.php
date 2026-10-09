<?php

declare(strict_types=1);

namespace Jengo\Pesa\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Pesa\Config\Pesa as PesaConfig;
use Jengo\Pesa\DTO\WebhookPayload;
use Jengo\Pesa\Entities\PesaTransaction;
use Jengo\Pesa\Events\PaymentFailed;
use Jengo\Pesa\Events\PaymentReversed;
use Jengo\Pesa\Events\PaymentSucceeded;
use Jengo\Pesa\Models\PesaTransactionModel;
use Jengo\Pesa\Pesa;

class PesaWebhookController extends Controller
{
    /**
     * Handle incoming payment webhooks and IPN notifications.
     */
    public function handle(string $gateway): ResponseInterface
    {
        /** @var IncomingRequest $request */
        $request = $this->request;

        $gatewayDriver = Pesa::gateway($gateway);
        $handler = $gatewayDriver->getWebhookHandler();

        try {
            $payload = $handler->handle($request);
        } catch (\Throwable $e) {
            log_message('error', "[Pesa] Webhook processing failed for [{$gateway}]: " . $e->getMessage());
            return Services::response()
                ->setStatusCode(400)
                ->setJSON([
                    'error'   => true,
                    'message' => $e->getMessage(),
                ]);
        }

        // Process transaction state & ledger updates asynchronously via jengo/queues if available
        if (function_exists('defer')) {
            defer(function () use ($gateway, $payload) {
                $this->processTransactionState($gateway, $payload);
            });
        } else {
            $this->processTransactionState($gateway, $payload);
        }

        return $handler->createAcknowledgmentResponse($payload);
    }

    /**
     * Process transaction ledger state and trigger system events.
     */
    protected function processTransactionState(string $gateway, WebhookPayload $payload): void
    {
        $config = config('Pesa') ?? new PesaConfig();
        if (! $config->enableLedger) {
            return;
        }

        $model = new PesaTransactionModel();

        // 1. Find existing pending transaction by gateway reference or internal reference
        $transaction = $model->findByGatewayReference($gateway, $payload->gatewayReference);

        if (! $transaction && $payload->reference) {
            $transaction = $model->findByReference($payload->reference);
        }

        $now = date('Y-m-d H:i:s');

        // 2. If no record found (e.g. C2B Paybill direct payment without prior STK prompt), create a completed record
        if (! $transaction) {
            $transaction = new PesaTransaction([
                'id'                => $this->generateUuid(),
                'reference'         => $payload->reference,
                'gateway'           => $gateway,
                'gateway_reference' => $payload->gatewayReference,
                'receipt_number'    => $payload->receiptNumber,
                'type'              => 'c2b_payment',
                'status'            => $payload->isSuccess() ? 'successful' : 'failed',
                'amount'            => $payload->amount ?? 0.00,
                'currency'          => $payload->currency ?? 'KES',
                'payer_phone'       => $payload->phone,
                'payer_name'        => $payload->payerName,
                'payer_email'       => $payload->payerEmail,
                'failure_reason'    => $payload->failureReason,
                'raw_response'      => $payload->raw,
                'completed_at'      => $payload->isSuccess() ? $now : null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);

            $model->insert($transaction);
        } else {
            // 3. Prevent duplicate processing (Idempotency)
            if ($transaction->status === 'successful' && $payload->isSuccess()) {
                return;
            }

            $updateData = [
                'updated_at'   => $now,
                'raw_response' => $payload->raw,
            ];

            if ($payload->receiptNumber) {
                $updateData['receipt_number'] = $payload->receiptNumber;
            }

            if ($payload->amount !== null && $payload->amount > 0) {
                $updateData['amount'] = $payload->amount;
            }

            if ($payload->phone) {
                $updateData['payer_phone'] = $payload->phone;
            }

            if ($payload->payerName) {
                $updateData['payer_name'] = $payload->payerName;
            }

            if ($payload->payerEmail) {
                $updateData['payer_email'] = $payload->payerEmail;
            }

            if ($payload->isSuccess()) {
                $updateData['status'] = 'successful';
                $updateData['completed_at'] = $now;
                $updateData['failure_reason'] = null;
            } elseif ($payload->event === 'payment.reversed') {
                $updateData['status'] = 'reversed';
            } else {
                $updateData['status'] = 'failed';
                $updateData['failure_reason'] = $payload->failureReason ?? 'Transaction failed or was cancelled';
            }

            $model->update($transaction->id, $updateData);
            $transaction->fill($updateData);
        }

        // 4. Dispatch typed CodeIgniter events
        if ($payload->isSuccess()) {
            Events::trigger('pesa.payment_succeeded', new PaymentSucceeded($transaction, $payload->raw));
        } elseif ($payload->event === 'payment.reversed') {
            Events::trigger('pesa.payment_reversed', new PaymentReversed($transaction, $payload->raw));
        } else {
            Events::trigger('pesa.payment_failed', new PaymentFailed($transaction, $payload->failureReason ?? '', $payload->raw));
        }
    }

    protected function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
