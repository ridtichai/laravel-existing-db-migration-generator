<?php

return [
    'default_output_path' => 'database/migrations',
    'default_seeder_output_path' => 'database/seeders',

    'exclude_tables' => [
        'migrations',
        'password_resets',
        'failed_jobs',
        'personal_access_tokens',
    ],

    'use_laravel_style_macros' => true,

    /*
    |--------------------------------------------------------------------------
    | Seeder Options
    |--------------------------------------------------------------------------
    */
    'auto_register_seeder' => true,
    'multi_file_seed_threshold' => 1000,
];