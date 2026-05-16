<?php

// CORS rules for the API. Read by Laravel's HandleCors middleware.

return [

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Which domains are allowed to call this API from a browser.
    | Add the production Vercel URL here when you deploy.
    |
    */
    'allowed_origins' => [
        'http://localhost:3000',
        'https://grantly.au',
        'https://www.grantly.au',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Methods
    |--------------------------------------------------------------------------
    |
    | HTTP methods the browser is permitted to send.
    | '*' means all standard methods (GET, POST, PUT, PATCH, DELETE, OPTIONS).
    |
    */
    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Headers
    |--------------------------------------------------------------------------
    |
    | Request headers the browser is allowed to send.
    | We need Authorization for our Bearer token and Content-Type for JSON bodies.
    |
    */
    'allowed_headers' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Exposed Headers
    |--------------------------------------------------------------------------
    |
    | Response headers the browser script is allowed to read.
    | Empty means only the "safe" headers (Content-Type etc.) are readable.
    |
    */
    'exposed_headers' => [],

    /*
    |--------------------------------------------------------------------------
    | Max Age
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) the browser should cache the preflight OPTIONS
    | response. 0 = no caching; bumping this reduces preflight round-trips.
    |
    */
    'max_age' => 0,

    /*
    |--------------------------------------------------------------------------
    | Supports Credentials
    |--------------------------------------------------------------------------
    |
    | Whether cookies/auth headers can be sent cross-origin.
    | We use Bearer tokens in headers, not cookies, so this stays false.
    |
    */
    'supports_credentials' => false,

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins Patterns
    |--------------------------------------------------------------------------
    |
    | Regex patterns for allowed origins. Useful if you have many subdomains.
    | We're not using patterns, so this is empty.
    |
    */
    'allowed_origins_patterns' => [],

    /*
    |--------------------------------------------------------------------------
    | Paths
    |--------------------------------------------------------------------------
    |
    | Which URL paths CORS headers should be added to.
    | 'api/*' covers all our versioned endpoints.
    |
    */
    'paths' => ['api/*'],

];
