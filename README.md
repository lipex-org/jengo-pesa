<p align="center">
  <a href="https://lipex-org.github.io/jengophp.com/">
    <img src="https://raw.githubusercontent.com/lipex-org/jengophp.com/main/public/logo-full.png" width="220" alt="Jengo Logo">
  </a>
</p>

<h1 align="center">Jengo Pesa</h1>

<p align="center">
  <strong>Unified multi-gateway payment processing subsystem for CodeIgniter 4 and the Jengo Framework with first-class support for M-Pesa Daraja 3.0, Pesapal v3, Stripe, and Flutterwave.</strong>
</p>

<p align="center">
  <a href="https://lipex-org.github.io/jengophp.com/packages/pesa"><strong>Documentation</strong></a> •
  <a href="https://github.com/lipex-org/pesa/blob/main/LICENSE"><strong>License</strong></a> •
  <a href="https://github.com/lipex-org/pesa/issues"><strong>Issues</strong></a>
</p>

---

## Installation

```bash
composer require jengo/pesa
php spark jengo:install pesa
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

For full guides on M-Pesa Daraja 3.0 workflows (STK, C2B, B2C), hosted checkouts (Pesapal v3, Stripe), transaction ledgers, auto-routed webhooks, and CLI commands, visit https://lipex-org.github.io/jengophp.com/packages/pesa/.

## License

Released under the MIT License.
