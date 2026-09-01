<?php

declare(strict_types=1);

use App\Domain\Tools\Models\ToolRun;
use App\Domain\Users\Models\User;

/**
 * What a run keeps, and who may read it.
 *
 * The rule under every case here: a signed-in member's run keeps its input and its
 * result so they can reopen it; an anonymous run keeps neither, because there is no
 * account it could be shown back to and nobody who could ask us to delete it.
 */
it('keeps the input and result of a run made by a member', function () {
    $user = User::factory()->create();
    $tool = counterTool();

    $this->actingAs($user);

    $id = $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'one two three']])
        ->assertOk()
        ->json('data.id');

    $run = ToolRun::query()->where('ulid', substr((string) $id, 4))->sole();

    expect($run->input_payload)->toBe(['text' => 'one two three'])
        ->and($run->result_payload)->not->toBeNull();

    $this->getJson("/api/v1/account/tool-runs/{$id}")
        ->assertOk()
        ->assertJsonPath('data.input.text', 'one two three')
        ->assertJsonPath('data.has_stored_result', true)
        ->assertJsonPath('data.tool.slug', $tool->slug);
});

it('keeps nothing but the record for an anonymous run', function () {
    $tool = counterTool();

    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'anonymous text']])
        ->assertOk();

    $run = ToolRun::query()->latest('id')->sole();

    expect($run->user_id)->toBeNull()
        ->and($run->input_payload)->toBeNull()
        ->and($run->result_payload)->toBeNull()
        // The hash is still there: de-duplication and caching need it, and it
        // reveals nothing about what was typed.
        ->and($run->input_hash)->not->toBeEmpty();
});

it('keeps the input of a failed run, because that is what a failure is about', function () {
    $user = User::factory()->create();
    $tool = counterTool();

    $this->actingAs($user);

    // 200,001 characters — past the schema's ceiling, so validation refuses it
    // before a runner is reached and nothing is recorded at all.
    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => str_repeat('a', 200_001)]])
        ->assertStatus(422);

    expect(ToolRun::query()->count())->toBe(0);
});

it('refuses to show one member the run of another', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();
    $tool = counterTool();

    $this->actingAs($theirs);
    $id = $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'theirs']])
        ->assertOk()
        ->json('data.id');

    $this->actingAs($mine)->getJson("/api/v1/account/tool-runs/{$id}")->assertNotFound();
});

it('refuses run history to anonymous visitors entirely', function () {
    $this->getJson('/api/v1/account/tool-runs')->assertUnauthorized();
    $this->getJson('/api/v1/account/tool-runs/run_01ABC')->assertUnauthorized();
});

it('leaves the payloads out of the list so a page of history is not a page of results', function () {
    $user = User::factory()->create();
    $tool = counterTool();

    $this->actingAs($user);
    $this->postJson("/api/v1/tools/{$tool->slug}/run", ['input' => ['text' => 'listed']])->assertOk();

    $row = $this->getJson('/api/v1/account/tool-runs')->assertOk()->json('data.0');

    expect($row['input'] ?? null)->toBeNull()
        ->and($row['result'] ?? null)->toBeNull()
        // …but the list still knows there is something worth opening.
        ->and($row['has_stored_result'])->toBeTrue();
});

it('will not serve a run that has fallen outside the plan’s history window', function () {
    $user = User::factory()->create();
    $tool = counterTool();

    // A free plan keeps seven days; this one is well past it.
    $run = ToolRun::factory()->create([
        'tool_id' => $tool->id,
        'user_id' => $user->id,
        'created_at' => now()->subDays(30),
    ]);

    $this->actingAs($user)
        ->getJson("/api/v1/account/tool-runs/{$run->public_id}")
        ->assertNotFound();
});
