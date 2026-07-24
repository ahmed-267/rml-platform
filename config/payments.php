<?php

return [
    'default_provider' => env('PAYMENT_DEFAULT_PROVIDER', 'manual_bank_transfer'),
    'card_provider' => env('PAYMENT_CARD_PROVIDER', 'stripe'),

    'currency' => env('PAYMENT_CURRENCY', 'EUR'),

    'mollie' => [
        'key' => env('MOLLIE_KEY'),
        'webhook_secret' => env('MOLLIE_WEBHOOK_SECRET'),
        'webhook_url' => env('MOLLIE_WEBHOOK_URL'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'eur'),
    ],

    'bank_transfer' => [
        'account_name' => env('BANK_TRANSFER_ACCOUNT_NAME', 'RML Energy Exchange'),
        'iban' => env('BANK_TRANSFER_IBAN', 'ES00 0000 0000 0000 0000 0000'),
        'bic' => env('BANK_TRANSFER_BIC', 'CAIXESBBXXX'),
        'bank_name' => env('BANK_TRANSFER_BANK_NAME', 'Demo Bank'),
        'instructions' => env(
            'BANK_TRANSFER_INSTRUCTIONS',
            'Pay by bank transfer using the payment reference shown. Lead details are released after payment confirmation.'
        ),
    ],
];
