<?php

declare(strict_types=1);

namespace Jengo\Pesa\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Jengo\Base\Container\Traits\HasContainer;
use Jengo\Pesa\DTO\WebhookPayload;
use Jengo\Pesa\Pesa;
use Jengo\Pesa\Services\PesaTransactionService;
use Jengo\Queues\Facades\Queue;

class PesaWebhookController extends Controller
{
    use HasContainer;

    /**
     * Handle incoming payment webhooks and IPN notifications.
     */
    public function handle(string $gateway): ResponseInterface
    {
        /** @var IncomingRequest $request */
        $request = $this->request;

        $gatewayDriver = Pesa::gateway($gateway);
        $handler = $gatewayDriver->getWebhookHandler();

        try {
            $payload = $handler->handle($request);
        } catch (\Throwable $e) {
            log_message('error', "[Pesa] Webhook processing failed for [{$gateway}]: " . $e->getMessage());
            return Services::response()
                ->setStatusCode(400)
                ->setJSON([
                    'error'   => true,
                    'message' => $e->getMessage(),
                ]);
        }

        // Process transaction state & ledger updates asynchronously via Queue::defer
        Queue::defer(function (PesaTransactionService $service, string $gw, WebhookPayload $data) {
            $service->process($gw, $data);
        }, $gateway, $payload);

        return $handler->createAcknowledgmentResponse($payload);
    }
}
