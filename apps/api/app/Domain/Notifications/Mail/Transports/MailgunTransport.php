<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Mail\Transports;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;

/**
 * Mailgun, via the `messages.mime` endpoint.
 *
 * The MIME endpoint rather than the field-by-field one: the message Laravel has
 * already built carries the alternative parts, inline images, custom headers and
 * attachments, and handing Mailgun the assembled message keeps all of it instead of
 * re-deriving a lossy subset. The recipient list still has to be passed separately —
 * that is what Mailgun addresses, not the To header.
 */
final class MailgunTransport extends ApiTransport
{
    public function __construct(
        private readonly string $domain,
        private readonly string $secret,
        /** `api.mailgun.net`, or `api.eu.mailgun.net` for an EU region account. */
        private readonly string $endpoint = 'api.mailgun.net',
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'mailgun+api://'.$this->endpoint.'?domain='.$this->domain;
    }

    protected function doSend(SentMessage $message): void
    {
        $response = Http::asMultipart()
            ->withBasicAuth('api', $this->secret)
            ->attach('message', $message->toString(), 'message.mime', ['Content-Type' => 'message/rfc822'])
            ->post("https://{$this->endpoint}/v3/{$this->domain}/messages.mime", [
                // Every envelope recipient, so Bcc is delivered without appearing
                // in the headers of the message the others receive.
                'to' => implode(',', $this->stringify($message->getEnvelope()->getRecipients())),
            ]);

        if ($response->failed()) {
            throw new TransportException(
                'Mailgun rejected the message ('.$response->status().'): '.$response->body(),
            );
        }

        $id = $response->json('id');

        if (is_string($id)) {
            $message->setMessageId(trim($id, '<>'));
        }
    }
}
