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


    /*
    |--------------------------------------------------------------------------
    | Laravel Style Macros
    |--------------------------------------------------------------------------
    |
    | When enabled, the generator will try to output Laravel-style shortcuts:
    | - $table->id()
    | - $table->timestamps()
    | - $table->foreignId(...)->constrained(...)
    |
    | When disabled, the generator will stay closer to the original schema.
    |
    */
    'use_laravel_style_macros' => true,
    'omit_default_string_length' => true,
    /*
    |--------------------------------------------------------------------------
    | Seeder Options
    |--------------------------------------------------------------------------
    */
    'auto_register_seeder' => true,
    'multi_file_seed_threshold' => 1000,
];