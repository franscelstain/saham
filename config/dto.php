<?php

return [
    'strict_ban' => env('DTO_STRICT_BAN', false),

    // list namespace prefixes
    'strict_ban_namespaces' => array_values(array_filter(array_map('trim',
        preg_split('/[;,]+/', (string) env('DTO_STRICT_BAN_NAMESPACES', ''))
    ))),
    
    'watchlist' => [
        'scorecard' => [
            'dto_strict_ban' => env('WATCHLIST_SCORECARD_DTO_STRICT_BAN', false),
            'dto_legacy_log' => env('WATCHLIST_SCORECARD_DTO_LEGACY_LOG', true), // guardrail
        ],
    ],
];
