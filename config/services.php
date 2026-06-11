<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Token for wine:push-golden — pushing golden snapshots to a remote CellarOS.
    'cellaros' => [
        'ingest_token' => env('CELLAROS_INGEST_TOKEN'),
    ],

    /*
     * Anthropic (Claude) — powers supplier-document portfolio parsing
     * (Domain\Supplier\Services\DocumentAnalysisService). Without a key the
     * parser fails closed and the document lands on Failed with a clear reason.
     */
    'lwin' => [
        // The Liv-ex LWIN database (Creative Commons) published file.
        'url' => env('LWIN_DATABASE_URL', 'https://s3-eu-west-1.amazonaws.com/lwin-dictionary/latest/LWINdatabase.xlsx'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-4-8'),
    ],

];
