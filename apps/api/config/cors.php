<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing
|--------------------------------------------------------------------------
|
| The frontend is a separate origin, so it needs CORS — and because it
| authenticates with a Sanctum session cookie, `supports_credentials` must be
| true. That in turn makes a wildcard origin illegal: browsers reject
| `Access-Control-Allow-Origin: *` on a credentialed request. Origins are
| therefore listed explicitly, from config, and never guessed.
|
*/

$frontendUrl = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout', 'webhooks/*'],

    'allowed_methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_unique(array_filter([
        $frontendUrl,
        // 127.0.0.1 and localhost are different origins to a browser, and local
        // tooling uses both. Only added outside production.
        ...(env('APP_ENV') === 'local'
            ? ['http://localhost:3000', 'http://127.0.0.1:3000']
            : []),
        ...array_filter(explode(',', (string) env('CORS_ADDITIONAL_ORIGINS', ''))),
    ]))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept', 'Authorization', 'Content-Type', 'X-Requested-With',
        'X-XSRF-TOKEN', 'X-Request-Id', 'Idempotency-Key',
    ],

    'exposed_headers' => [
        'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset',
        'Retry-After', 'X-Request-Id',
    ],

    'max_age' => 3600,

    // Required for the session-cookie auth described in docs/06.
    'supports_credentials' => true,

];
