<?php

use App\Http\ApiExceptionRenderer;
use App\Http\Middleware\EnsureBlogEnabled;
use App\Http\Middleware\EnsureChangelogEnabled;
use App\Http\Middleware\EnsureNewsletterEnabled;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\IdentifyVisitor;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('api/v1')
                ->group(base_path('routes/api_v1.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum's stateful guard lets the first-party frontend authenticate with a
        // cookie instead of a token in JavaScript-readable storage (see docs/21).
        $middleware->api(prepend: [
            // Sanctum's stateful pipeline already includes its own
            // AuthenticateSession (config/sanctum.php), which ties each session to the
            // password hash it was created under — that is what makes a password
            // change log every *other* session out. The framework's version cannot be
            // used here: it assumes a SessionGuard, and the default guard is `sanctum`.
            EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->api(append: [
            ForceJsonResponse::class,
            IdentifyVisitor::class,
        ]);

        $middleware->alias([
            'permission' => PermissionMiddleware::class,
            'role' => RoleMiddleware::class,
            'password.confirm' => RequirePassword::class,
            'blog.enabled' => EnsureBlogEnabled::class,
            'changelog.enabled' => EnsureChangelogEnabled::class,
            'newsletter.enabled' => EnsureNewsletterEnabled::class,
        ]);

        // A tool run's `input` is validated against the tool's own JSON Schema, and
        // that schema says what an optional field's empty value looks like — almost
        // always `""`. Laravel's default of rewriting empty strings to null turns
        // "I cleared this optional field" into `null is not a string`, which the
        // user sees as a validation error on a field they were told to leave blank.
        // The body is data here, not a form, so it reaches the runner as it was sent.
        $middleware->convertEmptyStringsToNull(except: [
            fn (Request $request): bool => $request->is('api/v1/tools/*/run'),
        ]);

        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // One error envelope for the whole API, so clients never branch on shape.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiExceptionRenderer::render($e, $request);
        });
    })->create();
