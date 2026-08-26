<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Http\UrlGuard;

/**
 * Builds consistently tagged campaign URLs.
 *
 * The reason analytics data is usually a mess is inconsistent casing and naming —
 * `Instagram`, `instagram` and `IG` become three sources. This normalises parameters
 * to lowercase-hyphenated and warns about the mistakes that quietly corrupt reports.
 */
final class UtmBuilderRunner implements ToolRunner
{
    public static function key(): string
    {
        return 'utility.utm-builder';
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['url', 'source', 'medium'],
            'additionalProperties' => false,
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'title' => 'Destination URL',
                    'description' => 'Where the link should send people.',
                    'maxLength' => 2000,
                    'examples' => ['https://example.com/pricing'],
                ],
                'source' => [
                    'type' => 'string',
                    'title' => 'Source',
                    'description' => 'Where the traffic comes from: instagram, youtube, newsletter…',
                    'minLength' => 1,
                    'maxLength' => 100,
                ],
                'medium' => [
                    'type' => 'string',
                    'title' => 'Medium',
                    'description' => 'The kind of link: social, bio, email, cpc, referral…',
                    'minLength' => 1,
                    'maxLength' => 100,
                    'default' => 'social',
                ],
                'campaign' => ['type' => 'string', 'title' => 'Campaign', 'maxLength' => 100, 'default' => ''],
                'content' => [
                    'type' => 'string',
                    'title' => 'Content (optional)',
                    'description' => 'Distinguishes two links in the same campaign — "story-1" vs "bio-link".',
                    'maxLength' => 100,
                    'default' => '',
                ],
                'term' => ['type' => 'string', 'title' => 'Term (optional)', 'maxLength' => 100, 'default' => ''],
                'shorten_display' => [
                    'type' => 'boolean',
                    'title' => 'Also show a clean display version',
                    'default' => true,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $url = trim($input->string('url'));

        if (! UrlGuard::isPublicHttpUrl($url)) {
            throw ToolExecutionException::invalidInput(
                'Enter a full public http(s) URL, for example https://example.com/pricing.',
                ['url' => 'Must be a valid public http or https URL.'],
            );
        }

        $parameters = array_filter([
            'utm_source' => $this->normalise($input->string('source')),
            'utm_medium' => $this->normalise($input->string('medium', 'social')),
            'utm_campaign' => $this->normalise($input->string('campaign')),
            'utm_content' => $this->normalise($input->string('content')),
            'utm_term' => $this->normalise($input->string('term')),
        ], fn (string $v) => $v !== '');

        $warnings = $this->warnings($input, $parameters);
        $tagged = $this->append($url, $parameters);

        $pairs = [
            ['label' => 'Tagged URL', 'value' => $tagged, 'hint' => 'Copy this into your bio, caption or ad.'],
        ];

        foreach ($parameters as $key => $value) {
            $pairs[] = ['label' => $key, 'value' => $value];
        }

        if ($input->bool('shorten_display', true)) {
            $pairs[] = [
                'label' => 'Display text',
                'value' => preg_replace('#^https?://(www\.)?#', '', $url) ?? $url,
                'hint' => 'Use as the visible label when the platform allows it.',
            ];
        }

        return ToolResult::keyValue($pairs, summary: 'Campaign URL ready — parameters normalised to lowercase.')
            ->withWarnings($warnings)
            ->withMeta(['parameters' => $parameters]);
    }

    /**
     * @param  array<string, string>  $parameters
     * @return list<string>
     */
    private function warnings(ToolInput $input, array $parameters): array
    {
        $warnings = [];

        foreach (['source', 'medium', 'campaign', 'content', 'term'] as $field) {
            $raw = trim($input->string($field));
            $key = "utm_{$field}";

            if ($raw !== '' && isset($parameters[$key]) && $raw !== $parameters[$key]) {
                $warnings[] = "\"{$raw}\" was normalised to \"{$parameters[$key]}\" — analytics tools treat "
                    .'differently-cased values as separate sources.';
            }
        }

        if (! isset($parameters['utm_campaign'])) {
            $warnings[] = 'No campaign name set. Without one you cannot group these clicks later.';
        }

        return $warnings;
    }

    /** @param  array<string, string>  $parameters */
    private function append(string $url, array $parameters): string
    {
        // Preserve any query string the destination already has rather than
        // clobbering it — landing pages often carry their own parameters.
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $existing);

        $query = http_build_query([...$existing, ...$parameters], encoding_type: PHP_QUERY_RFC3986);

        $rebuilt = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        if (isset($parts['port'])) {
            $rebuilt .= ':'.$parts['port'];
        }

        $rebuilt .= $parts['path'] ?? '';
        $rebuilt .= $query === '' ? '' : '?'.$query;
        $rebuilt .= isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $rebuilt;
    }

    private function normalise(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? $value;

        return trim($value, '-');
    }
}
