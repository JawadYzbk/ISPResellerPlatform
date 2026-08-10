# Whish Pay PHP client

This is the platform's standalone, framework-neutral Whish Pay client. It has no Node.js dependency and accepts an injected HTTP transport, with cURL available as the default transport.

The client follows the server-side contract used by the referenced community client: create a payment, receive a collect URL, and verify the payment with the status endpoint before treating it as paid. The Laravel application keeps ledger amounts in integer minor units and converts only at the provider boundary.

```php
$client = new WhishPay\WhishClient(
    new WhishPay\WhishConfig(
        channel: $channel,
        secret: $secret,
        websiteUrl: 'https://portal.example.test',
        environment: 'sandbox',
    ),
);

$payment = $client->createPayment(new WhishPay\PaymentRequest(
    amount: '12.50',
    currency: 'USD',
    invoice: 'INV-100',
    externalId: '123456789',
    successCallbackUrl: 'https://portal.example.test/whish/success',
    failureCallbackUrl: 'https://portal.example.test/whish/failure',
    successRedirectUrl: 'https://portal.example.test/whish/success',
    failureRedirectUrl: 'https://portal.example.test/whish/failure',
));

$status = $client->getPaymentStatus('123456789', 'USD');
```

Merchant credentials are held in private configuration properties and are never included in the client's public serialization. Confirm endpoint paths, headers, amount units, callback fields, and production credentials against the current official Whish merchant documentation before enabling live traffic.

Reference contract: [Mohammad-AlBaker-Zaytoun/whish-pay](https://github.com/Mohammad-AlBaker-Zaytoun/whish-pay).
