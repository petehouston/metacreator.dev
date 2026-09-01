<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use InvalidArgumentException;

/**
 * Every notification the platform can send, in one place (docs/13).
 *
 * A test asserts that everything dispatched exists here and that every entry has a
 * template, so an event cannot be invented at a call site. Groups are the human
 * categories the preferences screen shows — users toggle "Billing", not `billing.*`.
 */
final class EventCatalog
{
    public const GROUPS = [
        'security' => 'Account security',
        'billing' => 'Billing',
        'tools' => 'Tools & usage',
        'support' => 'Support',
        'product' => 'Product updates',
        'staff' => 'Staff alerts',
    ];

    /** @var array<string, NotificationEvent>|null */
    private static ?array $events = null;

    /** @return array<string, NotificationEvent> */
    public static function all(): array
    {
        return self::$events ??= self::build();
    }

    public static function get(string $key): NotificationEvent
    {
        return self::all()[$key] ?? throw new InvalidArgumentException(
            "Unknown notification event [{$key}]. Declare it in ".self::class.'.'
        );
    }

    public static function has(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /**
     * Events a user may turn off, grouped for the preferences screen.
     *
     * @return array<string, list<NotificationEvent>>
     */
    public static function optionalByGroup(): array
    {
        $grouped = [];

        foreach (self::all() as $event) {
            if (! $event->optOut || $event->staffOnly) {
                continue;
            }

            $grouped[$event->group][] = $event;
        }

        return $grouped;
    }

    /** @return array<string, NotificationEvent> */
    private static function build(): array
    {
        $events = [];

        foreach (self::definitions() as $definition) {
            $events[$definition->key] = $definition;
        }

        return $events;
    }

    /** @return list<NotificationEvent> */
    private static function definitions(): array
    {
        return [
            // ── Account ──────────────────────────────────────────────────────
            new NotificationEvent(
                key: 'user.welcome',
                group: 'security',
                channels: ['mail', 'database'],
                optOut: false,
                title: 'Welcome to MetaCreator, :name',
                body: 'Your account is ready. Every free tool is unlocked, and your daily limits just went up.',
                action: ['label' => 'Browse the tools', 'url' => '/tools'],
                template: 'mail.welcome',
                required: ['name'],
                icon: 'sparkles',
            ),
            new NotificationEvent(
                key: 'user.email_verify',
                group: 'security',
                channels: ['mail'],
                optOut: false,
                title: 'Confirm your email address',
                body: 'Confirm :email so we can send you receipts and security alerts.',
                action: ['label' => 'Confirm email', 'url' => '/verify-email'],
                required: ['email'],
                icon: 'mail',
            ),
            new NotificationEvent(
                key: 'user.password_reset',
                group: 'security',
                channels: ['mail'],
                optOut: false,
                title: 'Reset your password',
                body: 'This link is valid for :minutes minutes and can be used once. If you did not ask for it, you can ignore this email.',
                action: ['label' => 'Choose a new password', 'url' => '/reset-password'],
                required: ['minutes'],
                icon: 'key',
            ),
            new NotificationEvent(
                key: 'user.password_changed',
                group: 'security',
                channels: ['mail', 'database'],
                optOut: false,
                title: 'Your password was changed',
                body: 'The password on your account was changed on :changed_at. If that was not you, contact support immediately.',
                action: ['label' => 'Review account security', 'url' => '/dashboard/settings'],
                required: ['changed_at'],
                icon: 'shield',
            ),
            new NotificationEvent(
                key: 'user.magic_link',
                group: 'security',
                channels: ['mail'],
                optOut: false,
                title: 'Your sign-in link',
                body: 'Click below to sign in. The link expires in :minutes minutes and works once.',
                action: ['label' => 'Sign in to MetaCreator', 'url' => '/auth/magic'],
                template: 'mail.magic-link',
                required: ['minutes'],
                icon: 'key',
            ),
            new NotificationEvent(
                key: 'user.new_device_login',
                group: 'security',
                channels: ['mail', 'database'],
                optOut: true,
                title: 'New sign-in from :device',
                body: 'Your account was signed in from :device (:ip) on :signed_in_at.',
                action: ['label' => 'This was not me', 'url' => '/dashboard/settings'],
                required: ['device', 'ip', 'signed_in_at'],
                icon: 'shield',
            ),
            new NotificationEvent(
                key: 'user.profile_updated',
                group: 'security',
                channels: ['database'],
                optOut: true,
                title: 'Your profile was updated',
                body: 'Changes to :fields were saved.',
                action: ['label' => 'View profile', 'url' => '/dashboard/settings'],
                required: ['fields'],
                icon: 'user',
            ),
            new NotificationEvent(
                key: 'user.deletion_scheduled',
                group: 'security',
                channels: ['mail', 'database'],
                optOut: false,
                title: 'Your account is scheduled for deletion',
                body: 'We will erase your account and run history on :deletes_at. You can cancel any time before then.',
                action: ['label' => 'Cancel deletion', 'url' => '/dashboard/settings'],
                required: ['deletes_at'],
                icon: 'alert',
            ),
            new NotificationEvent(
                key: 'user.deletion_cancelled',
                group: 'security',
                channels: ['mail', 'database'],
                optOut: false,
                title: 'Account deletion cancelled',
                body: 'Your account is active again and nothing was removed.',
                action: ['label' => 'Go to your dashboard', 'url' => '/dashboard'],
                icon: 'shield',
            ),

            // ── Billing ──────────────────────────────────────────────────────
            new NotificationEvent(
                key: 'billing.subscription_started',
                group: 'billing',
                channels: ['mail', 'database'],
                optOut: false,
                title: 'Your :plan plan is active',
                body: 'Every premium tool is unlocked. Your next renewal is :renews_at.',
                action: ['label' => 'Start with a premium tool', 'url' => '/tools?tier=premium'],
                required: ['plan', 'renews_at'],
                icon: 'sparkles',
            ),
            new NotificationEvent(
                key: 'billing.invoice_paid',
                group: 'billing',
                channels: ['mail'],
                optOut: false,
                title: 'Receipt for :amount',
                body: 'Thanks — we received your payment for :plan. Invoice :number is attached to your billing history.',
                action: ['label' => 'View invoice', 'url' => '/dashboard/billing'],
                template: 'mail.receipt',
                required: ['amount', 'plan', 'number'],
                icon: 'receipt',
            ),
            new NotificationEvent(
                key: 'billing.payment_failed',
                group: 'billing',
                channels: ['mail', 'database'],
                optOut: false,
                title: 'We could not take your payment',
                body: 'The charge for :plan was declined. We will retry, but updating your card now avoids any interruption.',
                action: ['label' => 'Update payment method', 'url' => '/dashboard/billing'],
                required: ['plan'],
                icon: 'alert',
            ),
            new NotificationEvent(
                key: 'billing.renewal_reminder',
                group: 'billing',
                channels: ['mail'],
                optOut: true,
                title: 'Your :plan plan renews on :renews_at',
                body: 'Nothing to do — this is a heads-up before we charge :amount.',
                action: ['label' => 'Manage subscription', 'url' => '/dashboard/billing'],
                required: ['plan', 'renews_at', 'amount'],
                icon: 'calendar',
            ),
            new NotificationEvent(
                key: 'billing.pass_expiring',
                group: 'billing',
                channels: ['mail', 'database'],
                optOut: true,
                title: 'Your 7-day pass ends tomorrow',
                body: 'Your pass expires on :expires_at. Upgrade to keep premium tools unlocked.',
                action: ['label' => 'See plans', 'url' => '/pricing'],
                required: ['expires_at'],
                icon: 'calendar',
            ),
            new NotificationEvent(
                key: 'billing.subscription_cancelled',
                group: 'billing',
                channels: ['mail', 'database'],
                optOut: false,
                title: 'Your subscription is cancelled',
                body: 'You keep premium access until :ends_at. After that your account returns to the free tier.',
                action: ['label' => 'Reactivate', 'url' => '/pricing'],
                required: ['ends_at'],
                icon: 'calendar',
            ),
            new NotificationEvent(
                key: 'billing.subscription_ended',
                group: 'billing',
                channels: ['mail', 'database'],
                optOut: false,
                title: 'Your premium access has ended',
                body: 'Your account is back on the free tier. Your saved runs and history are untouched.',
                action: ['label' => 'See plans', 'url' => '/pricing'],
                icon: 'calendar',
            ),
            new NotificationEvent(
                key: 'billing.refund_issued',
                group: 'billing',
                channels: ['mail'],
                optOut: false,
                title: 'Refund of :amount issued',
                body: 'We refunded :amount to your original payment method. Banks usually take 5–10 days to show it.',
                action: ['label' => 'View billing history', 'url' => '/dashboard/billing'],
                required: ['amount'],
                icon: 'receipt',
            ),

            // ── Tools ────────────────────────────────────────────────────────
            new NotificationEvent(
                key: 'tool.run_completed',
                group: 'tools',
                channels: ['database'],
                optOut: true,
                title: ':tool finished',
                body: 'Your run finished in :duration. Results are ready.',
                action: ['label' => 'View results', 'url' => '/dashboard/runs'],
                required: ['tool', 'duration'],
                icon: 'check',
            ),
            new NotificationEvent(
                key: 'tool.run_failed',
                group: 'tools',
                channels: ['database'],
                optOut: true,
                title: ':tool could not finish',
                body: 'The run stopped with: :reason. Nothing was counted against your quota.',
                action: ['label' => 'Try again', 'url' => '/tools'],
                required: ['tool', 'reason'],
                icon: 'alert',
            ),
            new NotificationEvent(
                key: 'tool.quota_warning',
                group: 'tools',
                channels: ['database'],
                optOut: true,
                title: 'You have used 80% of today\'s runs',
                body: ':used of :limit runs used. Your quota resets at :resets_at.',
                action: ['label' => 'See plans', 'url' => '/pricing'],
                required: ['used', 'limit', 'resets_at'],
                icon: 'alert',
            ),
            new NotificationEvent(
                key: 'tool.access_granted',
                group: 'tools',
                channels: ['mail', 'database'],
                optOut: false,
                title: 'You have been given access to :tool',
                body: 'Our team unlocked :tool on your account:expiry_note.',
                action: ['label' => 'Open the tool', 'url' => '/tools'],
                required: ['tool'],
                icon: 'sparkles',
            ),
            new NotificationEvent(
                key: 'tool.new_tool_published',
                group: 'product',
                channels: ['mail', 'database'],
                optOut: true,
                title: 'New tool: :tool',
                body: ':tagline',
                action: ['label' => 'Try it now', 'url' => '/tools'],
                required: ['tool', 'tagline'],
                icon: 'sparkles',
            ),

            // ── Support ──────────────────────────────────────────────────────
            new NotificationEvent(
                key: 'support.ticket_created',
                group: 'support',
                channels: ['mail', 'database'],
                optOut: false,
                title: 'We received your ticket :reference',
                body: 'Thanks for writing in about ":subject". We usually reply within one business day.',
                action: ['label' => 'View ticket', 'url' => '/dashboard/support'],
                required: ['reference', 'subject'],
                icon: 'ticket',
            ),
            new NotificationEvent(
                key: 'support.staff_replied',
                group: 'support',
                channels: ['mail', 'database'],
                optOut: false,
                title: 'Reply on ticket :reference',
                body: ':author replied to ":subject".',
                action: ['label' => 'Read the reply', 'url' => '/dashboard/support'],
                required: ['reference', 'subject', 'author'],
                icon: 'ticket',
            ),
            new NotificationEvent(
                key: 'support.customer_replied',
                group: 'support',
                channels: ['database'],
                optOut: false,
                title: ':author replied to :reference',
                body: 'New customer reply on ":subject".',
                action: ['label' => 'Open in admin', 'url' => '/c0ns0le/tickets'],
                required: ['reference', 'subject', 'author'],
                icon: 'ticket',
                staffOnly: true,
            ),
            new NotificationEvent(
                key: 'support.solved',
                group: 'support',
                channels: ['mail', 'database'],
                optOut: false,
                title: 'Ticket :reference is solved',
                body: 'We marked ":subject" as solved. Reply any time to reopen it.',
                action: ['label' => 'View ticket', 'url' => '/dashboard/support'],
                required: ['reference', 'subject'],
                icon: 'check',
            ),
            new NotificationEvent(
                key: 'support.sla_breach',
                group: 'staff',
                channels: ['database'],
                optOut: false,
                title: 'SLA breach on :reference',
                body: ':reference has been waiting since :due_at without a first response.',
                action: ['label' => 'Open in admin', 'url' => '/c0ns0le/tickets'],
                required: ['reference', 'due_at'],
                icon: 'alert',
                staffOnly: true,
            ),

            // ── Staff ────────────────────────────────────────────────────────
            new NotificationEvent(
                key: 'staff.post_scheduled_published',
                group: 'staff',
                channels: ['database'],
                optOut: true,
                title: '":title" went live',
                body: 'The scheduled post published on time.',
                action: ['label' => 'View post', 'url' => '/c0ns0le/posts'],
                required: ['title'],
                icon: 'check',
                staffOnly: true,
            ),
            new NotificationEvent(
                key: 'staff.new_subscription',
                group: 'staff',
                channels: ['database'],
                optOut: true,
                title: 'New :plan subscription',
                body: ':email subscribed to :plan (:amount).',
                action: ['label' => 'View subscriptions', 'url' => '/c0ns0le/subscriptions'],
                required: ['plan', 'email', 'amount'],
                icon: 'sparkles',
                staffOnly: true,
            ),
            new NotificationEvent(
                key: 'staff.payment_failed',
                group: 'staff',
                channels: ['database'],
                optOut: true,
                title: 'Payment failed for :email',
                body: 'The :plan charge was declined. Dunning has started.',
                action: ['label' => 'View subscriptions', 'url' => '/c0ns0le/subscriptions'],
                required: ['email', 'plan'],
                icon: 'alert',
                staffOnly: true,
            ),
            new NotificationEvent(
                key: 'staff.new_ticket',
                group: 'staff',
                channels: ['database'],
                optOut: true,
                title: 'New :priority ticket :reference',
                body: ':subject — from :email.',
                action: ['label' => 'Open in admin', 'url' => '/c0ns0le/tickets'],
                required: ['priority', 'reference', 'subject', 'email'],
                icon: 'ticket',
                staffOnly: true,
            ),
            new NotificationEvent(
                key: 'staff.tool_error_spike',
                group: 'staff',
                channels: ['database'],
                optOut: true,
                title: 'Error spike on :tool',
                body: ':rate% of runs failed in the last :window.',
                action: ['label' => 'View tool analytics', 'url' => '/c0ns0le/tool-analytics'],
                required: ['tool', 'rate', 'window'],
                icon: 'alert',
                staffOnly: true,
            ),
        ];
    }
}
