<?php

declare(strict_types=1);

namespace Jengo\Pesa\Config;

use CodeIgniter\Config\BaseConfig;

class Pesa extends BaseConfig
{
    /**
     * Default payment gateway driver.
     * Supported: 'mpesa', 'pesapal', 'stripe', 'flutterwave', 'fake'
     */
    public string $default = 'mpesa';

    /**
     * Base currency for transactions.
     */
    public string $currency = 'KES';

    /**
     * Enable automatic transaction logging in database.
     */
    public bool $enableLedger = true;

    /**
     * Database table for payment transactions.
     */
    public string $table = 'pesa_transactions';

    /**
     * Gateways Configuration
     *
     * @var array<string, array<string, mixed>>
     */
    public array $gateways = [
        'mpesa' => [
            'env'           => 'sandbox', // 'sandbox' or 'live'
            'shortcode'     => '',        // Paybill or Till Number
            'consumer_key'  => '',
            'consumer_secret' => '',
            'passkey'       => '',        // Lipa Na M-Pesa Online passkey
            'initiator_name' => '',       // B2C initiator username
            'security_credential' => '',  // B2C encrypted password
            'cert_path'     => '',        // Public cert path for security credential generation
            'callback_url'  => '',        // Default callback URL for STK push
            'timeout_url'   => '',        // B2C timeout URL
            'result_url'    => '',        // B2C result URL
        ],
        'pesapal' => [
            'env'           => 'sandbox', // 'sandbox' or 'live'
            'consumer_key'  => '',
            'consumer_secret' => '',
            'ipn_id'        => '',        // Registered IPN Notification ID
            'callback_url'  => '',
        ],
        'stripe' => [
            'key'            => '',
            'secret'         => '',
            'webhook_secret' => '',
        ],
        'flutterwave' => [
            'public_key'     => '',
            'secret_key'     => '',
            'encryption_key' => '',
            'secret_hash'    => '',
        ],
        'fake' => [
            'auto_succeed'   => true,
            'latency_ms'     => 100,
        ],
    ];
}
