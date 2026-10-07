<?php

declare(strict_types=1);

namespace Jengo\Pesa\Drivers\Fake;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Pesa\Contracts\B2CInterface;
use Jengo\Pesa\Contracts\HostedCheckoutInterface;
use Jengo\Pesa\Contracts\StkPushInterface;
use Jengo\Pesa\Contracts\WebhookHandlerInterface;
use Jengo\Pesa\Drivers\AbstractGateway;
use Jengo\Pesa\DTO\CheckoutRequest;
use Jengo\Pesa\DTO\CheckoutResponse;
use Jengo\Pesa\DTO\DisbursementRequest;
use Jengo\Pesa\DTO\DisbursementResponse;
use Jengo\Pesa\DTO\StkRequest;
use Jengo\Pesa\DTO\StkResponse;
use Jengo\Pesa\DTO\TransactionQueryResponse;
use Jengo\Pesa\DTO\WebhookPayload;

class FakeGateway extends AbstractGateway implements StkPushInterface, HostedCheckoutInterface, B2CInterface, WebhookHandlerInterface
{
    public function getName(): string
    {
        return 'fake';
    }

    public function getWebhookHandler(): WebhookHandlerInterface
    {
        return $this;
    }

    public function stkPush(StkRequest $request): StkResponse
    {
        $checkoutId = 'ws_FAKE_' . uniqid();

        $this->recordPendingTransaction(
            gatewayReference: $checkoutId,
            type: 'stk_push',
            amount: $request->amount,
            currency: 'KES',
            reference: $request->reference ?? $request->accountReference,
            phone: $request->phone,
            rawRequest: ['phone' => $request->phone, 'amount' => $request->amount]
        );

        return new StkResponse(
            successful: true,
            merchantRequestId: 'MR_FAKE_' . uniqid(),
            checkoutRequestId: $checkoutId,
            responseCode: '0',
            responseDescription: 'Success. Request accepted for processing',
            customerMessage: 'Success. Request accepted for processing',
            reference: $request->reference ?? $request->accountReference,
            raw: ['status' => 'mock_success']
        );
    }

    public function queryStkStatus(string $checkoutRequestId): TransactionQueryResponse
    {
        return new TransactionQueryResponse(
            successful: true,
            status: 'successful',
            receiptNumber: 'RC_FAKE_' . strtoupper(substr(md5($checkoutRequestId), 0, 8)),
            resultCode: '0',
            resultDesc: 'The service request is processed successfully.'
        );
    }

    public function checkout(CheckoutRequest $request): CheckoutResponse
    {
        $gatewayRef = 'chk_fake_' . uniqid();
        $merchantRef = $request->reference ?? ('ORD-' . uniqid());

        $this->recordPendingTransaction(
            gatewayReference: $gatewayRef,
            type: 'hosted_checkout',
            amount: $request->amount,
            currency: $request->currency,
            reference: $merchantRef,
            phone: $request->phone,
            email: $request->email
        );

        return new CheckoutResponse(
            successful: true,
            redirectUrl: 'https://checkout.fake-gateway.local/pay/' . $gatewayRef,
            gatewayReference: $gatewayRef,
            reference: $merchantRef
        );
    }

    public function disburse(DisbursementRequest $request): DisbursementResponse
    {
        $convId = 'B2C_FAKE_' . uniqid();

        $this->recordPendingTransaction(
            gatewayReference: $convId,
            type: 'b2c_disbursement',
            amount: $request->amount,
            currency: 'KES',
            reference: $request->reference,
            phone: $request->phone
        );

        return new DisbursementResponse(
            successful: true,
            originatorConversationId: 'ORIG_FAKE_' . uniqid(),
            conversationId: $convId,
            responseCode: '0',
            responseDescription: 'Accept the service request successfully.',
            reference: $request->reference
        );
    }

    public function handle(IncomingRequest $request): WebhookPayload
    {
        $ref = (string) ($request->getGet('ref') ?? $request->getPost('ref') ?? 'ws_FAKE_TEST');
        $receipt = 'RC_FAKE_' . strtoupper(substr(md5($ref), 0, 8));

        return new WebhookPayload(
            gateway: 'fake',
            event: 'payment.success',
            gatewayReference: $ref,
            receiptNumber: $receipt,
            amount: 100.0,
            currency: 'KES',
            phone: '254700000000',
            payerName: 'John Doe',
            raw: ['status' => 'mock_callback']
        );
    }

    public function createAcknowledgmentResponse(WebhookPayload $payload): ResponseInterface
    {
        return Services::response()->setStatusCode(200)->setJSON(['success' => true]);
    }
}
