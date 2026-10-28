<?php

return [
    // Leave empty for direct HTTPS hosting. Trust only the actual proxy IPs/CIDRs.
    'proxies' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('TRUSTED_PROXIES', ''))))),
];
