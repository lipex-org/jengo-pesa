<?php

declare(strict_types=1);

namespace Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Pesa\Drivers\Mpesa\MpesaSecurity;
use Jengo\Pesa\DTO\StkRequest;

/**
 * @internal
 */
final class MpesaHelpersTest extends CIUnitTestCase
{
    public function testPhoneSanitization(): void
    {
        $this->assertSame('254712345678', StkRequest::sanitizePhone('0712345678'));
        $this->assertSame('254712345678', StkRequest::sanitizePhone('+254712345678'));
        $this->assertSame('254712345678', StkRequest::sanitizePhone('254712345678'));
        $this->assertSame('254112345678', StkRequest::sanitizePhone('0112345678'));
        $this->assertSame('254712345678', StkRequest::sanitizePhone('712345678'));
    }

    public function testStkPasswordGeneration(): void
    {
        $shortcode = '174379';
        $passkey = 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919';
        $timestamp = '20261007200000';

        $password = MpesaSecurity::generateStkPassword($shortcode, $passkey, $timestamp);
        $expected = base64_encode($shortcode . $passkey . $timestamp);

        $this->assertSame($expected, $password);
    }
}
