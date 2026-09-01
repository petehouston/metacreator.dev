<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Mail\Transports;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;

/**
 * Postmark, via the JSON `/email` endpoint.
 *
 * Postmark has no MIME endpoint, so the message is taken apart into the fields it
 * accepts. The message stream matters more here than it looks: Postmark separates
 * transactional from broadcast traffic and will refuse a send to the wrong stream,
 * which is the mechanism that keeps a password reset out of a reputation pool
 * shared with marketing mail.
 */
final class PostmarkTransport extends ApiTransport
{
    public function __construct(
        private readonly string $token,
        private readonly string $messageStream = 'outbound',
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'postmark+api://api.postmarkapp.com?stream='.$this->messageStream;
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $this->email($message);

        $payload = array_filter([
            'From' => $this->sender($message),
            'To' => implode(',', $this->stringify($email->getTo())),
            'Cc' => implode(',', $this->stringify($email->getCc())),
            'Bcc' => implode(',', $this->stringify($email->getBcc())),
            'ReplyTo' => implode(',', $this->stringify($email->getReplyTo())),
            'Subject' => $email->getSubject() ?? '',
            'HtmlBody' => $email->getHtmlBody(),
            'TextBody' => $email->getTextBody(),
            'MessageStream' => $this->messageStream,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $attachments = $this->attachments($email);

        if ($attachments !== []) {
            $payload['Attachments'] = array_map(static fn (array $file): array => [
                'Name' => $file['name'],
                'Content' => $file['content'],
                'ContentType' => $file['type'],
            ], $attachments);
        }

        $response = Http::withHeaders([
            'X-Postmark-Server-Token' => $this->token,
            'Accept' => 'application/json',
        ])->post('https://api.postmarkapp.com/email', $payload);

        // Postmark answers 200 with a non-zero ErrorCode for things like a
        // suppressed recipient, so the status alone is not the verdict.
        $errorCode = $response->json('ErrorCode');

        if ($response->failed() || (is_int($errorCode) && $errorCode !== 0)) {
            throw new TransportException(
                'Postmark rejected the message ('.$response->status().'): '.$response->body(),
            );
        }

        $id = $response->json('MessageID');

        if (is_string($id)) {
            $message->setMessageId($id);
        }
    }
}
