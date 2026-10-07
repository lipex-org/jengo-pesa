<?php

declare(strict_types=1);

namespace Jengo\Pesa\Drivers\Pesapal;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Pesa\Contracts\WebhookHandlerInterface;
use Jengo\Pesa\DTO\WebhookPayload;
use Jengo\Pesa\Exceptions\WebhookVerificationException;

class PesapalWebhookHandler implements WebhookHandlerInterface
{
    public function handle(IncomingRequest $request): WebhookPayload
    {
        $trackingId = (string) ($request->getGet('OrderTrackingId') ?? $request->getPost('OrderTrackingId') ?? '');
        $merchantRef = (string) ($request->getGet('OrderMerchantReference') ?? $request->getPost('OrderMerchantReference') ?? '');
        $notificationType = (string) ($request->getGet('OrderNotificationType') ?? $request->getPost('OrderNotificationType') ?? '');

        if (empty($trackingId)) {
            throw new WebhookVerificationException('Missing OrderTrackingId in Pesapal IPN notification');
        }

        // Pesapal IPN hit
        return new WebhookPayload(
            gateway: 'pesapal',
            event: 'payment.ipn',
            gatewayReference: $trackingId,
            reference: $merchantRef ?: null,
            raw: [
                'OrderTrackingId'        => $trackingId,
                'OrderMerchantReference' => $merchantRef,
                'OrderNotificationType'  => $notificationType,
            ]
        );
    }

    public function createAcknowledgmentResponse(WebhookPayload $payload): ResponseInterface
    {
        return Services::response()
            ->setStatusCode(200)
            ->setJSON([
                'orderNotificationType'  => 'IPNCHANGE',
                'orderTrackingId'        => $payload->gatewayReference,
                'orderMerchantReference' => $payload->reference,
                'status'                 => 200,
            ]);
    }
}
