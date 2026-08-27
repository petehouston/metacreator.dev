<?php

declare(strict_types=1);

use App\Domain\Blog\Enums\PostStatus;
use App\Domain\Blog\Models\Post;
use App\Domain\Blog\Models\PostCategory;
use App\Domain\Blog\Models\Tag;
use App\Domain\Tools\Enums\ToolTier;
use App\Domain\Tools\Models\Tool;
use App\Domain\Tools\Models\ToolGrant;
use App\Domain\Tools\Services\ToolAccessService;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

/**
 * What the management screens actually do, as opposed to who may open them.
 *
 * The cases here are the ones where a plausible implementation is wrong in a way
 * nobody notices until it costs something: a comp that never expires, a role edit
 * that locks everyone out, two editors silently overwriting each other.
 */
function postFixture(User $author, PostStatus $status = PostStatus::Draft): Post
{
    return Post::query()->create([
        'slug' => 'fixture-'.uniqid(),
        'title' => 'Fixture post',
        'author_id' => $author->id,
        'status' => $status,
        'published_at' => $status === PostStatus::Published ? now()->subDay() : null,
        'blocks' => ['version' => 1, 'blocks' => []],
    ])->refresh();
}

// ── Users ────────────────────────────────────────────────────────────────────

it('never lets staff change a user’s email through the admin API', function () {
    $victim = User::factory()->create(['email' => 'real@example.com']);

    $this->actingAs(staff('super-admin'))
        ->patchJson("/api/v1/admin/users/{$victim->public_id}", ['email' => 'attacker@example.com'])
        ->assertStatus(422);

    expect($victim->fresh()->email)->toBe('real@example.com');
});

