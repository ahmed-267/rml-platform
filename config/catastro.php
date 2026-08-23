<?php

return [
    'enabled' => filter_var(env('CATASTRO_ENABLED', true), FILTER_VALIDATE_BOOL),
    'base_url' => env(
        'CATASTRO_BASE_URL',
        'https://ovc.catastro.meh.es/OVCServWeb/OVCWcfCallejero/COVCCallejero.svc',
    ),
    'legacy_url' => env(
        'CATASTRO_LEGACY_URL',
        'https://ovc.catastro.meh.es/ovcservweb/ovcswlocalizacionrc/ovccallejero.asmx',
    ),
    'timeout' => (int) env('CATASTRO_TIMEOUT', 10),
    // live = official public OVC transport; fixture = deterministic local fixtures.
    'provider_mode' => env('CATASTRO_PROVIDER_MODE', 'live'),
];
