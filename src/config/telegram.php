<?php

    return [
        'api_url' => env('TELEGRAM_PROXY'),
        'otk_service_bot' => [
            'token'   => env('TELEGRAM_BOT_TOKEN'),
        ],
        'test_bot' => [
            'token'   => env('TELEGRAM_TEST_BOT_TOKEN'),
        ],
        'debug' => env('TELEGRAM_DEBUG', false),
        'debug_uids' => explode(',', env('TELEGRAM_DEBUG_UIDS', '')),
    ];
