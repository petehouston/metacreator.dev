<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Honours the `features.changelog_enabled` setting.
 *
 * Same reasoning as the blog: a product that would rather not publish its release
 * history needs those URLs genuinely absent — a 404, not an empty timeline — or the
 * pages stay indexed. Applied to the whole public changelog group; the admin routes
 * are untouched, so releases can still be written while the public page is off.
 */
final class EnsureChangelogEnabled
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->bool('features.changelog_enabled', true)) {
            throw new NotFoundHttpException('The changelog is not available.');
        }

        return $next($request);
    }
}
