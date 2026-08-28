<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Support\Models\ContactMessage;
use App\Domain\Support\Models\Ticket;
use App\Domain\Support\Models\TicketMessage;
use App\Domain\Users\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Sample tickets and contact-form messages.
 *
 * The support queue orders worst-first and shows an SLA timeline; with an empty
 * table none of that is visible, so nobody notices when it is wrong. These fixtures
 * deliberately include an overdue ticket, one waiting on the customer, one with an
 * internal note and one already solved — the four states the queue sorts between.
 *
 * Never runs in production — see {@see DatabaseSeeder}.
 */
final class SupportDemoSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::query()->where('email', 'pro@metacreator.dev')->first();
        $other = User::query()->where('email', 'free@metacreator.dev')->first();
        $agent = User::query()->where('email', 'support@metacreator.dev')->first();

        if ($customer === null || $other === null) {
            return;
        }

        // Overdue and unanswered: the row the queue must put at the top.
        $overdue = $this->ticket(
            reference: 'MC-1001',
            user: $customer,
            subject: 'Export is truncating my CSV at 500 rows',
            category: 'tools',
            priority: 'high',
            status: 'open',
            dueAt: now()->subHours(6),
            lastActivity: now()->subHours(9),
        );

        $this->message($overdue, $customer, 'user',
            "I ran the hashtag analyser over 2,300 posts and the export stops at row 500. Am I hitting a plan limit? I'm on Pro Monthly.");

        // Answered, waiting on the customer — the SLA clock should be paused here.
        $pending = $this->ticket(
            reference: 'MC-1002',
            user: $other,
            subject: 'Cannot sign in with the magic link',
            category: 'account',
            priority: 'normal',
            status: 'pending',
            assignee: $agent,
            firstResponseAt: now()->subDays(1)->addMinutes(22),
            dueAt: now()->addHours(18),
            lastActivity: now()->subDays(1)->addMinutes(22),
        );

        $this->message($pending, $other, 'user',
            'The link in the email says it has expired even though I clicked it straight away.');
        $this->message($pending, $agent, 'staff',
            "Magic links are single use and last fifteen minutes. If your mail provider pre-fetches links it will consume one before you ever see it — could you tell me which provider you're on?");

        // An internal note, to prove notes never reach the customer's thread.
        $billing = $this->ticket(
            reference: 'MC-1003',
            user: $customer,
            subject: 'Charged twice for August',
            category: 'billing',
            priority: 'urgent',
            status: 'open',
            assignee: $agent,
            firstResponseAt: now()->subHours(2),
            dueAt: now()->addHours(2),
            lastActivity: now()->subHours(2),
        );

        $this->message($billing, $customer, 'user', 'My card shows two charges of $19 on the same day.');
        $this->message($billing, $agent, 'staff', 'Confirmed — the second charge has been refunded and should clear in 5–10 days.');
        $this->message($billing, $agent, 'staff',
            'Duplicate came from a retried checkout session. Worth a look once the webhook handler lands.', internal: true);

        // Solved, so the "closed" filters and the resolution timer have a subject.
        $solved = $this->ticket(
            reference: 'MC-1004',
            user: $other,
            subject: 'How do I change the email on my account?',
            category: 'account',
            priority: 'low',
            status: 'solved',
            assignee: $agent,
            firstResponseAt: now()->subDays(4)->addMinutes(35),
            resolvedAt: now()->subDays(4)->addHours(2),
            dueAt: now()->subDays(3),
            lastActivity: now()->subDays(4)->addHours(2),
        );

        $this->message($solved, $other, 'user', 'I mistyped my email at sign-up.');
        $this->message($solved, $agent, 'staff',
            'The address on an account is immutable by design, so support has to move it. Done — check the new inbox for the verification mail.');

        $this->contactMessages();
    }

    private function contactMessages(): void
    {
        $messages = [
            ['name' => 'Priya Raman', 'email' => 'priya@brandstudio.io', 'topic' => 'partnership',
                'subject' => 'Agency plan for 12 seats?',
                'message' => 'We manage 40 creator accounts and would need a shared workspace. Is there an agency tier, or should we buy 12 Pro seats?',
                'handled' => false, 'ago' => 3],
            ['name' => 'Tom Ableton', 'email' => 'tom@example.com', 'topic' => 'bug',
                'subject' => 'Thumbnail downloader returns a 403',
                'message' => 'Getting a 403 on any Shorts URL since yesterday. Regular videos are fine.',
                'handled' => false, 'ago' => 9],
            ['name' => 'Lena Ostrowski', 'email' => 'lena@creatorlab.co', 'topic' => 'general',
                'subject' => 'Do you have an API?',
                'message' => "I'd like to run the engagement calculator from our own dashboard. Is there a documented API, or is it planned?",
                'handled' => false, 'ago' => 26],
            ['name' => 'Marcus Bell', 'email' => 'marcus@example.net', 'topic' => 'press',
                'subject' => 'Interview for a newsletter piece',
                'message' => 'Writing about creator tooling for a 30k-subscriber newsletter. Any chance of 20 minutes with the founder?',
                'handled' => true, 'ago' => 60],
        ];

        foreach ($messages as $entry) {
            ContactMessage::query()->updateOrCreate(
                ['email' => $entry['email'], 'subject' => $entry['subject']],
                [
                    'name' => $entry['name'],
                    'message' => $entry['message'],
                    'topic' => $entry['topic'],
                    'handled_at' => $entry['handled'] ? now()->subHours($entry['ago'] - 2) : null,
                    'created_at' => now()->subHours($entry['ago']),
                    'updated_at' => now()->subHours($entry['ago']),
                ],
            );
        }
    }

    private function ticket(
        string $reference,
        User $user,
        string $subject,
        string $category,
        string $priority,
        string $status,
        ?User $assignee = null,
        ?\DateTimeInterface $firstResponseAt = null,
        ?\DateTimeInterface $resolvedAt = null,
        ?\DateTimeInterface $dueAt = null,
        ?\DateTimeInterface $lastActivity = null,
    ): Ticket {
        return Ticket::query()->updateOrCreate(
            ['reference' => $reference],
            [
                'ulid' => Str::ulid()->toString(),
                'user_id' => $user->id,
                'subject' => $subject,
                'category' => $category,
                'priority' => $priority,
                'status' => $status,
                'assigned_to' => $assignee?->id,
                'first_response_at' => $firstResponseAt,
                'resolved_at' => $resolvedAt,
                'due_at' => $dueAt,
                'last_activity_at' => $lastActivity ?? now(),
            ],
        );
    }

    private function message(Ticket $ticket, ?User $author, string $type, string $body, bool $internal = false): void
    {
        TicketMessage::query()->updateOrCreate(
            ['ticket_id' => $ticket->id, 'body' => $body],
            [
                'author_id' => $author?->id,
                'author_type' => $type,
                'is_internal_note' => $internal,
            ],
        );
    }
}
