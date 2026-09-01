<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Mail\Transports;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

/**
 * Shared plumbing for the HTTP-API mail transports.
 *
 * Laravel ships SMTP, sendmail, log and SES; the rest of the providers an operator
 * might pick — Mailgun, Postmark, Resend, Klaviyo — normally arrive as a Symfony
 * bridge or a vendor package each. Four more dependencies to let one dropdown
 * change, when each provider's send endpoint is a single JSON or multipart POST,
 * is a poor trade: the packages have to be installed for providers nobody on this
 * deployment uses, and they pin their own HTTP stacks. These transports use the
 * framework's HTTP client instead, so every provider in the settings dropdown is
 * live on a fresh `composer install` and `Http::fake()` covers them all in tests.
 *
 * Subclasses implement {@see doSend()} and get address and attachment marshalling
 * from here.
 */
abstract class ApiTransport extends AbstractTransport
{
    /**
     * The Symfony message being sent, normalised to an Email.
     *
     * The envelope can technically carry a pre-serialised RawMessage, which has no
     * subject or body to read and so cannot be mapped onto a provider's JSON fields.
     * Nothing in this application sends one — every message originates as a Mailable —
     * but the API transports genuinely cannot handle it, and saying so beats reading
     * fields off something that has none.
     */
    protected function email(SentMessage $message): Email
    {
        $original = $message->getOriginalMessage();

        if (! $original instanceof Message) {
            throw new TransportException(
                'This transport needs a structured message; a pre-serialised one cannot be mapped '
                .'onto the provider API. Use SMTP for raw messages.',
            );
        }

        return MessageConverter::toEmail($original);
    }

    /**
     * `Name <address>` for each address, which is what every provider accepts.
     *
     * @param  array<int, Address>  $addresses
     * @return list<string>
     */
    protected function stringify(array $addresses): array
    {
        return array_values(array_map(static fn (Address $address): string => $address->toString(), $addresses));
    }

    /** The envelope sender, which is the From unless a Return-Path was set. */
    protected function sender(SentMessage $message): string
    {
        $from = $this->email($message)->getFrom();

        return $from === [] ? $message->getEnvelope()->getSender()->toString() : $from[0]->toString();
    }

    /**
     * Attachments as `[filename, base64 content, content type]` triples.
     *
     * @return list<array{name: string, content: string, type: string}>
     */
    protected function attachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $part) {
            $attachments[] = [
                'name' => $part->getFilename() ?? 'attachment',
                'content' => base64_encode($part->getBody()),
                'type' => $part->getMediaType().'/'.$part->getMediaSubtype(),
            ];
        }

        return $attachments;
    }
}
