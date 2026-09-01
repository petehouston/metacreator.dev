<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Newsletter;

use App\Domain\Newsletter\Actions\ConfirmNewsletterSubscriptionAction;
use App\Domain\Newsletter\Actions\SubscribeToNewsletterAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Newsletter\SubscribeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The public end of the newsletter: signing up, and confirming.
 *
 * Every answer here is deliberately uninformative about who is already on the list
 * — see {@see SubscribeToNewsletterAction}. The only thing the response varies on
 * is whether a confirmation email was sent, which is a property of the site's
 * settings rather than of the address.
 */
final class NewsletterSubscriptionController extends Controller
{
    /**
     * The exact wording shown next to the form, recorded with the signup.
     *
     * Kept server-side and versioned with the code rather than taken from the
     * request: a consent record the subscriber's own browser can dictate proves
     * nothing at all.
     */
    private const CONSENT_TEXT = 'New tools and creator tactics, once a week. '
        .'No spam. Unsubscribe in one click.';

    public function store(SubscribeRequest $request, SubscribeToNewsletterAction $subscribe): JsonResponse
    {
        $result = $subscribe->execute(
            email: $request->string('email')->toString(),
            name: $request->input('name'),
            source: $request->input('source'),
            sourceUrl: $request->input('source_url'),
            ipHash: $this->ipHash($request),
            consentText: self::CONSENT_TEXT,
        );

        return response()->json([
            'data' => [
                'subscribed' => true,
                'requires_confirmation' => $result['requires_confirmation'],
            ],
        ], 202);
    }

    public function confirm(Request $request, ConfirmNewsletterSubscriptionAction $confirm): JsonResponse
    {
        $request->validate(['token' => ['required', 'string', 'size:64']]);

        $subscriber = $confirm->execute($request->string('token')->toString());

        return response()->json([
            'data' => [
                'confirmed' => true,
                'email' => $subscriber->email,
            ],
        ]);
    }

    /**
     * Part of the consent record (docs/14), and the reason it is a hash: proving
     * *that* a request came from one address is enough, storing the address itself
     * is more personal data than the obligation needs.
     */
    private function ipHash(Request $request): ?string
    {
        $ip = $request->ip();

        return $ip === null ? null : hash_hmac('sha256', $ip, (string) config('app.key'));
    }
}
