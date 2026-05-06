<?php

return [

    /*
    |--------------------------------------------------------------------------
    | إعدادات منصّة سحاب
    |--------------------------------------------------------------------------
    | يضاف لـ config/services.php أو يُحمّل من خلال هذا الملف
    */

    'sahab' => [
        // التوكن المستخدم لحماية مسارات /cron/*
        'cron_token' => env('CRON_TOKEN'),

        // قنوات الواتساب
        'whatsapp' => [
            'provider' => env('WHATSAPP_PROVIDER', 'unifonic'), // unifonic | twilio | wa.me
            'unifonic_app_sid' => env('UNIFONIC_APP_SID'),
            'unifonic_sender_id' => env('UNIFONIC_SENDER_ID', 'Sahab'),
            'twilio_account_sid' => env('TWILIO_ACCOUNT_SID'),
            'twilio_auth_token' => env('TWILIO_AUTH_TOKEN'),
            'twilio_from' => env('TWILIO_FROM_WHATSAPP'),
        ],

        // بوّابة الدفع للاشتراكات
        'moyasar' => [
            'api_key' => env('MOYASAR_API_KEY'),
            'webhook_secret' => env('MOYASAR_WEBHOOK_SECRET'),
        ],

        // زاتكا
        'zatca' => [
            'environment' => env('ZATCA_ENV', 'sandbox'), // sandbox | production
            'api_url_sandbox' => 'https://gw-fatoora-sb.zatca.gov.sa',
            'api_url_production' => 'https://gw-fatoora.zatca.gov.sa',
        ],

        // التوصيل
        'delivery' => [
            'hungerstation' => [
                'api_url' => env('HUNGERSTATION_API_URL', 'https://api.hungerstation.com/v1'),
                'webhook_secret' => env('HUNGERSTATION_WEBHOOK_SECRET'),
            ],
            'jahez' => [
                'api_url' => env('JAHEZ_API_URL', 'https://integration.jahez.net'),
                'webhook_secret' => env('JAHEZ_WEBHOOK_SECRET'),
            ],
            // ... إلخ لباقي المنصّات
        ],

        // OCR & AI
        'ai' => [
            'provider' => 'anthropic',
            'model' => env('CLAUDE_MODEL', 'claude-opus-4-7'),
        ],

        // التشفير
        'encryption_key' => env('SAHAB_ENCRYPTION_KEY', env('APP_KEY')),
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('CLAUDE_MODEL', 'claude-opus-4-7'),
        'api_url' => 'https://api.anthropic.com/v1/messages',
    ],
];
