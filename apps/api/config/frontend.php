<?php

declare(strict_types=1);

/**
 * How this API reaches the Next.js front end.
 *
 * The only thing it needs the front end for is on-demand ISR: public pages cache
 * API responses for minutes at a time, so a publish is invisible until the cache
 * entries behind it are dropped. `revalidate_url` is that endpoint, and the secret
 * is the shared signature on the call.
 */
return [

    /**
     * The front end's `/api/revalidate` route.
     *
     * In production this is loopback — the Next server runs on the same droplet —
     * so a revalidation never leaves the box. Empty disables revalidation entirely,
     * which is the right behaviour for tests and for a CLI-only install.
     */
    'revalidate_url' => env('REVALIDATE_URL'),

    /**
     * Must match `REVALIDATE_SECRET` in the front end's environment. The route
     * fails closed on a mismatch, so a half-rotated pair silently stops publishing
     * from taking effect — rotate both together.
     */
    'revalidate_secret' => env('REVALIDATE_SECRET'),

    /**
     * Seconds to wait on that call.
     *
     * Short on purpose: it runs after the admin's response has been flushed, and a
     * front end that is slow or down must not hold a php-fpm worker open. A missed
     * revalidation degrades to the old timed behaviour rather than breaking a save.
     */
    'revalidate_timeout' => (float) env('REVALIDATE_TIMEOUT', 5),

];
