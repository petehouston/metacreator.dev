<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Mail\Transports;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;

/** Resend, via the JSON `/emails` endpoint. */
final class ResendTransport extends ApiTransport
{
    public function __construct(private readonly string $key)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'resend+api://api.resend.com';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $this->email($message);

        $payload = array_filter([
            'from' => $this->sender($message),
            'to' => $this->stringify($email->getTo()),
            'cc' => $this->stringify($email->getCc()),
            'bcc' => $this->stringify($email->getBcc()),
            'reply_to' => $this->stringify($email->getReplyTo()),
            'subject' => $email->getSubject() ?? '',
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
        ], static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        $attachments = $this->attachments($email);

        if ($attachments !== []) {
            $payload['attachments'] = array_map(static fn (array $file): array => [
                'filename' => $file['name'],
                'content' => $file['content'],
                'content_type' => $file['type'],
            ], $attachments);
        }

        $response = Http::withToken($this->key)->post('https://api.resend.com/emails', $payload);

        if ($response->failed()) {
            throw new TransportException(
                'Resend rejected the message ('.$response->status().'): '.$response->body(),
            );
        }

        $id = $response->json('id');

        if (is_string($id)) {
            $message->setMessageId($id);
        }
    }
}
