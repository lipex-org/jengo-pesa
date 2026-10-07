<?php

declare(strict_types=1);

namespace Jengo\Pesa\Drivers\Mpesa;

use CodeIgniter\HTTP\CURLRequest;
use Config\Services;
use Jengo\Pesa\Exceptions\GatewayRequestException;

class MpesaAuthenticator
{
    protected string $env;
    protected string $consumerKey;
    protected string $consumerSecret;
    protected ?CURLRequest $httpClient = null;

    public function __construct(string $consumerKey, string $consumerSecret, string $env = 'sandbox')
    {
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->env = strtolower($env);
    }

    public function setHttpClient(CURLRequest $client): void
    {
        $this->httpClient = $client;
    }

    /**
     * Get OAuth base URL.
     */
    public function getBaseUrl(): string
    {
        return $this->env === 'live'
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Fetch OAuth Access Token from Safaricom Daraja.
     * Caches token in CI4 Cache service until near expiry.
     */
    public function getAccessToken(): string
    {
        $cacheKey = 'mpesa_oauth_token_' . md5($this->consumerKey . $this->env);
        $cache = Services::cache();

        if ($cache && ($cachedToken = $cache->get($cacheKey))) {
            return (string) $cachedToken;
        }

        $url = $this->getBaseUrl() . '/oauth/v1/generate?grant_type=client_credentials';
        $credentials = base64_encode($this->consumerKey . ':' . $this->consumerSecret);

        $client = $this->httpClient ?? Services::curlrequest();

        try {
            $response = $client->get($url, [
                'headers' => [
                    'Authorization' => 'Basic ' . $credentials,
                    'Accept'        => 'application/json',
                ],
                'http_errors' => false,
                'timeout'     => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();
            $data = json_decode($body, true) ?? [];

            if ($statusCode !== 200 || ! isset($data['access_token'])) {
                $errorMsg = $data['errorMessage'] ?? 'Failed to authenticate with M-Pesa Daraja API';
                throw new GatewayRequestException($errorMsg, $statusCode, $data);
            }

            $token = (string) $data['access_token'];
            $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : 3599;

            // Cache token (subtract 60s for safety margin)
            if ($cache) {
                $cache->save($cacheKey, $token, max(60, $expiresIn - 60));
            }

            return $token;
        } catch (\Throwable $e) {
            if ($e instanceof GatewayRequestException) {
                throw $e;
            }
            throw new GatewayRequestException('M-Pesa authentication error: ' . $e->getMessage(), 0, [], $e);
        }
    }
}
