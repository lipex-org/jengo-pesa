<?php

declare(strict_types=1);

namespace Jengo\Pesa\Drivers\Mpesa;

use Jengo\Pesa\Config\Pesa as PesaConfig;
use Jengo\Pesa\Contracts\B2CInterface;
use Jengo\Pesa\Contracts\C2BInterface;
use Jengo\Pesa\Contracts\StkPushInterface;
use Jengo\Pesa\Contracts\WebhookHandlerInterface;
use Jengo\Pesa\Drivers\AbstractGateway;
use Jengo\Pesa\DTO\DisbursementRequest;
use Jengo\Pesa\DTO\DisbursementResponse;
use Jengo\Pesa\DTO\StkRequest;
use Jengo\Pesa\DTO\StkResponse;
use Jengo\Pesa\DTO\TransactionQueryResponse;
use Jengo\Pesa\Exceptions\GatewayRequestException;
use Jengo\Pesa\Exceptions\PesaException;

class MpesaGateway extends AbstractGateway implements StkPushInterface, C2BInterface, B2CInterface
{
    protected MpesaAuthenticator $auth;
    protected ?MpesaWebhookHandler $webhookHandler = null;

    public function __construct(array $config = [], ?PesaConfig $pesaConfig = null)
    {
        parent::__construct($config, $pesaConfig);

        $consumerKey = (string) ($this->config['consumer_key'] ?? '');
        $consumerSecret = (string) ($this->config['consumer_secret'] ?? '');
        $env = (string) ($this->config['env'] ?? 'sandbox');

        $this->auth = new MpesaAuthenticator($consumerKey, $consumerSecret, $env);
    }

    public function getName(): string
    {
        return 'mpesa';
    }

    public function getWebhookHandler(): WebhookHandlerInterface
    {
        if ($this->webhookHandler === null) {
            $this->webhookHandler = new MpesaWebhookHandler();
        }
        return $this->webhookHandler;
    }

