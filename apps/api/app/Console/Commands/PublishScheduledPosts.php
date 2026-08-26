<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Blog\Enums\PostStatus;
use App\Domain\Blog\Models\Post;
use Illuminate\Console\Command;

/**
 * Promotes posts whose scheduled time has arrived. Runs every minute (docs/09).
 *
 * Each post is moved individually rather than with a mass UPDATE so that the model
 * events other features hang off — ISR revalidation, notifications, search
 * reindexing — actually fire.
 */
final class PublishScheduledPosts extends Command
{
    protected $signature = 'blog:publish-scheduled';

    protected $description = 'Publish posts whose scheduled time has passed';

    public function handle(): int
    {
        $due = Post::query()
            ->where('status', PostStatus::Scheduled->value)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->get();

        foreach ($due as $post) {
            $post->forceFill([
                'status' => PostStatus::Published,
                // The scheduled time is the publication time; using now() instead
                // would make a post that ran a minute late claim the wrong date.
                'published_at' => $post->scheduled_for,
                'scheduled_for' => null,
            ])->save();

            $this->info("Published: {$post->slug}");
        }

        if ($due->isNotEmpty()) {
            $this->components->info("Published {$due->count()} scheduled post(s).");
        }

        return self::SUCCESS;
    }
}
