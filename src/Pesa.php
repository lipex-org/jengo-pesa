<?php

declare(strict_types=1);

namespace Jengo\Pesa;

use Config\Services;
use Jengo\Pesa\Contracts\GatewayInterface;
use Jengo\Pesa\Contracts\WebhookHandlerInterface;
use Jengo\Pesa\DTO\CheckoutRequest;
use Jengo\Pesa\DTO\CheckoutResponse;
use Jengo\Pesa\DTO\DisbursementRequest;
use Jengo\Pesa\DTO\DisbursementResponse;
use Jengo\Pesa\DTO\StkRequest;
use Jengo\Pesa\DTO\StkResponse;
use Jengo\Pesa\DTO\TransactionQueryResponse;

/**
 * @method static GatewayInterface gateway(?string $name = null)
 * @method static StkResponse stkPush(StkRequest $request)
 * @method static TransactionQueryResponse queryStkStatus(string $checkoutRequestId)
 * @method static CheckoutResponse checkout(CheckoutRequest $request)
 * @method static DisbursementResponse disburse(DisbursementRequest $request)
 * @method static WebhookHandlerInterface getWebhookHandler()
 */
class Pesa
{
    /**
     * Get the PesaManager instance from the service locator.
     */
    public static function getManager(): PesaManager
    {
        return Services::pesa();
    }

    /**
     * Resolve a gateway instance by name.
     */
    public static function gateway(?string $name = null): GatewayInterface
    {
        return static::getManager()->gateway($name);
    }

    /**
     * Handle static method calls forwarded to the default gateway.
     */
    public static function __callStatic(string $method, array $arguments)
    {
        return static::getManager()->{$method}(...$arguments);
    }
}
