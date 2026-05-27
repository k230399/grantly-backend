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

    'frontend' => [
        // Public-facing app URL used to build links in transactional emails.
        'url' => env('FRONTEND_URL', 'http://localhost:3000'),
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

    'supabase' => [
        'url'          => env('SUPABASE_URL'),
        'anon_key'     => env('SUPABASE_ANON_KEY'),
        // The JWT secret is used to verify tokens issued by Supabase Auth.
        // Find it in: Supabase Dashboard → Project Settings → API → JWT Secret
        'jwt_secret'   => env('SUPABASE_JWT_SECRET'),
        // Service role key (server-only) for Supabase Storage REST writes + signed URLs.
        'service_key'  => env('SUPABASE_SERVICE_KEY'),
        'storage_bucket' => env('SUPABASE_STORAGE_BUCKET', 'grantly'),
    ],

    // Australian Business Register lookup. The GUID is a free per-developer key,
    // register at https://abr.business.gov.au/Tools/WebServices.
    'abr' => [
        'guid'      => env('ABR_GUID'),
        'base_url'  => env('ABR_BASE_URL', 'https://abr.business.gov.au/json'),
        // Cache successful lookups for an hour so a typing applicant plus the
        // PATCH /profile verify only triggers one outbound call to the ABR.
        'cache_ttl' => (int) env('ABR_CACHE_TTL', 3600),
    ],

];
