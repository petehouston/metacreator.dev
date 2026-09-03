<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Honours the `features.search_enabled` setting.
 *
 * Unlike the blog and changelog switches, this one defaults to **off**. Search is
 * the one feature that reads across every table the site publishes, so an operator
 * gets to look at it before it is exposed — and a fresh install that has not been
 * populated yet would offer a search box that finds nothing, which is a worse first
 * impression than no search box.
 *
 * A 404 rather than a 403, for the same reason as the others: the frontend hides
 * the search affordance when the flag is off, and a URL that is meant to be absent
 * should say it is absent.
 */
final class EnsureSearchEnabled
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! $this->settings->bool('features.search_enabled', false)) {
            throw new NotFoundHttpException('Search is not available.');
        }

        return $next($request);
    }
}
