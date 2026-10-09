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
    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../src/Database/Migrations/2026_10_07_000001_create_pesa_transactions_table.php';

        $forge = \Config\Database::forge();
        $migration = new \Jengo\Pesa\Database\Migrations\CreatePesaTransactionsTable($forge);
        $migration->up();
    }

    protected function tearDown(): void
    {
        $forge = \Config\Database::forge();
        $migration = new \Jengo\Pesa\Database\Migrations\CreatePesaTransactionsTable($forge);
        $migration->down();

        parent::tearDown();
    }

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