it('suspends and reinstates a user, and records who did it', function () {
    $root = staff('super-admin');
    $victim = User::factory()->create();

    $this->actingAs($root)
        ->postJson("/api/v1/admin/users/{$victim->public_id}/suspend", ['reason' => 'Chargeback fraud'])
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    expect(Activity::query()->where('event', 'suspended')->where('causer_id', $root->id)->exists())->toBeTrue();

    // The same endpoint reverses it: suspension is a state, not a one-way door.
    $this->actingAs($root)
        ->postJson("/api/v1/admin/users/{$victim->public_id}/suspend")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

it('refuses to suspend or delete a super admin', function () {
    $root = staff('super-admin');
    $other = staff('super-admin');

    $this->actingAs($root)->postJson("/api/v1/admin/users/{$other->public_id}/suspend")->assertStatus(422);
    $this->actingAs($root)->deleteJson("/api/v1/admin/users/{$other->public_id}")->assertStatus(422);
});

// ── Roles ────────────────────────────────────────────────────────────────────

it('refuses to remove the last super admin', function () {
    $root = staff('super-admin');

    $this->actingAs($root)
        ->putJson("/api/v1/admin/users/{$root->public_id}/roles", ['roles' => ['editor']])
        ->assertStatus(422);

    expect($root->fresh()->hasRole('super-admin'))->toBeTrue();
});

it('lets the last super admin step down once another exists', function () {
    $root = staff('super-admin');
    $successor = User::factory()->create();

    $this->actingAs($root)
        ->putJson("/api/v1/admin/users/{$successor->public_id}/roles", ['roles' => ['super-admin']])
        ->assertOk();

    $this->actingAs($root)
        ->putJson("/api/v1/admin/users/{$root->public_id}/roles", ['roles' => ['editor']])
        ->assertOk();
});

it('composes a read-only editor role from the catalog', function () {
    $root = staff('super-admin');

    $this->actingAs($root)->postJson('/api/v1/admin/roles', [
        'name' => 'editor-readonly',
        'permissions' => ['posts.view_any', 'posts.view', 'media.view_any'],
    ])->assertCreated();

    $reader = tap(User::factory()->create(), fn (User $u) => $u->assignRole('editor-readonly'));

    $this->actingAs($reader)->getJson('/api/v1/admin/posts')->assertOk();
    $this->actingAs($reader)->postJson('/api/v1/admin/posts', ['title' => 'Nope'])->assertForbidden();
});

it('rejects a permission that is not in the catalog', function () {
    $this->actingAs(staff('super-admin'))
        ->postJson('/api/v1/admin/roles', [
            'name' => 'sneaky',
            'permissions' => ['posts.view_any', 'everything.always'],
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation.failed');
});

it('refuses to delete a seeded role or one still in use', function () {
    $root = staff('super-admin');
    $editorRole = Role::query()->where('name', 'editor')->firstOrFail();

    $this->actingAs($root)->deleteJson("/api/v1/admin/roles/{$editorRole->id}")->assertStatus(422);
});

// ── Tools ────────────────────────────────────────────────────────────────────

it('refuses to change the key that binds a tool to its runner', function () {
    $tool = toolFixture();

    $this->actingAs(staff('super-admin'))
        ->patchJson("/api/v1/admin/tools/{$tool->slug}", ['key' => 'something.else'])
        ->assertStatus(422);

    expect($tool->fresh()->key)->not->toBe('something.else');
});

it('stamps published_at the first time a tool is published and keeps it after', function () {
    $tool = toolFixture();
    $tool->forceFill(['status' => 'draft', 'published_at' => null])->save();

    $this->actingAs(staff('super-admin'))
        ->patchJson("/api/v1/admin/tools/{$tool->slug}", ['status' => 'published'])
        ->assertOk();

    $first = $tool->fresh()->published_at;
    expect($first)->not->toBeNull();

    $this->actingAs(staff('super-admin'))
        ->patchJson("/api/v1/admin/tools/{$tool->slug}", ['status' => 'hidden'])
        ->assertOk();

    $this->actingAs(staff('super-admin'))
        ->patchJson("/api/v1/admin/tools/{$tool->slug}", ['status' => 'published'])
        ->assertOk();

    expect($tool->fresh()->published_at?->toIso8601String())->toBe($first?->toIso8601String());
});

// ── Tool grants ──────────────────────────────────────────────────────────────

it('comps a premium tool to one user without changing anyone else’s access', function () {
    Notification::fake();

    $tool = toolFixture(ToolTier::Premium);
    $lucky = User::factory()->create();
    $everyoneElse = User::factory()->create();

    $this->actingAs(staff('super-admin'))->postJson('/api/v1/admin/tool-grants', [
        'user' => $lucky->email,
        'tool' => $tool->slug,
        'reason' => 'Apology for the outage on the 14th',
        'expires_at' => now()->addDays(30)->toIso8601String(),
    ])->assertCreated();

    $access = app(ToolAccessService::class);

    expect($access->decide($tool, $lucky)->allowed)->toBeTrue()
        ->and($access->decide($tool, $everyoneElse)->allowed)->toBeFalse();
});

it('stops honouring a grant the moment it expires', function () {
    $tool = toolFixture(ToolTier::Premium);
    $user = User::factory()->create();

    ToolGrant::query()->create([
        'user_id' => $user->id,
        'tool_id' => $tool->id,
        'reason' => 'Trial',
        'expires_at' => now()->subMinute(),
    ]);

    expect(app(ToolAccessService::class)->decide($tool, $user)->allowed)
        ->toBeFalse();
});

// ── Posts ────────────────────────────────────────────────────────────────────

it('keeps a contributor inside their own drafts', function () {
    $contributor = staff('contributor');
    $mine = postFixture($contributor);
    $theirs = postFixture(User::factory()->create());

    $this->actingAs($contributor)
        ->patchJson("/api/v1/admin/posts/{$mine->slug}", ['title' => 'My edit'])
        ->assertOk();

    $this->actingAs($contributor)
        ->patchJson("/api/v1/admin/posts/{$theirs->slug}", ['title' => 'Not mine'])
        ->assertForbidden();
});

it('refuses a save that would overwrite someone else’s newer edit', function () {
    $editor = staff('editor');
    $post = postFixture($editor);

    // Addressed by public id, not slug: a draft's slug follows its title, and the
    // first save moves it — which would make the second request a 404 for an
    // entirely different reason than the one under test.
    $url = "/api/v1/admin/posts/{$post->public_id}";

    // Two editors both loaded version 1; one of them has already saved.
    $this->actingAs($editor)
        ->patchJson($url, ['title' => 'First save', 'version' => $post->version])
        ->assertOk();

    $this->actingAs($editor)
        ->patchJson($url, ['title' => 'Second save', 'version' => $post->version])
        ->assertStatus(409);

    expect($post->fresh()->title)->toBe('First save');
});

it('refuses an illegal status transition', function () {
    $editor = staff('editor');
    $post = postFixture($editor, PostStatus::Archived);

    // Archived → Published is not a move the lifecycle allows; it has to go back
    // through draft so someone looks at it first.
    $this->actingAs($editor)
        ->patchJson("/api/v1/admin/posts/{$post->slug}", ['status' => 'published'])
        ->assertStatus(422);
});

it('keeps a published post’s slug when its title changes', function () {
    $editor = staff('editor');
    $post = postFixture($editor, PostStatus::Published);
    $slug = $post->slug;

    $this->actingAs($editor)
        ->patchJson("/api/v1/admin/posts/{$post->slug}", ['title' => 'A completely different headline'])
        ->assertOk();

    expect($post->fresh()->slug)->toBe($slug);
});

/**
 * The content has to survive the request.
 *
 * `validated()` returns only the keys a rule names. With a rule on
 * `blocks.blocks.*.type` and none on `blocks.blocks.*.data`, every block arrives at
 * the action as `{type: "paragraph"}` — the writing deleted on save, with a 200 and
 * nothing in the logs. It is the kind of bug that is invisible until someone loses
 * an article, so it is asserted at the seam where it happened.
 */
it('saves the words the editor actually typed', function () {
    $editor = staff('editor');
    $post = postFixture($editor);

    $this->actingAs($editor)->patchJson("/api/v1/admin/posts/{$post->public_id}", [
        'blocks' => [
            'version' => 1,
            'blocks' => [
                ['id' => 'b_one', 'type' => 'heading', 'data' => ['level' => 2, 'text' => 'A heading']],
                ['id' => 'b_two', 'type' => 'paragraph', 'data' => ['html' => 'Body text that must survive.']],
            ],
        ],
    ])->assertOk();

    $saved = $post->fresh();
    $blocks = $saved->blockList();

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['data']['text'])->toBe('A heading')
        ->and($blocks[1]['data']['html'])->toBe('Body text that must survive.')
        // Derived columns follow the content, so a zero here means it was lost too.
        ->and($saved->word_count)->toBeGreaterThan(0)
        ->and($saved->content_text)->toContain('Body text that must survive.');
});

it('generates an excerpt from the writing when one is not given', function () {
    $editor = staff('editor');
    $post = postFixture($editor);

    $this->actingAs($editor)->patchJson("/api/v1/admin/posts/{$post->public_id}", [
        'blocks' => [
            'version' => 1,
            'blocks' => [
                ['type' => 'paragraph', 'data' => ['html' => 'The opening line of the article.']],
            ],
        ],
    ])->assertOk();

    expect($post->fresh()->excerpt)->toContain('The opening line');
});

it('strips a script out of a custom HTML block instead of storing it', function () {
    $editor = staff('editor');
    $post = postFixture($editor);

    $this->actingAs($editor)->patchJson("/api/v1/admin/posts/{$post->public_id}", [
        'blocks' => [
            'version' => 1,
            'blocks' => [
                [
                    'type' => 'html',
                    'data' => ['html' => '<p>Fine</p><script>alert(1)</script>'],
                ],
            ],
        ],
    ])->assertOk();

    // Taking the raw block payload past `validated()` is only safe because the
    // sanitiser is the real gatekeeper. This is that guarantee, asserted.
    $stored = json_encode($post->fresh()->blockList());

    expect($stored)->not->toContain('<script')
        ->and($stored)->not->toContain('alert(1)');
});

it('snapshots a revision before overwriting content', function () {
    $editor = staff('editor');
    $post = postFixture($editor);

    $this->actingAs($editor)->patchJson("/api/v1/admin/posts/{$post->slug}", [
        'blocks' => ['version' => 1, 'blocks' => [
            ['type' => 'paragraph', 'data' => ['text' => 'Rewritten.']],
        ]],
    ])->assertOk();

    expect($post->revisions()->count())->toBe(1);
});

it('applies a bulk action only to the rows the actor may touch', function () {
    $contributor = staff('contributor');
    $mine = postFixture($contributor);
    $theirs = postFixture(User::factory()->create());

    $response = $this->actingAs($contributor)->postJson('/api/v1/admin/posts/bulk', [
        'ids' => [$mine->public_id, $theirs->public_id],
        'action' => 'feature',
    ])->assertOk();

    expect($response->json('data.applied'))->toBe([$mine->public_id])
        ->and($response->json('data.skipped'))->toBe([$theirs->public_id])
        ->and($theirs->fresh()->is_featured)->toBeFalse();
});

it('soft deletes a post and restores it with its status intact', function () {
    $editor = staff('editor');
    $post = postFixture($editor, PostStatus::Published);

    $this->actingAs($editor)->deleteJson("/api/v1/admin/posts/{$post->slug}")->assertNoContent();

    expect(Post::query()->where('id', $post->id)->exists())->toBeFalse();

    $this->actingAs($editor)->postJson("/api/v1/admin/posts/{$post->public_id}/restore")->assertOk();

    expect($post->fresh()->status)->toBe(PostStatus::Published);
});

// ── Taxonomy ─────────────────────────────────────────────────────────────────

/**
 * The admin's taxonomy lists must carry the numeric id.
 *
 * The public resources omit it deliberately — primary keys do not leave the API,
 * and the public site addresses a category by slug. But `posts.category_id` and the
 * tag pivot are numeric, so an editor screen built on the public shape renders a
 * category picker whose every option saves as null. It fails silently, which is why
 * this is asserted rather than left to be noticed.
 */
it('gives the admin the ids its pickers have to save', function (string $endpoint) {
    $editor = staff('editor');

    PostCategory::query()->create(['slug' => 'growth', 'name' => 'Growth']);
    Tag::query()->create(['slug' => 'seo', 'name' => 'SEO']);
    toolFixture();

    $response = $this->actingAs($editor)->getJson($endpoint)->assertOk();

    expect($response->json('data.0.id'))->toBeInt()
        ->and($response->json('data.0.slug'))->toBeString();
})->with([
    'post categories' => '/api/v1/admin/post-categories',
    'tags' => '/api/v1/admin/tags',
]);

it('assigns a category and tags a picker actually offered', function () {
    $editor = staff('editor');
    $post = postFixture($editor);

    $category = PostCategory::query()->create(['slug' => 'growth', 'name' => 'Growth']);
    $tag = Tag::query()->create(['slug' => 'seo', 'name' => 'SEO']);

    // The ids come from the listing endpoints the editor's dropdowns are built from.
    $categoryId = $this->actingAs($editor)->getJson('/api/v1/admin/post-categories')->json('data.0.id');
    $tagId = $this->actingAs($editor)->getJson('/api/v1/admin/tags')->json('data.0.id');

    $this->actingAs($editor)->patchJson("/api/v1/admin/posts/{$post->public_id}", [
        'category_id' => $categoryId,
        'tags' => [$tagId],
    ])->assertOk();

    expect($post->fresh()->category_id)->toBe($category->id)
        ->and($post->fresh()->tags->pluck('id')->all())->toBe([$tag->id]);
});

it('does not take the writing with the label when a category is deleted', function () {
    $editor = staff('editor');
    $category = PostCategory::query()->create(['slug' => 'temp', 'name' => 'Temporary']);
    $post = postFixture($editor);
    $post->forceFill(['category_id' => $category->id])->save();

    $this->actingAs($editor)->deleteJson("/api/v1/admin/post-categories/{$category->slug}")->assertNoContent();

    expect($post->fresh())->not->toBeNull()
        ->and($post->fresh()->category_id)->toBeNull();
});
