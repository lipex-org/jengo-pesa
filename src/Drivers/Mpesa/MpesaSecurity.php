<?php

declare(strict_types=1);

namespace Jengo\Pesa\Drivers\Mpesa;

use Jengo\Pesa\Exceptions\PesaException;

class MpesaSecurity
{
    /**
     * Generate password for Lipa Na M-Pesa Online (STK Push).
     * Password = Base64(ShortCode + PassKey + Timestamp)
     */
    public static function generateStkPassword(string $shortcode, string $passkey, string $timestamp): string
    {
        return base64_encode($shortcode . $passkey . $timestamp);
    }

    /**
     * Generate format timestamp: YYYYMMDDHHmmss.
     */
    public static function generateTimestamp(): string
    {
        return date('YmdHis');
    }

    /**
     * Generate Security Credential for B2C and Account Balance using Safaricom Public Certificate.
     * Encrypts the initiator plaintext password with the Daraja X509 public key.
     */
    public static function generateSecurityCredential(string $initiatorPassword, string $certPath): string
    {
        if (! file_exists($certPath)) {
            throw new PesaException("M-Pesa certificate file not found at: {$certPath}");
        }

        $pubKey = file_get_contents($certPath);
        if ($pubKey === false) {
            throw new PesaException("Unable to read M-Pesa certificate at: {$certPath}");
        }

        $encrypted = '';
        if (! openssl_public_encrypt($initiatorPassword, $encrypted, $pubKey, OPENSSL_PKCS1_PADDING)) {
            throw new PesaException('OpenSSL failed to encrypt M-Pesa Security Credential');
        }

        return base64_encode($encrypted);
    }
}
