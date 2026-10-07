# Jengo Pesa (`jengo/pesa`)

Universal multi-gateway payment processing subsystem for CodeIgniter 4 and the Jengo Framework with first-class support for Kenyan and African payment rails (M-Pesa Daraja 2.0, Pesapal v3, Flutterwave) alongside global providers (Stripe, PayPal).

---

## Key Features

1. **M-Pesa Daraja 2.0 Engine**:
   - **STK Push (Lipa Na M-Pesa Online / Express)** with automatic phone sanitization and transaction logging.
   - **C2B (Customer to Business)**: URL registration CLI command and instant confirmation handling for Paybills and Buy Goods (Till).
   - **B2C (Business to Customer)**: Disbursements, salary payments, dividend payouts, and withdrawals.
   - **Transaction Status & Querying**.
   - **Automatic OAuth Token Caching** using CI4 Cache service.

2. **Multi-Gateway Drivers**:
   - `mpesa`: Safaricom Daraja 2.0.
   - `pesapal`: Pesapal v3 API (IPN registration, hosted iframe/redirect checkout, mobile money + cards).
   - `stripe`: Stripe Checkout sessions with `stripe-signature` verification.
   - `fake`: In-memory mock driver for local testing and CI/CD pipelines.

3. **Transaction Ledger (`pesa_transactions`)**:
   - Automatic recording of all payments with lifecycle states: `pending` &rarr; `successful` | `failed` | `reversed`.
   - Idempotency protection ensuring duplicate webhooks do not double-credit users.

4. **Zero-Boilerplate Webhooks**:
   - Pre-routed webhook controller (`/pesa/webhook/{gateway}`).
   - Typed CodeIgniter events (`PaymentInitiated`, `PaymentSucceeded`, `PaymentFailed`, `PaymentReversed`).

---

## Installation

```bash
composer require jengo/pesa
```

Run database migrations:

```bash
php spark migrate -k jengo/pesa
```

Publish configuration:

```bash
php spark config:publish Jengo\\Pesa\\Config\\Pesa
```

---

## Quick Usage

### 1. Triggering an M-Pesa STK Push

```php
use Jengo\Pesa\DTO\StkRequest;
use Jengo\Pesa\Pesa;

$response = Pesa::gateway('mpesa')->stkPush(new StkRequest(
    phone: '0712345678', // Auto-normalized to 254712345678
    amount: 1500,
    accountReference: 'INV-1002',
    transactionDesc: 'Annual Subscription',
    callbackUrl: route_to('pesa.webhook', 'mpesa')
));

if ($response->isPending()) {
    $checkoutId = $response->checkoutRequestId;
    // Prompt sent to customer's phone!
}
```

### 2. Hosted Checkout (Pesapal / Stripe)

```php
use Jengo\Pesa\DTO\CheckoutRequest;
use Jengo\Pesa\Pesa;

$checkout = Pesa::gateway('pesapal')->checkout(new CheckoutRequest(
    amount: 4500,
    currency: 'KES',
    description: 'Order #442',
    callbackUrl: site_url('/orders/442/verify'),
    email: 'customer@example.com',
    phone: '254712345678',
    reference: 'ORD-442'
));

return redirect()->to($checkout->redirectUrl);
```

### 3. Listening to Payment Success Events

```php
// app/Config/Events.php
use CodeIgniter\Events\Events;
use Jengo\Pesa\Events\PaymentSucceeded;

Events::on('pesa.payment_succeeded', static function (PaymentSucceeded $event) {
    $transaction = $event->transaction;
    
    $receipt = $transaction->receipt_number; // e.g. QKH7189XYZ
    $amount  = $transaction->amount;
    $ref     = $transaction->reference;
    
    // Fulfill customer order...
});
```

### 4. CLI Commands

```bash
# Register M-Pesa C2B Paybill / Till URLs using nested sub-variants
php spark jengo:pesa mpesa register-c2b --shortcode=600999

# Or using the colon syntax
php spark jengo:pesa mpesa:register-c2b --shortcode=600999
```

---

## Configuration

All credentials and options are configured in `app/Config/Pesa.php`. CodeIgniter 4 automatically maps `.env` variables to config properties using the config class name prefix (e.g. `Pesa.default`, `Pesa.gateways.mpesa.consumer_key`, `Pesa.gateways.mpesa.passkey`):

```env
# Optional .env overrides matching app/Config/Pesa.php
Pesa.default = mpesa
Pesa.currency = KES
```

## License

MIT License &copy; Ian Otieno / Jengo Framework.
