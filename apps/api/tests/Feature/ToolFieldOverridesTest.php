<?php

declare(strict_types=1);

use App\Domain\Tools\Enums\ToolTier;

/**
 * The example in an empty form field is the tool page's most persuasive element and
 * the thing most likely to rot — a sample post gets deleted and the tool starts
 * demonstrating a 404. Fixing that should not need a deploy, so an admin can set
 * it. What they must *not* be able to change is what the tool accepts: the schema
 * stays the runner's, and these tests hold that line.
 */
it('shows an admin’s sample as the field placeholder and the sample run', function () {
    $tool = counterTool();

    $this->actingAs(staff('super-admin'))
        ->patchJson("/api/v1/admin/tools/{$tool->slug}", [
            'config' => ['field_overrides' => ['text' => ['sample' => 'Paste your script here.']]],
        ])
        ->assertOk();

    $data = $this->getJson("/api/v1/catalog/tools/{$tool->slug}")->assertOk()->json('data');

    expect($data['input_schema']['properties']['text']['examples'])->toBe(['Paste your script here.'])
        // The same value gives this tool the "Try with sample data" button it did
        // not previously have anything to put behind.
        ->and($data['example']['input']['text'])->toBe('Paste your script here.');
});

it('leaves what the tool accepts to the runner', function () {
    $tool = counterTool();
    $schema = $tool->input_schema;

    $this->actingAs(staff('super-admin'))
        ->patchJson("/api/v1/admin/tools/{$tool->slug}", [
            'config' => ['field_overrides' => ['text' => ['sample' => 'Anything at all']]],
        ])
        ->assertOk();

    // The stored schema — the one a run is validated against — is untouched. Only
    // the presented copy carries the override.
    expect($tool->fresh()->input_schema)->toBe($schema);
});

it('drops an override for a field the schema does not have', function () {
    $tool = counterTool();

    $this->actingAs(staff('super-admin'))
        ->patchJson("/api/v1/admin/tools/{$tool->slug}", [
            'config' => ['field_overrides' => [
                'text' => ['sample' => 'Kept'],
                'a_field_the_runner_renamed' => ['sample' => 'Dropped'],
            ]],
        ])
        ->assertOk();

    expect(array_keys($tool->fresh()->fieldOverrides()))->toBe(['text']);
});

it('treats a cleared box as no override rather than an empty one', function () {
    $tool = counterTool();
    $root = staff('super-admin');

    $this->actingAs($root)->patchJson("/api/v1/admin/tools/{$tool->slug}", [
        'config' => ['field_overrides' => ['text' => ['sample' => 'Something']]],
    ])->assertOk();

    $this->actingAs($root)->patchJson("/api/v1/admin/tools/{$tool->slug}", [
        'config' => ['field_overrides' => ['text' => ['sample' => '']]],
    ])->assertOk();

    $tool->refresh();

    expect($tool->fieldOverrides())->toBe([])
        // …and the field goes back to showing whatever the runner ships with.
        ->and($tool->presentedInputSchema())->toBe($tool->input_schema);
});

it('saving the form fields panel does not erase a runner’s other config', function () {
    $tool = toolFixture(ToolTier::Free);
    $tool->forceFill(['config' => ['api_endpoint' => 'https://example.test']])->saveQuietly();

    $this->actingAs(staff('super-admin'))
        ->patchJson("/api/v1/admin/tools/{$tool->slug}", [
            'config' => ['field_overrides' => []],
        ])
        ->assertOk();

    expect($tool->fresh()->config)->toBe(['api_endpoint' => 'https://example.test']);
});

it('shows an admin’s hint under the field in place of the runner’s', function () {
    $tool = counterTool();

    $this->actingAs(staff('super-admin'))
        ->patchJson("/api/v1/admin/tools/{$tool->slug}", [
            'config' => ['field_overrides' => ['text' => ['hint' => 'Paste anything — we never store it.']]],
        ])
        ->assertOk();

    $data = $this->getJson("/api/v1/catalog/tools/{$tool->slug}")->assertOk()->json('data');

    expect($data['input_schema']['properties']['text']['description'])
        ->toBe('Paste anything — we never store it.')
        // A hint is copy, not a contract: the schema a run is validated against is
        // still the runner's, byte for byte.
        ->and($tool->fresh()->input_schema)->toBe($tool->input_schema);
});

it('leaves a hint alone on a field whose sample is numeric', function () {
    $tool = counterTool();

    $this->actingAs(staff('super-admin'))
        ->patchJson("/api/v1/admin/tools/{$tool->slug}", [
            'config' => ['field_overrides' => ['text' => ['hint' => '2026']]],
        ])
        ->assertOk();

    // The sample and default are cast to what the field accepts; a hint never is,
    // because it is a sentence about the field rather than a value for it.
    expect($tool->fresh()->presentedInputSchema()['properties']['text']['description'])->toBe('2026');
});
