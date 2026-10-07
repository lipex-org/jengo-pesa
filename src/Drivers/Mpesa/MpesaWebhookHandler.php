<?php

declare(strict_types=1);

namespace Jengo\Pesa\Drivers\Mpesa;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Pesa\Contracts\WebhookHandlerInterface;
use Jengo\Pesa\DTO\WebhookPayload;
use Jengo\Pesa\Exceptions\WebhookVerificationException;

class MpesaWebhookHandler implements WebhookHandlerInterface
{
    public function handle(IncomingRequest $request): WebhookPayload
    {
        $rawBody = (string) $request->getBody();
        $data = json_decode($rawBody, true);

        if (! is_array($data)) {
            throw new WebhookVerificationException('Invalid JSON payload received from M-Pesa webhook');
        }

        // 1. Check if payload is an STK Push Callback: Body -> stkCallback
        if (isset($data['Body']['stkCallback'])) {
            return $this->parseStkCallback($data['Body']['stkCallback'], $data);
        }

        // 2. Check if payload is a C2B Confirmation / Validation request (TransID, BillRefNumber, etc.)
        if (isset($data['TransID']) && isset($data['TransAmount'])) {
            return $this->parseC2BCallback($data);
        }

        // 3. Check if payload is a B2C Result callback (Result -> ResultType, ResultCode, etc.)
        if (isset($data['Result'])) {
            return $this->parseB2CCallback($data['Result'], $data);
        }

        throw new WebhookVerificationException('Unrecognized M-Pesa webhook payload structure');
    }

    protected function parseStkCallback(array $stk, array $raw): WebhookPayload
    {
        $checkoutRequestId = $stk['CheckoutRequestID'] ?? '';
        $resultCode = (int) ($stk['ResultCode'] ?? 1);
        $resultDesc = (string) ($stk['ResultDesc'] ?? '');

        $isSuccess = ($resultCode === 0);
        $event = $isSuccess ? 'payment.success' : 'payment.failed';

        $amount = null;
        $receipt = null;
        $phone = null;

        if ($isSuccess && isset($stk['CallbackMetadata']['Item'])) {
            foreach ($stk['CallbackMetadata']['Item'] as $item) {
                $name = $item['Name'] ?? '';
                $val = $item['Value'] ?? null;

                if ($name === 'Amount') {
                    $amount = (float) $val;
                } elseif ($name === 'MpesaReceiptNumber') {
                    $receipt = (string) $val;
                } elseif ($name === 'PhoneNumber') {
                    $phone = (string) $val;
                }
            }
        }

        return new WebhookPayload(
            gateway: 'mpesa',
            event: $event,
            gatewayReference: $checkoutRequestId,
            receiptNumber: $receipt,
            amount: $amount,
            currency: 'KES',
            phone: $phone,
            failureReason: $isSuccess ? null : $resultDesc,
            raw: $raw
        );
    }

    protected function parseC2BCallback(array $data): WebhookPayload
    {
        $transId = (string) ($data['TransID'] ?? '');
        $amount = (float) ($data['TransAmount'] ?? 0.0);
        $phone = (string) ($data['MSISDN'] ?? '');
        $reference = (string) ($data['BillRefNumber'] ?? '');
        $payerName = trim(($data['FirstName'] ?? '') . ' ' . ($data['MiddleName'] ?? '') . ' ' . ($data['LastName'] ?? ''));

        return new WebhookPayload(
            gateway: 'mpesa',
            event: 'payment.success',
            gatewayReference: $transId,
            receiptNumber: $transId,
            amount: $amount,
            currency: 'KES',
            phone: $phone,
            payerName: ! empty($payerName) ? $payerName : null,
            reference: ! empty($reference) ? $reference : null,
            raw: $data
        );
    }

    protected function parseB2CCallback(array $result, array $raw): WebhookPayload
    {
        $convId = (string) ($result['ConversationID'] ?? '');
        $origConvId = (string) ($result['OriginatorConversationID'] ?? '');
        $resultCode = (int) ($result['ResultCode'] ?? 1);
        $resultDesc = (string) ($result['ResultDesc'] ?? '');
        $transId = (string) ($result['TransactionID'] ?? '');

        $isSuccess = ($resultCode === 0);
        $event = $isSuccess ? 'payment.success' : 'payment.failed';

        $amount = null;
        $phone = null;
        $name = null;

        if ($isSuccess && isset($result['ResultParameters']['ResultParameter'])) {
            foreach ($result['ResultParameters']['ResultParameter'] as $param) {
                $k = $param['Key'] ?? '';
                $v = $param['Value'] ?? null;

                if ($k === 'TransactionAmount') {
                    $amount = (float) $v;
                } elseif ($k === 'ReceiverPartyPublicName') {
                    $name = (string) $v;
                }
            }
        }

        return new WebhookPayload(
            gateway: 'mpesa',
            event: $event,
            gatewayReference: $convId ?: $origConvId,
            receiptNumber: $transId ?: null,
            amount: $amount,
            currency: 'KES',
            phone: $phone,
            payerName: $name,
            failureReason: $isSuccess ? null : $resultDesc,
            raw: $raw
        );
    }

    public function createAcknowledgmentResponse(WebhookPayload $payload): ResponseInterface
    {
        // M-Pesa expects standard 200 JSON with ResultCode 0 for confirmation/validation
        return Services::response()
            ->setStatusCode(200)
            ->setJSON([
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted',
            ]);
    }
}
