<?php

return [
    'enabled' => env('MONITORING_ENABLED', false),
    'webhook_url' => env('MONITORING_ALERT_WEBHOOK_URL'),
    'webhook_secret' => env('MONITORING_ALERT_WEBHOOK_SECRET'),
    'timeout' => max(1, (int) env('MONITORING_ALERT_TIMEOUT', 10)),
];
