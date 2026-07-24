<?php

return [
    'provider' => env('WHATSAPP_PROVIDER', 'meta_cloud'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
    'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '34'),
];
