<?php

declare(strict_types=1);

namespace App\Domain\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * A browser the user has signed in from.
 *
 * @property int $user_id
 * @property string $fingerprint
 * @property string $label
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $revoked_at
 */
final class UserDevice extends Model
{
    protected $fillable = [
        'user_id', 'fingerprint', 'label', 'user_agent', 'ip', 'location', 'session_id', 'last_seen_at',
    ];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function fingerprintFor(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->userAgent() ?? 'unknown',
            $request->header('Sec-Ch-Ua-Platform', ''),
            $request->header('Accept-Language', ''),
        ]));
    }

    /**
     * A human label like "Chrome on macOS".
     *
     * Intentionally coarse. Parsing user agents precisely is a losing game, and the
     * only question the user is answering is "do I recognise this?".
     */
    public static function labelFor(?string $userAgent): string
    {
        $agent = $userAgent ?? '';

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $platform = match (true) {
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS X') => 'macOS',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'an unknown device',
        };

        return "{$browser} on {$platform}";
    }

    public function isCurrent(Request $request): bool
    {
        return $this->fingerprint === self::fingerprintFor($request);
    }
}
