<?php

declare(strict_types=1);

namespace App\Domain\Tools\Services;

use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\ToolInput;
use Illuminate\Validation\ValidationException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;

/**
 * Validates a payload against a runner's JSON Schema and normalises it.
 *
 * Using the runner's own schema — the same one the frontend generated its form from
 * — is what guarantees the client and server agree on what a valid input is.
 * Failures come back in Laravel's usual field→messages shape so the frontend has one
 * error format to handle.
 */
final class InputValidator
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ValidationException
     */
    public function validate(ToolRunner $runner, array $payload): ToolInput
    {
        $schema = $runner->inputSchema();
        $payload = $this->applyDefaults($schema, $this->coerce($schema, $payload));

        $validator = new Validator;

        // Report every problem at once. Fixing a form one error per submission is a
        // miserable experience, and the frontend renders all of them inline anyway.
        $validator->setMaxErrors(20);

        $result = $validator->validate(
            json_decode(json_encode($payload, JSON_THROW_ON_ERROR), false),
            json_encode($schema, JSON_THROW_ON_ERROR),
        );

        $error = $result->error();

        if ($error !== null) {
            throw ValidationException::withMessages($this->formatErrors($error, $schema));
        }

        return new ToolInput($payload);
    }

    /**
     * HTTP form data arrives as strings. Coerce declared numbers and booleans before
     * validating, so `"3"` satisfies `{"type": "integer"}` as a caller would expect.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function coerce(array $schema, array $payload): array
    {
        foreach ($schema['properties'] ?? [] as $key => $definition) {
            if (! array_key_exists($key, $payload) || ! is_string($payload[$key])) {
                continue;
            }

            $payload[$key] = match ($definition['type'] ?? null) {
                'integer' => is_numeric($payload[$key]) ? (int) $payload[$key] : $payload[$key],
                'number' => is_numeric($payload[$key]) ? (float) $payload[$key] : $payload[$key],
                'boolean' => filter_var($payload[$key], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $payload[$key],
                default => $payload[$key],
            };
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyDefaults(array $schema, array $payload): array
    {
        foreach ($schema['properties'] ?? [] as $key => $definition) {
            if (! array_key_exists($key, $payload) && array_key_exists('default', $definition)) {
                $payload[$key] = $definition['default'];
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, list<string>>
     */
    private function formatErrors(ValidationError $error, array $schema): array
    {
        $formatted = (new ErrorFormatter)->format(
            $error,
            multiple: true,
            formatter: fn ($e) => ['keyword' => $e->keyword(), 'args' => $e->args()],
        );

        $messages = [];

        foreach ($formatted as $pointer => $errors) {
            // JSON pointers ("/handle") become dot paths ("handle") so the frontend
            // can map them onto its form fields without special-casing.
            $field = trim(str_replace('/', '.', (string) $pointer), '.');

            foreach ((array) $errors as $detail) {
                $keyword = $detail['keyword'] ?? '';
                $args = $detail['args'] ?? [];

                // `required` is reported against the *object*, not the field, so fan it
                // out to the individual fields the form can actually highlight.
                if ($keyword === 'required') {
                    foreach ($args['missing'] ?? [] as $missing) {
                        $messages[$missing][] = sprintf('%s is required.', $this->label($missing, $schema));
                    }

                    continue;
                }

                $target = $field !== '' ? $field : 'input';
                $messages[$target][] = $this->humanise($target, $keyword, $args, $schema);
            }
        }

        return array_map(
            fn (array $list) => array_values(array_unique($list)),
            $messages,
        );
    }

    /**
     * Turn JSON Schema keywords into something a person would actually write.
     *
     * "The data should match one item from enum" tells a user nothing; naming the
     * field and listing the valid options tells them exactly what to do. Allowed
     * values come from the schema because the `enum` error itself carries no args.
     *
     * @param  array<string, mixed>  $args
     * @param  array<string, mixed>  $schema
     */
    private function humanise(string $field, string $keyword, array $args, array $schema): string
    {
        $label = $this->label($field, $schema);
        $property = $schema['properties'][$field] ?? [];

        return match ($keyword) {
            'enum' => sprintf(
                '%s must be one of: %s.',
                $label,
                implode(', ', array_map(strval(...), $property['enum'] ?? [])),
            ),
            'type' => sprintf('%s must be %s.', $label, $this->article((string) ($args['expected'] ?? 'valid'))),
            'minLength' => sprintf(
                '%s must be at least %d character%s.',
                $label, $args['min'] ?? 0, ($args['min'] ?? 0) === 1 ? '' : 's',
            ),
            'maxLength' => sprintf('%s must be no more than %d characters.', $label, $args['max'] ?? 0),
            'minimum', 'exclusiveMinimum' => sprintf('%s must be at least %s.', $label, $args['min'] ?? 0),
            'maximum', 'exclusiveMaximum' => sprintf('%s must be no more than %s.', $label, $args['max'] ?? 0),
            'minItems' => sprintf('%s needs at least %d item(s).', $label, $args['min'] ?? 0),
            'maxItems' => sprintf('%s can have at most %d item(s).', $label, $args['max'] ?? 0),
            'pattern' => sprintf('%s is not in the expected format.', $label),
            'additionalProperties' => sprintf('%s is not a recognised option.', $label),
            'format' => sprintf('%s must be a valid %s.', $label, $args['format'] ?? 'value'),
            default => sprintf('%s is not valid.', $label),
        };
    }

    /**
     * Prefer the schema's own `title` as the field label — it is already written for
     * humans, and it is exactly what the generated form shows above the input.
     *
     * @param  array<string, mixed>  $schema
     */
    private function label(string $field, array $schema): string
    {
        $property = $schema['properties'][$field] ?? null;

        return is_array($property) && isset($property['title'])
            ? (string) $property['title']
            : ucfirst(str_replace(['_', '.'], ' ', $field));
    }

    private function article(string $type): string
    {
        return in_array($type[0] ?? '', ['a', 'e', 'i', 'o', 'u'], true)
            ? "an {$type}"
            : "a {$type}";
    }
}
