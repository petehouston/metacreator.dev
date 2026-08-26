<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Settings\Settings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Honours the `features.blog_enabled` setting.
 *
 * Turning the blog off has to make its URLs genuinely absent — a 404, not an empty
 * list — or search engines keep the old pages indexed. Applied to the whole blog
 * route group so no individual action has to remember the check.
 */
final class EnsureBlogEnabled
{
    public function __construct(private readonly Settings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->bool('features.blog_enabled', true)) {
            throw new NotFoundHttpException('The blog is not available.');
        }

        return $next($request);
    }
}
