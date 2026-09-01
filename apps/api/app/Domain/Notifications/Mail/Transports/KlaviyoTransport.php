<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Mail\Transports;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;

/**
 * Klaviyo, via the Events API.
 *
 * Klaviyo is not an SMTP relay and has no endpoint that takes a rendered message
 * and delivers it. Its transactional path is indirect by design: the application
 * posts an *event* against a metric, and a flow inside Klaviyo — triggered by that
 * metric — renders and sends the actual email. So this transport does not deliver
 * anything on its own. It hands Klaviyo the subject and the rendered bodies as
 * event properties and relies on a flow to put them in front of the recipient.
 *
 * That has consequences an operator has to know before selecting it:
 *
 *  - **A flow must exist.** Until someone builds a flow on the configured metric
 *    whose template outputs `{{ event.html_body }}`, every send here succeeds at the
 *    API and delivers no mail. Nothing in this codebase can detect that.
 *  - **Klaviyo owns the send.** Delivery timing, throttling and suppression are
 *    decided there, so a password reset is only as prompt as the flow allows.
 *  - **Attachments cannot travel this way,** so a message carrying one is rejected
 *    here rather than delivered with the attachment quietly missing.
 *
 * For those reasons this suits lifecycle mail an operator already runs in Klaviyo,
 * and a dedicated sending provider is the better choice for password resets and
 * receipts. One event is posted per recipient — Klaviyo addresses a profile, not a
 * recipient list.
 */
final class KlaviyoTransport extends ApiTransport
{
    /** Pinned: Klaviyo versions its API by date and an unpinned call is a future outage. */
    private const REVISION = '2024-10-15';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $metric = 'Transactional Email',
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'klaviyo+api://a.klaviyo.com?metric='.rawurlencode($this->metric);
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $this->email($message);

        if ($email->getAttachments() !== []) {
            throw new TransportException(
                'Klaviyo delivers through a flow template and cannot carry attachments. '
                .'Choose a sending provider for messages with attachments.',
            );
        }

        $properties = array_filter([
            'subject' => $email->getSubject() ?? '',
            'html_body' => $email->getHtmlBody(),
            'text_body' => $email->getTextBody(),
            'from' => $this->sender($message),
            'reply_to' => implode(',', $this->stringify($email->getReplyTo())),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        foreach ($message->getEnvelope()->getRecipients() as $recipient) {
            $response = Http::withHeaders([
                'Authorization' => 'Klaviyo-API-Key '.$this->apiKey,
                'revision' => self::REVISION,
                'accept' => 'application/vnd.api+json',
            ])
                ->withBody(json_encode([
                    'data' => [
                        'type' => 'event',
                        'attributes' => [
                            'properties' => $properties,
                            'metric' => [
                                'data' => ['type' => 'metric', 'attributes' => ['name' => $this->metric]],
                            ],
                            'profile' => [
                                'data' => ['type' => 'profile', 'attributes' => ['email' => $recipient->getAddress()]],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR), 'application/vnd.api+json')
                ->post('https://a.klaviyo.com/api/events/');

            if ($response->failed()) {
                throw new TransportException(
                    'Klaviyo rejected the event for '.$recipient->getAddress()
                    .' ('.$response->status().'): '.$response->body(),
                );
            }
        }
    }
}
