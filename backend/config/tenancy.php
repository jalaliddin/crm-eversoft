<?php

return [
    // Tenants may be served from {slug}.{base_domain}. Empty disables host-based resolution.
    'base_domain' => env('TENANT_BASE_DOMAIN', 'crm.eversoft.uz'),

    // Hosts that never identify a tenant (API host, local development).
    'central_domains' => array_filter(explode(',', (string) env('TENANT_CENTRAL_DOMAINS', 'localhost,127.0.0.1'))),
];
