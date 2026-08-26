<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use InvalidArgumentException;

/**
 * One entry in the event catalog (see docs/13).
 *
 * Events are data, not classes. A feature that needs to notify someone declares the
 * event here and fires it by key; the delivery layer decides the channels. That is
 * what keeps "a feature never sends an ad-hoc email" enforceable rather than merely
 * aspirational — {@see EventCatalog::get()} throws on an unknown key.
 */
final readonly class NotificationEvent
{
    /**
     * @param  list<string>  $channels  Laravel channel names: `mail`, `database`.
     * @param  list<string>  $required  Payload keys that must be present.
     * @param  array{label: string, url: string}|null  $action  Call to action; `url` is frontend-relative.
     */
    public function __construct(
        public string $key,
        public string $group,
        public array $channels,
        public bool $optOut,
        public string $title,
        public string $body,
        public ?array $action = null,
        public string $template = 'mail.event',
        public array $required = [],
        public string $icon = 'bell',
        public bool $staffOnly = false,
    ) {}

    /**
     * Render `:placeholder` tokens against a payload.
     *
     * Deliberately the same substitution for every channel: the in-app title and the
     * email subject should never be able to drift apart.
     *
     * Values are `mixed` rather than `scalar|null` because callers are ordinary
     * application code: the guard below is what makes the contract true, not a
     * docblock nobody can enforce.
     *
     * @param  array<string, mixed>  $payload
     */
    public function render(string $template, array $payload): string
    {
        $replacements = [];

        foreach ($payload as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $replacements[':'.$key] = (string) $value;
            }
        }

        return strtr($template, $replacements);
    }

    /** @param array<string, mixed> $payload */
    public function assertPayloadSatisfied(array $payload): void
    {
        $missing = array_values(array_diff($this->required, array_keys($payload)));

        if ($missing !== []) {
            throw new InvalidArgumentException(sprintf(
                'Notification event [%s] is missing required payload key(s): %s.',
                $this->key,
                implode(', ', $missing),
            ));
        }
    }

    public function usesMail(): bool
    {
        return in_array('mail', $this->channels, true);
    }
}
