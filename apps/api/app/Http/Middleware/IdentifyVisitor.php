<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Attaches a privacy-preserving visitor identifier to the request.
 *
 * We need to count unique anonymous users and enforce per-visitor quotas, but we do
 * not need — and therefore do not keep — their IP address. An HMAC of IP+user-agent
 * under a salt that rotates daily gives us exactly one day of correlation and is
 * useless afterwards, which is the whole point (see docs/21).
 */
final class IdentifyVisitor
{
    public const ATTRIBUTE = 'visitor_hash';

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::ATTRIBUTE, $this->hash($request));

        return $next($request);
    }

    private function hash(Request $request): string
    {
        $salt = config('app.key').date('Y-m-d');

        return hash_hmac(
            'sha256',
            $request->ip().'|'.$request->userAgent(),
            $salt,
        );
    }
}
