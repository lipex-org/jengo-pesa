<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Pesa\DTO\CheckoutRequest;
use Jengo\Pesa\DTO\DisbursementRequest;
use Jengo\Pesa\DTO\StkRequest;
use Jengo\Pesa\Pesa;

/**
 * @internal
 */
final class FakeGatewayTest extends CIUnitTestCase
{
    public function testStkPushWorkflow(): void
    {
        $gateway = Pesa::gateway('fake');

        $req = new StkRequest(
            phone: '0712345678',
            amount: 500,
            accountReference: 'INV-100'
        );

        $this->assertSame('254712345678', $req->phone);

        $res = $gateway->stkPush($req);
        $this->assertTrue($res->successful);
        $this->assertTrue($res->isPending());
        $this->assertStringStartsWith('ws_FAKE_', $res->checkoutRequestId);

        $status = $gateway->queryStkStatus($res->checkoutRequestId);
        $this->assertTrue($status->isSuccessful());
        $this->assertNotNull($status->receiptNumber);
    }

    public function testHostedCheckout(): void
    {
        $gateway = Pesa::gateway('fake');

        $req = new CheckoutRequest(
            amount: 2500,
            currency: 'KES',
            description: 'Order 1',
            callbackUrl: 'https://example.com/callback'
        );

        $res = $gateway->checkout($req);
        $this->assertTrue($res->successful);
        $this->assertStringContainsString('https://checkout.fake-gateway.local', $res->redirectUrl);
    }

    public function testDisbursement(): void
    {
        $gateway = Pesa::gateway('fake');

        $req = new DisbursementRequest(
            phone: '0712345678',
            amount: 1000,
            remarks: 'Bonus payout'
        );

        $res = $gateway->disburse($req);
        $this->assertTrue($res->successful);
        $this->assertStringStartsWith('B2C_FAKE_', $res->conversationId);
    }
}
