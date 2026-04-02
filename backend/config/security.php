<?php

return [

    'request_id_header' => env('REQUEST_ID_HEADER', 'X-Request-Id'),

    'trusted_proxies' => array_values(array_filter(array_map(
        static fn (string $proxy) => trim($proxy),
        explode(',', (string) env('TRUSTED_PROXIES', ''))
    ))),

    'trusted_hosts' => array_values(array_filter(array_map(
        static fn (string $host) => trim($host),
        explode(',', (string) env('TRUSTED_HOSTS', ''))
    ))),

    'headers' => [
        'content_security_policy' => env(
            'SECURITY_CONTENT_SECURITY_POLICY',
            "default-src 'self'; base-uri 'self'; frame-ancestors 'self'; object-src 'none'; form-action 'self'"
        ),
        'permissions_policy' => env('SECURITY_PERMISSIONS_POLICY', 'camera=(), microphone=(), geolocation=()'),
        'referrer_policy' => env('SECURITY_REFERRER_POLICY', 'strict-origin-when-cross-origin'),
        'frame_options' => env('SECURITY_FRAME_OPTIONS', 'SAMEORIGIN'),
        'content_type_options' => env('SECURITY_CONTENT_TYPE_OPTIONS', 'nosniff'),
        'cross_origin_opener_policy' => env('SECURITY_CROSS_ORIGIN_OPENER_POLICY', 'same-origin'),
        'cross_origin_resource_policy' => env('SECURITY_CROSS_ORIGIN_RESOURCE_POLICY', 'same-origin'),
        'hsts' => [
            'enabled' => (bool) env('SECURITY_HSTS_ENABLED', true),
            'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
            'include_subdomains' => (bool) env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
            'preload' => (bool) env('SECURITY_HSTS_PRELOAD', false),
        ],
    ],

    'readiness' => [
        'check_cache' => (bool) env('READINESS_CHECK_CACHE', true),
        'cache_store' => env('READINESS_CACHE_STORE', env('CACHE_STORE', 'database')),
    ],

];
