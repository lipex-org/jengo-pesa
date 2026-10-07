<?php

declare(strict_types=1);

namespace Jengo\Pesa\Contracts;

interface GatewayInterface
{
    /**
     * Get the unique gateway name identifier (e.g., 'mpesa', 'pesapal', 'stripe').
     */
    public function getName(): string;

    /**
     * Get the webhook handler instance for this gateway.
     */
    public function getWebhookHandler(): WebhookHandlerInterface;
}
