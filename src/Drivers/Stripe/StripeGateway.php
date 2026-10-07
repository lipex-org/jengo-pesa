<?php

declare(strict_types=1);

namespace Jengo\Pesa\Drivers\Stripe;

use Jengo\Pesa\Config\Pesa as PesaConfig;
use Jengo\Pesa\Contracts\HostedCheckoutInterface;
use Jengo\Pesa\Contracts\WebhookHandlerInterface;
use Jengo\Pesa\Drivers\AbstractGateway;
use Jengo\Pesa\DTO\CheckoutRequest;
use Jengo\Pesa\DTO\CheckoutResponse;
use Jengo\Pesa\Exceptions\GatewayRequestException;
use Jengo\Pesa\Exceptions\PesaException;

class StripeGateway extends AbstractGateway implements HostedCheckoutInterface
{
    protected ?StripeWebhookHandler $webhookHandler = null;

    public function getName(): string
    {
        return 'stripe';
    }

    public function getWebhookHandler(): WebhookHandlerInterface
    {
        if ($this->webhookHandler === null) {
            $secret = (string) ($this->config['webhook_secret'] ?? '');
            $this->webhookHandler = new StripeWebhookHandler($secret);
        }
        return $this->webhookHandler;
    }

    /**
     * Create a Stripe Checkout Session.
     */
    public function checkout(CheckoutRequest $request): CheckoutResponse
    {
        $secretKey = (string) ($this->config['secret'] ?? '');

        if (empty($secretKey)) {
            throw new PesaException('Stripe secret key must be configured');
        }

        $url = 'https://api.stripe.com/v1/checkout/sessions';

        $merchantReference = $request->reference ?? ('ORD-' . uniqid());

        $payload = [
            'payment_method_types' => ['card'],
            'mode'                 => 'payment',
            'client_reference_id'  => $merchantReference,
            'success_url'          => $request->callbackUrl,
            'cancel_url'           => $request->cancelUrl ?? $request->callbackUrl,
            'line_items' => [
                [
                    'price_data' => [
                        'currency'     => strtolower($request->currency),
                        'unit_amount'  => (int) round($request->amount * 100), // Stripe in cents
                        'product_data' => [
                            'name' => $request->description,
                        ],
                    ],
                    'quantity' => 1,
                ],
            ],
        ];

        if (! empty($request->email)) {
            $payload['customer_email'] = $request->email;
        }

        $client = $this->getHttpClient();

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $secretKey,
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ],
                'form_params' => $payload,
                'http_errors' => false,
                'timeout'     => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();
            $data = json_decode($body, true) ?? [];

            $sessionId = (string) ($data['id'] ?? '');
            $redirectUrl = (string) ($data['url'] ?? '');

            $successful = ($statusCode === 200 && ! empty($redirectUrl));

            if ($successful) {
                $this->recordPendingTransaction(
                    gatewayReference: $sessionId,
                    type: 'hosted_checkout',
                    amount: $request->amount,
                    currency: $request->currency,
                    reference: $merchantReference,
                    email: $request->email,
                    rawRequest: $payload
                );
            }

            return new CheckoutResponse(
                successful: $successful,
                redirectUrl: $redirectUrl,
                gatewayReference: $sessionId,
                reference: $merchantReference,
                raw: $data
            );
        } catch (\Throwable $e) {
            throw new GatewayRequestException('Stripe checkout error: ' . $e->getMessage(), 0, [], $e);
        }
    }
}
