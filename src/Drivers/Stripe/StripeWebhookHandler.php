<?php

declare(strict_types=1);

namespace Jengo\Pesa\Drivers\Stripe;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Pesa\Contracts\WebhookHandlerInterface;
use Jengo\Pesa\DTO\WebhookPayload;
use Jengo\Pesa\Exceptions\WebhookVerificationException;

class StripeWebhookHandler implements WebhookHandlerInterface
{
    public function __construct(protected string $webhookSecret = '')
    {
    }

    public function handle(IncomingRequest $request): WebhookPayload
    {
        $payload = (string) $request->getBody();
        $sigHeader = $request->getHeaderLine('stripe-signature');

        if (empty($payload)) {
            throw new WebhookVerificationException('Empty Stripe webhook body');
        }

        if (! empty($this->webhookSecret) && ! empty($sigHeader)) {
            $this->verifySignature($payload, $sigHeader, $this->webhookSecret);
        }

        $data = json_decode($payload, true);
        if (! is_array($data)) {
            throw new WebhookVerificationException('Invalid JSON payload from Stripe');
        }

        $eventType = (string) ($data['type'] ?? '');
        $object = $data['data']['object'] ?? [];

        $gatewayRef = (string) ($object['id'] ?? '');
        $receipt = (string) ($object['payment_intent'] ?? $gatewayRef);
        $amount = isset($object['amount_total']) ? ($object['amount_total'] / 100) : (isset($object['amount']) ? ($object['amount'] / 100) : null);
        $currency = strtoupper((string) ($object['currency'] ?? 'USD'));
        $email = (string) ($object['customer_details']['email'] ?? ($object['receipt_email'] ?? ''));
        $name = (string) ($object['customer_details']['name'] ?? '');
        $clientRef = (string) ($object['client_reference_id'] ?? ($object['metadata']['reference'] ?? ''));

        $event = match ($eventType) {
            'checkout.session.completed', 'payment_intent.succeeded' => 'payment.success',
            'payment_intent.payment_failed'                          => 'payment.failed',
            'charge.refunded'                                        => 'payment.reversed',
            default                                                  => 'payment.' . $eventType,
        };

        return new WebhookPayload(
            gateway: 'stripe',
            event: $event,
            gatewayReference: $gatewayRef,
            receiptNumber: $receipt,
            amount: $amount,
            currency: $currency,
            payerName: $name ?: null,
            payerEmail: $email ?: null,
            reference: $clientRef ?: null,
            raw: $data
        );
    }

    protected function verifySignature(string $payload, string $sigHeader, string $secret): void
    {
        // Parse t=timestamp, v1=signature
        $items = explode(',', $sigHeader);
        $timestamp = null;
        $signatures = [];

        foreach ($items as $item) {
            $parts = explode('=', trim($item), 2);
            if (count($parts) === 2) {
                if ($parts[0] === 't') {
                    $timestamp = $parts[1];
                } elseif ($parts[0] === 'v1') {
                    $signatures[] = $parts[1];
                }
            }
        }

        if ($timestamp === null || empty($signatures)) {
            throw new WebhookVerificationException('Malformed stripe-signature header');
        }

        // Tolerance check: 5 minutes (300 seconds)
        if (abs(time() - (int) $timestamp) > 300) {
            throw new WebhookVerificationException('Stripe webhook signature timestamp expired');
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        $valid = false;
        foreach ($signatures as $sig) {
            if (hash_equals($expectedSignature, $sig)) {
                $valid = true;
                break;
            }
        }

        if (! $valid) {
            throw new WebhookVerificationException('Invalid Stripe webhook signature');
        }
    }

    public function createAcknowledgmentResponse(WebhookPayload $payload): ResponseInterface
    {
        return Services::response()
            ->setStatusCode(200)
            ->setJSON(['received' => true]);
    }
}
