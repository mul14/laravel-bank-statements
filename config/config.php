<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Client
    |--------------------------------------------------------------------------
    |
    */

    'client' => [
        'user_agent'    => env('BANK_CLIENT_USER_AGENT', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36'),
        'ip_address'    => env('BANK_CLIENT_IP_ADDRESS', '18.96.236.10'),
        'request_delay' => env('BANK_CLIENT_REQUEST_DELAY', 2), // seconds
        'options'       => [
            'timeout' => env('BANK_CLIENT_TIMEOUT', 15), // seconds
            'debug'   => env('BANK_CLIENT_DEBUG', false)
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Collector
    |--------------------------------------------------------------------------
    |
    */

    'collector' => [
        'type' => env('BANK_COLLECTOR', 'web'), // only available "web" at the moment
        'web'  => [
            'bca'     => Sule\BankStatements\Collector\Web\Bca::class,
            'mandiri' => Sule\BankStatements\Collector\Web\Mandiri::class
        ],
        'temp_storage_path' => env('BANK_TEMP_STORAGE_PATH', storage_path().'/app')
    ],

    /*
    |--------------------------------------------------------------------------
    | Bank Accounts
    |--------------------------------------------------------------------------
    |
    */

    'accounts' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table'    => env('BANK_ACCOUNTS_TABLE', 'bank_accounts')
    ],

    /*
    |--------------------------------------------------------------------------
    | Bank Statements
    |--------------------------------------------------------------------------
    |
    */

    'statements' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table'    => env('BANK_STATEMENTS_TABLE', 'bank_statements')
    ]

];
