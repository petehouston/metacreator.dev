<?php

declare(strict_types=1);

/**
 * Test bootstrap.
 *
 * The api/worker containers export APP_ENV, DB_DATABASE, CACHE_STORE and friends as
 * real environment variables. Laravel resolves configuration through `$_SERVER`
 * first, and neither `.env.testing` (php-dotenv is immutable) nor PHPUnit's
 * `<env force="true">` (which writes `$_ENV` and `putenv`, not `$_SERVER`) can
 * displace them. The suite would therefore run as `local`, against the *development*
 * database, with Redis sessions — and every CSRF-protected POST would fail with a
 * 419, because the framework only skips that check when the environment is
 * `testing`.
 *
 * Setting them here, before the autoloader hands control to Laravel, is the one
 * place that works for every invocation path: `artisan test`, `vendor/bin/pest`,
 * and PHPUnit run directly.
 */
$overrides = [
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    // Argon2id is deliberately slow; the suite would take minutes at real cost.
    'BCRYPT_ROUNDS' => '4',
    'HASH_DRIVER' => 'bcrypt',
    'BROADCAST_CONNECTION' => 'null',
    'CACHE_STORE' => 'array',
    // Real MySQL, not SQLite: the migrations rely on fulltext indexes and JSON
    // columns whose behaviour differs, and a suite that passes on a different engine
    // than production proves very little.
    'DB_CONNECTION' => 'mysql',
    'DB_DATABASE' => 'metacreator_test',
    'DB_URL' => '',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'PULSE_ENABLED' => 'false',
    'TELESCOPE_ENABLED' => 'false',
    'NIGHTWATCH_ENABLED' => 'false',
];

foreach ($overrides as $key => $value) {
    $_SERVER[$key] = $value;
    $_ENV[$key] = $value;
    putenv("{$key}={$value}");
}

require __DIR__.'/../vendor/autoload.php';