    /**
     * Trigger an M-Pesa STK Push (Lipa Na M-Pesa Online).
     */
    public function stkPush(StkRequest $request): StkResponse
    {
        $shortcode = (string) ($this->config['shortcode'] ?? '');
        $passkey = (string) ($this->config['passkey'] ?? '');
        $callbackUrl = $request->callbackUrl ?? ($this->config['callback_url'] ?? '');

        if (empty($shortcode) || empty($passkey)) {
            throw new PesaException('M-Pesa STK Push requires shortcode and passkey to be configured');
        }

        if (empty($callbackUrl)) {
            throw new PesaException('M-Pesa STK Push requires a valid callback URL');
        }

        $timestamp = MpesaSecurity::generateTimestamp();
        $password = MpesaSecurity::generateStkPassword($shortcode, $passkey, $timestamp);
        $token = $this->auth->getAccessToken();

        $url = $this->auth->getBaseUrl() . '/mpesa/stkpush/v1/processrequest';

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => 'CustomerPayBillOnline', // or CustomerBuyGoodsOnline
            'Amount'            => (int) round($request->amount),
            'PartyA'            => $request->phone,
            'PartyB'            => $shortcode,
            'PhoneNumber'       => $request->phone,
            'CallBackURL'       => $callbackUrl,
            'AccountReference'  => substr($request->accountReference, 0, 12),
            'TransactionDesc'   => substr($request->transactionDesc, 0, 13),
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

            $merchantRequestId = (string) ($data['MerchantRequestID'] ?? '');
            $checkoutRequestId = (string) ($data['CheckoutRequestID'] ?? '');
            $responseCode = (string) ($data['ResponseCode'] ?? '-1');
            $responseDesc = (string) ($data['ResponseDescription'] ?? '');
            $customerMsg = (string) ($data['CustomerMessage'] ?? $responseDesc);

            $successful = ($statusCode === 200 && $responseCode === '0');

            if ($successful) {
                // Record in ledger
                $this->recordPendingTransaction(
                    gatewayReference: $checkoutRequestId,
                    type: 'stk_push',
                    amount: $request->amount,
                    currency: 'KES',
                    reference: $request->reference ?? $request->accountReference,
                    phone: $request->phone,
                    rawRequest: $payload
                );
            }

            return new StkResponse(
                successful: $successful,
                merchantRequestId: $merchantRequestId,
                checkoutRequestId: $checkoutRequestId,
                responseCode: $responseCode,
                responseDescription: $responseDesc,
                customerMessage: $customerMsg,
                reference: $request->reference ?? $request->accountReference,
                raw: $data
            );
        } catch (\Throwable $e) {
            throw new GatewayRequestException('M-Pesa STK Push error: ' . $e->getMessage(), 0, [], $e);
        }
    }

    /**
     * Query STK Push transaction status.
     */
    public function queryStkStatus(string $checkoutRequestId): TransactionQueryResponse
    {
        $shortcode = (string) ($this->config['shortcode'] ?? '');
        $passkey = (string) ($this->config['passkey'] ?? '');

        if (empty($shortcode) || empty($passkey)) {
            throw new PesaException('Querying STK status requires shortcode and passkey to be configured');
        }

        $timestamp = MpesaSecurity::generateTimestamp();
        $password = MpesaSecurity::generateStkPassword($shortcode, $passkey, $timestamp);
        $token = $this->auth->getAccessToken();

        $url = $this->auth->getBaseUrl() . '/mpesa/stkpushquery/v1/query';

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId,
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

            $resultCode = (string) ($data['ResultCode'] ?? '-1');
            $resultDesc = (string) ($data['ResultDesc'] ?? ($data['errorMessage'] ?? ''));

            $status = 'pending';
            if ($resultCode === '0') {
                $status = 'successful';
            } elseif ($resultCode !== '-1' && $statusCode === 200) {
                $status = 'failed';
            }

            return new TransactionQueryResponse(
                successful: ($status === 'successful'),
                status: $status,
                resultCode: $resultCode,
                resultDesc: $resultDesc,
                raw: $data
            );
        } catch (\Throwable $e) {
            throw new GatewayRequestException('M-Pesa STK query error: ' . $e->getMessage(), 0, [], $e);
        }
    }

    /**
     * Register C2B URLs with Safaricom.
     */
    public function registerC2BUrls(
        string $shortCode,
        string $responseType,
        string $validationUrl,
        string $confirmationUrl
    ): array {
        $token = $this->auth->getAccessToken();
        $url = $this->auth->getBaseUrl() . '/mpesa/c2b/v1/registerurl';

        $payload = [
            'ShortCode'       => $shortCode,
            'ResponseType'    => $responseType, // Completed or Cancelled
            'ConfirmationURL' => $confirmationUrl,
            'ValidationURL'   => $validationUrl,
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

            $body = (string) $response->getBody();
            return json_decode($body, true) ?? [];
        } catch (\Throwable $e) {
            throw new GatewayRequestException('M-Pesa C2B registration error: ' . $e->getMessage(), 0, [], $e);
        }
    }

    /**
     * B2C Money Disbursement (Salary, Dividend, Promotion, Payout).
     */
    public function disburse(DisbursementRequest $request): DisbursementResponse
    {
        $shortcode = (string) ($this->config['shortcode'] ?? '');
        $initiatorName = (string) ($this->config['initiator_name'] ?? '');
        $securityCred = (string) ($this->config['security_credential'] ?? '');
        $resultUrl = (string) ($this->config['result_url'] ?? '');
        $timeoutUrl = (string) ($this->config['timeout_url'] ?? '');

        if (empty($securityCred) && ! empty($this->config['cert_path']) && ! empty($this->config['initiator_password'])) {
            $securityCred = MpesaSecurity::generateSecurityCredential(
                $this->config['initiator_password'],
                $this->config['cert_path']
            );
        }

        if (empty($shortcode) || empty($initiatorName) || empty($securityCred)) {
            throw new PesaException('B2C payout requires shortcode, initiator_name, and security_credential');
        }

        $token = $this->auth->getAccessToken();
        $url = $this->auth->getBaseUrl() . '/mpesa/b2c/v1/paymentrequest';

        $payload = [
            'InitiatorName'          => $initiatorName,
            'SecurityCredential'     => $securityCred,
            'CommandID'              => $request->commandId,
            'Amount'                 => (int) round($request->amount),
            'PartyA'                 => $shortcode,
            'PartyB'                 => $request->phone,
            'Remarks'                => substr($request->remarks, 0, 100),
            'QueueTimeOutURL'        => $timeoutUrl,
            'ResultURL'              => $resultUrl,
            'Occasion'               => substr($request->occasion, 0, 100),
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

            $origConvId = (string) ($data['OriginatorConversationID'] ?? '');
            $convId = (string) ($data['ConversationID'] ?? '');
            $responseCode = (string) ($data['ResponseCode'] ?? '-1');
            $responseDesc = (string) ($data['ResponseDescription'] ?? '');

            $successful = ($statusCode === 200 && $responseCode === '0');

            if ($successful) {
                $this->recordPendingTransaction(
                    gatewayReference: $convId ?: $origConvId,
                    type: 'b2c_disbursement',
                    amount: $request->amount,
                    currency: 'KES',
                    reference: $request->reference,
                    phone: $request->phone,
                    rawRequest: $payload
                );
            }

            return new DisbursementResponse(
                successful: $successful,
                originatorConversationId: $origConvId,
                conversationId: $convId,
                responseCode: $responseCode,
                responseDescription: $responseDesc,
                reference: $request->reference,
                raw: $data
            );
        } catch (\Throwable $e) {
            throw new GatewayRequestException('M-Pesa B2C error: ' . $e->getMessage(), 0, [], $e);
        }
    }
}
