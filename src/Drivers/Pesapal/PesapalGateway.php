<?php

declare(strict_types=1);

namespace Jengo\Pesa\Drivers\Pesapal;

use Config\Services;
use Jengo\Pesa\Config\Pesa as PesaConfig;
use Jengo\Pesa\Contracts\HostedCheckoutInterface;
use Jengo\Pesa\Contracts\WebhookHandlerInterface;
use Jengo\Pesa\Drivers\AbstractGateway;
use Jengo\Pesa\DTO\CheckoutRequest;
use Jengo\Pesa\DTO\CheckoutResponse;
use Jengo\Pesa\DTO\TransactionQueryResponse;
use Jengo\Pesa\Exceptions\GatewayRequestException;
use Jengo\Pesa\Exceptions\PesaException;

class PesapalGateway extends AbstractGateway implements HostedCheckoutInterface
{
    protected ?PesapalWebhookHandler $webhookHandler = null;

    public function getName(): string
    {
        return 'pesapal';
    }

    public function getWebhookHandler(): WebhookHandlerInterface
    {
        if ($this->webhookHandler === null) {
            $this->webhookHandler = new PesapalWebhookHandler();
        }
        return $this->webhookHandler;
    }

    public function getBaseUrl(): string
    {
        $env = strtolower((string) ($this->config['env'] ?? 'sandbox'));
        return $env === 'live'
            ? 'https://pay.pesapal.com/v3'
            : 'https://cybqa.pesapal.com/pesapalv3';
    }

    /**
     * Authenticate and retrieve Bearer token for Pesapal v3.
     */
    public function getAuthToken(): string
    {
        $consumerKey = (string) ($this->config['consumer_key'] ?? '');
        $consumerSecret = (string) ($this->config['consumer_secret'] ?? '');

        if (empty($consumerKey) || empty($consumerSecret)) {
            throw new PesaException('Pesapal consumer_key and consumer_secret must be configured');
        }

        $cacheKey = 'pesapal_auth_token_' . md5($consumerKey);
        $cache = Services::cache();

        if ($cache && ($cachedToken = $cache->get($cacheKey))) {
            return (string) $cachedToken;
        }

        $url = $this->getBaseUrl() . '/api/Auth/RequestToken';
        $client = $this->getHttpClient();

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json' => [
                    'consumer_key'    => $consumerKey,
                    'consumer_secret' => $consumerSecret,
                ],
                'http_errors' => false,
                'timeout'     => 30,
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true) ?? [];

            $token = (string) ($data['token'] ?? '');
            if (empty($token)) {
                throw new GatewayRequestException('Failed to authenticate with Pesapal API', $response->getStatusCode(), $data);
            }

            if ($cache) {
                $cache->save($cacheKey, $token, 300); // 5 min cache
            }

            return $token;
        } catch (\Throwable $e) {
            throw new GatewayRequestException('Pesapal token error: ' . $e->getMessage(), 0, [], $e);
        }
    }

    /**
     * Register IPN URL with Pesapal.
     */
    public function registerIpn(string $ipnUrl): string
    {
        $token = $this->getAuthToken();
        $url = $this->getBaseUrl() . '/api/URLSetup/RegisterIPN';
        $client = $this->getHttpClient();

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json' => [
                    'url'                   => $ipnUrl,
                    'ipn_notification_type' => 'GET',
                ],
                'http_errors' => false,
                'timeout'     => 30,
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true) ?? [];

            return (string) ($data['ipn_id'] ?? '');
        } catch (\Throwable $e) {
            throw new GatewayRequestException('Pesapal IPN registration error: ' . $e->getMessage(), 0, [], $e);
        }
    }

    /**
     * Submit an Order request and receive a redirect checkout URL.
     */
    public function checkout(CheckoutRequest $request): CheckoutResponse
    {
        $token = $this->getAuthToken();
        $ipnId = (string) ($this->config['ipn_id'] ?? '');

        if (empty($ipnId)) {
            throw new PesaException('Pesapal requires a registered ipn_id in configuration');
        }

        $url = $this->getBaseUrl() . '/api/Transactions/SubmitOrderRequest';

        $merchantReference = $request->reference ?? ('ORD-' . uniqid());

        $payload = [
            'id'                       => $merchantReference,
            'currency'                 => $request->currency,
            'amount'                   => $request->amount,
            'description'              => $request->description,
            'callback_url'             => $request->callbackUrl,
            'notification_id'          => $ipnId,
            'billing_address' => [
                'email_address' => $request->email ?? '',
                'phone_number'  => $request->phone ?? '',
                'country_code'  => 'KE',
            ],
        ];

        $client = $this->getHttpClient();

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json'        => $payload,
                'http_errors' => false,
                'timeout'     => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();
            $data = json_decode($body, true) ?? [];

            $orderTrackingId = (string) ($data['order_tracking_id'] ?? '');
            $redirectUrl = (string) ($data['redirect_url'] ?? '');

            $successful = ($statusCode === 200 && ! empty($redirectUrl));

            if ($successful) {
                $this->recordPendingTransaction(
                    gatewayReference: $orderTrackingId,
                    type: 'hosted_checkout',
                    amount: $request->amount,
                    currency: $request->currency,
                    reference: $merchantReference,
                    phone: $request->phone,
                    email: $request->email,
                    rawRequest: $payload
                );
            }

            return new CheckoutResponse(
                successful: $successful,
                redirectUrl: $redirectUrl,
                gatewayReference: $orderTrackingId,
                reference: $merchantReference,
                raw: $data
            );
        } catch (\Throwable $e) {
            throw new GatewayRequestException('Pesapal SubmitOrder error: ' . $e->getMessage(), 0, [], $e);
        }
    }

    /**
     * Query Transaction Status by OrderTrackingId.
     */
    public function queryStatus(string $orderTrackingId): TransactionQueryResponse
    {
        $token = $this->getAuthToken();
        $url = $this->getBaseUrl() . '/api/Transactions/GetTransactionStatus?orderTrackingId=' . urlencode($orderTrackingId);
        $client = $this->getHttpClient();

        try {
            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                ],
                'http_errors' => false,
                'timeout'     => 30,
            ]);

            $body = (string) $response->getBody();
            $data = json_decode($body, true) ?? [];

            $statusDesc = strtolower((string) ($data['status_description'] ?? ''));
            $status = match ($statusDesc) {
                'completed' => 'successful',
                'failed'    => 'failed',
                'reversed'  => 'reversed',
                default     => 'pending',
            };

            return new TransactionQueryResponse(
                successful: ($status === 'successful'),
                status: $status,
                receiptNumber: (string) ($data['confirmation_code'] ?? ''),
                amount: isset($data['amount']) ? (float) $data['amount'] : null,
                phone: (string) ($data['phone_number'] ?? ''),
                resultCode: (string) ($data['status_code'] ?? ''),
                resultDesc: $statusDesc,
                raw: $data
            );
        } catch (\Throwable $e) {
            throw new GatewayRequestException('Pesapal QueryStatus error: ' . $e->getMessage(), 0, [], $e);
        }
    }
}
