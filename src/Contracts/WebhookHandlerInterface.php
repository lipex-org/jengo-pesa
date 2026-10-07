<?php

declare(strict_types=1);

namespace Jengo\Pesa\Contracts;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Pesa\DTO\WebhookPayload;

interface WebhookHandlerInterface
{
    /**
     * Parse and verify the incoming webhook request from the gateway.
     * Returns a normalized WebhookPayload or throws WebhookVerificationException.
     */
    public function handle(IncomingRequest $request): WebhookPayload;

    /**
     * Generate the appropriate HTTP response acknowledgment for the gateway.
     */
    public function createAcknowledgmentResponse(WebhookPayload $payload): ResponseInterface;
}
