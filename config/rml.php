<?php

return [
    'platform' => [
        'company_name' => env('RML_COMPANY_NAME', 'RML Energy Saving'),
        'support_email' => env('RML_SUPPORT_EMAIL', 'support@rml-energy.test'),
        'support_phone' => env('RML_SUPPORT_PHONE', '+34 910 000 221'),
        'default_locale' => env('RML_DEFAULT_LOCALE', 'en'),
        'default_currency' => env('RML_DEFAULT_CURRENCY', 'EUR'),
        'bank_transfer_instructions' => env(
            'RML_BANK_TRANSFER_INSTRUCTIONS',
            "RML Energy Saving\nIBAN: ES91 2100 0418 4502 0005 1332\nBIC: CAIXESBBXXX\nUse the invoice payment reference as the transfer concept.",
        ),
    ],
];
