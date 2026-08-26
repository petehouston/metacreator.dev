<?php

declare(strict_types=1);

use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Services\InputValidator;
use Illuminate\Validation\ValidationException;

/** A runner that exists only to exercise the validator. */
final class FixtureRunner implements ToolRunner
{
    public static function key(): string
    {
        return 'test.fixture';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['handle', 'count'],
            'additionalProperties' => false,
            'properties' => [
                'handle' => ['type' => 'string', 'title' => 'Account handle', 'minLength' => 3, 'maxLength' => 30],
                'count' => ['type' => 'integer', 'title' => 'Follower count', 'minimum' => 1, 'maximum' => 1000000],
                'platform' => ['type' => 'string', 'title' => 'Platform', 'enum' => ['youtube', 'tiktok'], 'default' => 'youtube'],
                'verbose' => ['type' => 'boolean', 'title' => 'Verbose', 'default' => false],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        return ToolResult::keyValue([]);
    }
}

beforeEach(fn () => $this->validator = new InputValidator);

it('coerces form strings into the declared types', function () {
    // HTTP form data is all strings; "12" must satisfy {"type":"integer"}.
    $input = $this->validator->validate(new FixtureRunner, [
        'handle' => 'creator',
        'count' => '12500',
        'verbose' => 'true',
    ]);

    expect($input->int('count'))->toBe(12500)
        ->and($input->bool('verbose'))->toBeTrue();
});

it('applies schema defaults for omitted fields', function () {
    $input = $this->validator->validate(new FixtureRunner, ['handle' => 'creator', 'count' => 10]);

    expect($input->string('platform'))->toBe('youtube');
});

it('reports every problem at once, not one per submission', function () {
    $errors = null;

    try {
        $this->validator->validate(new FixtureRunner, ['handle' => 'x', 'count' => 0, 'platform' => 'myspace']);
    } catch (ValidationException $e) {
        $errors = $e->errors();
    }

    expect($errors)->toHaveKeys(['handle', 'count', 'platform']);
});

it('writes error messages a person can act on', function () {
    try {
        $this->validator->validate(new FixtureRunner, ['handle' => 'creator', 'count' => 5, 'platform' => 'myspace']);
    } catch (ValidationException $e) {
        // Names the field by its human title and lists the valid options, rather
        // than "The data should match one item from enum".
        expect($e->errors()['platform'][0])->toBe('Platform must be one of: youtube, tiktok.');
    }
});

it('names missing required fields individually', function () {
    try {
        $this->validator->validate(new FixtureRunner, []);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKeys(['handle', 'count'])
            ->and($e->errors()['handle'][0])->toBe('Account handle is required.');
    }
});

it('hashes input canonically, so key order cannot change the cache key', function () {
    $a = $this->validator->validate(new FixtureRunner, ['handle' => 'creator', 'count' => 10]);
    $b = $this->validator->validate(new FixtureRunner, ['count' => 10, 'handle' => 'creator']);

    expect($a->hash())->toBe($b->hash());
});

it('hashes different input differently', function () {
    $a = $this->validator->validate(new FixtureRunner, ['handle' => 'creator', 'count' => 10]);
    $b = $this->validator->validate(new FixtureRunner, ['handle' => 'creator', 'count' => 11]);

    expect($a->hash())->not->toBe($b->hash());
});
