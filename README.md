# Jengo Pesa

A unified multi-gateway payment processing subsystem for CodeIgniter 4 and the Jengo Framework with first-class support for Kenyan and African payment networks (M-Pesa Daraja 2.0, Pesapal v3, Flutterwave) alongside global providers (Stripe, PayPal).

Documentation: https://lipex-org.github.io/jengophp.com/packages/pesa/

## Installation

```bash
composer require jengo/pesa
php spark migrate -k jengo/pesa
php spark config:publish Jengo\\Pesa\\Config\\Pesa
```

## Quick Start

```php
use Jengo\Pesa\DTO\StkRequest;
use Jengo\Pesa\Pesa;

// Trigger an M-Pesa STK Push
$response = Pesa::gateway('mpesa')->stkPush(new StkRequest(
    phone: '0712345678',
    amount: 1500,
    accountReference: 'INV-1002',
    transactionDesc: 'Annual Subscription'
));

// Hosted checkout (Pesapal or Stripe)
$checkout = Pesa::gateway('pesapal')->checkout(new CheckoutRequest(
    amount: 4500,
    currency: 'KES',
    description: 'Order #442',
    callbackUrl: site_url('/orders/442/verify')
));
```

## Documentation

For full guides on M-Pesa Daraja 2.0 workflows (STK, C2B, B2C), hosted checkouts (Pesapal v3, Stripe), transaction ledgers, auto-routed webhooks, and CLI commands, visit https://lipex-org.github.io/jengophp.com/packages/pesa/.

## License

Released under the MIT License.
