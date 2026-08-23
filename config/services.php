<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'eur'),
    ],

    'google_maps' => [
        'server_key' => env('GOOGLE_MAPS_SERVER_KEY'),
        'country' => env('GOOGLE_MAPS_COUNTRY', 'ES'),
        'default_center_lat' => (float) env('GOOGLE_MAPS_DEFAULT_CENTER_LAT', 40.4168),
        'default_center_lng' => (float) env('GOOGLE_MAPS_DEFAULT_CENTER_LNG', -3.7038),
        'default_radius_km' => (float) env('GOOGLE_MAPS_DEFAULT_RADIUS_KM', 50),
    ],

    'catastro' => [
        // Compatibility aliases for existing bindings and demo configuration.
        'national_base_url' => env(
            'CATASTRO_BASE_URL',
            'https://ovc.catastro.meh.es/OVCServWeb/OVCWcfCallejero/COVCCallejero.svc',
        ),
        'legacy_url' => env(
            'CATASTRO_LEGACY_URL',
            'https://ovc.catastro.meh.es/ovcservweb/ovcswlocalizacionrc/ovccallejero.asmx',
        ),
        'timeout' => (int) env('CATASTRO_TIMEOUT', 10),
        'enabled' => filter_var(env('CATASTRO_ENABLED', true), FILTER_VALIDATE_BOOL),
        // live = real national OVC; fixture = deterministic local fixtures (no HTTP).
        'provider_mode' => env('CATASTRO_PROVIDER_MODE', 'live'),
        'demo' => [
            'match_reference' => env('CATASTRO_DEMO_MATCH_REFERENCE', '2749704YJ0624N0001DI'),
            'area_review_reference' => env('CATASTRO_DEMO_AREA_REVIEW_REFERENCE', '2749704YJ0624N0001DI'),
            'sold_reference' => env('CATASTRO_DEMO_SOLD_REFERENCE', '2749704YJ0624N0001DI'),
        ],
    ],

];
