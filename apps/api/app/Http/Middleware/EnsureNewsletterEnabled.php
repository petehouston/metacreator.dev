<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Honours the `features.newsletter_enabled` setting.
 *
 * With the newsletter off the endpoints are absent rather than inert, matching how
 * the blog and changelog groups behave — the frontend hides its forms off the same
 * public flag, so a request arriving here at all means something is out of date.
 */
final class EnsureNewsletterEnabled
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->bool('features.newsletter_enabled', true)) {
            throw new NotFoundHttpException('The newsletter is not available.');
        }

        return $next($request);
    }
}
