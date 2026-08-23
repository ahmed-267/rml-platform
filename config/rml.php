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
    'leads' => [
        'default_status_after_submission' => env('RML_LEAD_DEFAULT_STATUS', 'pending_validation'),
        'require_internal_audit' => filter_var(env('RML_LEAD_REQUIRE_AUDIT', true), FILTER_VALIDATE_BOOL),
        'allow_evidence_later' => filter_var(env('RML_LEAD_ALLOW_EVIDENCE_LATER', true), FILTER_VALIDATE_BOOL),
        'allow_seller_company_leads' => filter_var(env('RML_LEAD_ALLOW_SELLER_COMPANY', true), FILTER_VALIDATE_BOOL),
        'allow_seller_agent_leads' => filter_var(env('RML_LEAD_ALLOW_SELLER_AGENT', true), FILTER_VALIDATE_BOOL),
        'rml_internal_creates_payouts' => filter_var(env('RML_LEAD_RML_INTERNAL_PAYOUTS', false), FILTER_VALIDATE_BOOL),
        'min_property_area_m2' => (float) env('RML_LEAD_MIN_AREA_M2', 1),
        'max_property_area_m2' => (float) env('RML_LEAD_MAX_AREA_M2', 10000),
        'reservation_hours' => (int) env('RML_LEAD_RESERVATION_HOURS', 24),
        'sale_lock_hours' => (int) env('RML_LEAD_SALE_LOCK_HOURS', 48),
        'allow_rejected_resubmit' => filter_var(env('RML_LEAD_ALLOW_REJECTED_RESUBMIT', true), FILTER_VALIDATE_BOOL),
        'duplicate_detection' => env('RML_LEAD_DUPLICATE_DETECTION', 'soft'), // off|soft|strict
        'require_onsite_survey' => filter_var(env('RML_LEAD_REQUIRE_ONSITE_SURVEY', false), FILTER_VALIDATE_BOOL),
    ],
    'catastro' => [
        'enabled' => filter_var(env('RML_CATASTRO_ENABLED', env('CATASTRO_ENABLED', true)), FILTER_VALIDATE_BOOL),
        'area_tolerance_percent' => (float) env('RML_CATASTRO_AREA_TOLERANCE_PERCENT', 15),
        'coordinate_warn_meters' => (float) env('RML_CATASTRO_COORDINATE_WARN_METERS', 150),
        'require_for_audit_approval' => filter_var(env('RML_CATASTRO_REQUIRE_FOR_AUDIT', false), FILTER_VALIDATE_BOOL),
    ],
    'packages' => [
        'default_status' => env('RML_PACKAGE_DEFAULT_STATUS', 'available'),
        'allow_mixed_scheme' => filter_var(env('RML_PACKAGE_ALLOW_MIXED_SCHEME', true), FILTER_VALIDATE_BOOL),
        'allow_without_buyer' => filter_var(env('RML_PACKAGE_ALLOW_WITHOUT_BUYER', true), FILTER_VALIDATE_BOOL),
        'reservation_lock' => filter_var(env('RML_PACKAGE_RESERVATION_LOCK', true), FILTER_VALIDATE_BOOL),
        'expiry_days' => (int) env('RML_PACKAGE_EXPIRY_DAYS', 14),
        'allow_manual_creation' => filter_var(env('RML_PACKAGE_ALLOW_MANUAL', true), FILTER_VALIDATE_BOOL),
        'allow_installer_based_creation' => filter_var(env('RML_PACKAGE_ALLOW_INSTALLER', true), FILTER_VALIDATE_BOOL),
        'allow_without_installer' => filter_var(env('RML_PACKAGE_ALLOW_WITHOUT_INSTALLER', true), FILTER_VALIDATE_BOOL),
        'allow_mixed_zone' => filter_var(env('RML_PACKAGE_ALLOW_MIXED_ZONE', true), FILTER_VALIDATE_BOOL),
        'min_leads' => (int) env('RML_PACKAGE_MIN_LEADS', 1),
        'max_leads' => (int) env('RML_PACKAGE_MAX_LEADS', 100),
        'min_area_m2' => (float) env('RML_PACKAGE_MIN_AREA_M2', 0),
        'max_area_m2' => (float) env('RML_PACKAGE_MAX_AREA_M2', 100000),
        'default_search_radius_km' => (float) env('RML_PACKAGE_DEFAULT_RADIUS_KM', 50),
        'max_lead_distance_km' => (float) env('RML_PACKAGE_MAX_DISTANCE_KM', 200),
        'release_leads_on_expiry' => filter_var(env('RML_PACKAGE_RELEASE_ON_EXPIRY', true), FILTER_VALIDATE_BOOL),
        'reservation_hours' => (int) env('RML_PACKAGE_RESERVATION_HOURS', 48),
    ],
    'pricing' => [
        'calculation_method' => env('RML_PRICING_METHOD', 'per_m2'), // fixed|per_m2
        'fixed_selling_price' => env('RML_PRICING_FIXED_PRICE') !== null
            ? (float) env('RML_PRICING_FIXED_PRICE')
            : null,
        'minimum_selling_price' => (float) env('RML_PRICING_MIN_SELLING', 0),
        'maximum_discount_percent' => (float) env('RML_PRICING_MAX_DISCOUNT', 25),
        'allow_manual_override' => filter_var(env('RML_PRICING_ALLOW_OVERRIDE', true), FILTER_VALIDATE_BOOL),
        'package_discount_percent_max' => (float) env('RML_PRICING_PACKAGE_DISCOUNT_MAX', 15),
        'tax_percent' => (float) env('RML_PRICING_TAX_PERCENT', 0),
        'currency' => 'EUR',
    ],
    'payouts' => [
        'method' => env('RML_PAYOUT_METHOD', 'per_m2'), // fixed|per_m2|percentage
        'fixed_amount' => env('RML_PAYOUT_FIXED') !== null ? (float) env('RML_PAYOUT_FIXED') : null,
        'rate_per_m2' => (float) env('RML_PAYOUT_RATE_PER_M2', 2.0),
        'percentage' => env('RML_PAYOUT_PERCENTAGE') !== null
            ? (float) env('RML_PAYOUT_PERCENTAGE')
            : null,
        'rml_internal_payouts' => filter_var(env('RML_PAYOUT_RML_INTERNAL', false), FILTER_VALIDATE_BOOL),
        'company_payouts' => filter_var(env('RML_PAYOUT_COMPANY', true), FILTER_VALIDATE_BOOL),
        'agent_payouts' => filter_var(env('RML_PAYOUT_AGENT', true), FILTER_VALIDATE_BOOL),
        'staff_payout_to_company' => filter_var(env('RML_PAYOUT_STAFF_TO_COMPANY', true), FILTER_VALIDATE_BOOL),
        'allow_manual_override' => filter_var(env('RML_PAYOUT_ALLOW_OVERRIDE', true), FILTER_VALIDATE_BOOL),
    ],
    'reservations' => [
        'lead_reservation_hours' => (int) env('RML_RESERVATION_LEAD_HOURS', 24),
        'package_reservation_hours' => (int) env('RML_RESERVATION_PACKAGE_HOURS', 48),
        'abandoned_expires_hours' => (int) env('RML_RESERVATION_ABANDONED_HOURS', 72),
        'return_leads_to_listed' => filter_var(env('RML_RESERVATION_RETURN_LEADS', true), FILTER_VALIDATE_BOOL),
        'return_packages_to_available' => filter_var(env('RML_RESERVATION_RETURN_PACKAGES', true), FILTER_VALIDATE_BOOL),
        'allow_edit_reserved_package' => filter_var(env('RML_RESERVATION_EDIT_RESERVED', false), FILTER_VALIDATE_BOOL),
        'on_payment_fail' => env('RML_RESERVATION_ON_PAYMENT_FAIL', 'release'), // release|keep_locked
    ],
];
