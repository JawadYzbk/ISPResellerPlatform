<?php

return [
    // Keep the flow available, but make mandatory web enrollment an explicit deployment choice.
    'enforce_web_two_factor' => env('SECURITY_ENFORCE_TWO_FACTOR', false),

    // Browser history encryption requires WebCrypto, which is not available in every local HTTP setup.
    // Production deployments should enable it when served over HTTPS.
    'encrypt_inertia_history' => env('SECURITY_ENCRYPT_INERTIA_HISTORY'),

    // Keep shared-office NAT usable for staff while the per-account limit contains spraying.
    'login_ip_limit' => (int) env('SECURITY_LOGIN_IP_LIMIT', 60),
];
