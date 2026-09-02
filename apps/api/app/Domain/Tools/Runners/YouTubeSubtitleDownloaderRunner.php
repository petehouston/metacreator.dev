<?php

declare(strict_types=1);

namespace App\Domain\Tools\Runners;

use App\Domain\Tools\Contracts\Cacheable;
use App\Domain\Tools\Contracts\ToolRunner;
use App\Domain\Tools\Contracts\UsesProvider;
use App\Domain\Tools\Data\ResultArtifact;
use App\Domain\Tools\Data\RunContext;
use App\Domain\Tools\Data\ToolInput;
use App\Domain\Tools\Data\ToolResult;
use App\Domain\Tools\Exceptions\ToolExecutionException;
use App\Support\Http\SafeHttpClient;
use App\Support\Social\YouTubePage;
use App\Support\Social\YouTubeUrl;

/**
 * A video's subtitles, in the three formats anything downstream will accept.
 *
 * The transcript panel on a watch page is read-only by design: you can open it, you
 * cannot save it, and copying it by hand loses the timings — which are the whole
 * point if the next step is a re-cut, a translation pass, or subtitles on a
 * repost. This reads the caption tracks the player itself is given and writes them
 * out as SRT, WebVTT and plain text.
 *
 * Three formats because they are not interchangeable. **SRT** is what every editor
 * and every social uploader accepts. **WebVTT** is what an HTML5 `<track>` needs and
 * is the only one of the three that survives styling. **Plain text** is what a
 * summariser, a blog draft or a search index wants, with the timings stripped and
 * the lines rejoined into sentences.
 *
 * Auto-generated captions are marked as such rather than quietly mixed in with
 * human ones. They are usable, but they carry no punctuation on many videos and
 * they mis-hear proper nouns constantly — shipping one as a translation source
 * without knowing it was a machine transcript is how a caption file ends up quoting
 * somebody saying something they did not say.
 *
 * Only public videos, and only the tracks YouTube already publishes to the player
 * (docs/08 on compliance). Captions are the copyright of the video's owner: this
 * exists for accessibility work, research, translation and quoting — not for
 * lifting somebody's script.
 */
final class YouTubeSubtitleDownloaderRunner implements Cacheable, ToolRunner, UsesProvider
{
    /** Beyond this a transcript stops being something a browser should hold as a data URI. */
    private const MAX_CUES = 5000;

    public static function key(): string
    {
        return 'youtube.subtitle-downloader';
    }

    public function providers(): array
    {
        return ['youtube'];
    }

    public function cacheTtl(): int
    {
        return 21600;
    }

    public function inputSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'required' => ['url'],
            'additionalProperties' => false,
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'x-control' => 'text',
                    'title' => 'YouTube video URL or ID',
                    'minLength' => 11,
                    'maxLength' => 500,
                    'examples' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
                ],
                'language' => [
                    'type' => 'string',
                    'title' => 'Language code',
                    'description' => 'Two-letter code such as en, es, ja. Leave blank for the video’s own '
                        .'default track — a human-written one where the video has one.',
                    'maxLength' => 12,
                    'default' => '',
                    'examples' => ['en'],
                ],
                'include_auto' => [
                    'type' => 'boolean',
                    'title' => 'Allow auto-generated captions',
                    'description' => 'Off restricts the download to tracks a person wrote or reviewed.',
                    'default' => true,
                ],
            ],
        ];
    }

    public function run(ToolInput $input, RunContext $context): ToolResult
    {
        $videoId = YouTubeUrl::videoId($input->string('url'))
            ?? throw ToolExecutionException::invalidInput(
                "That doesn't look like a YouTube video link.",
                ['url' => 'Unrecognised YouTube URL or video ID.'],
            );

        $html = YouTubePage::watch($videoId);
        $tracks = $this->tracks($html);

        if ($tracks === []) {
            throw ToolExecutionException::notFound(
                'any subtitles on that video. It may have none, or it may be private, age-restricted or '
                .'members-only, in which case YouTube gives the player nothing to read',
            );
        }

        $track = $this->choose($tracks, trim($input->string('language')), $input->bool('include_auto', true));
        $cues = $this->cues($track['url']);

        if ($cues === []) {
            throw ToolExecutionException::upstreamFailed(
                'youtube',
                'YouTube listed a caption track for this video but served it empty. This usually clears '
                .'on a retry in a minute or two.',
            );
        }

        $title = YouTubePage::og($html, 'title') ?? $videoId;
        $stem = $this->filename($title, $videoId, $track['language']);
        $truncated = count($cues) > self::MAX_CUES;
        $cues = array_slice($cues, 0, self::MAX_CUES);

        $artifacts = [
            $this->artifact('srt', "{$stem}.srt", 'application/x-subrip', $this->srt($cues),
                'SubRip (.srt) — editors and social uploaders'),
            $this->artifact('vtt', "{$stem}.vtt", 'text/vtt', $this->vtt($cues),
                'WebVTT (.vtt) — HTML5 <track>'),
            $this->artifact('txt', "{$stem}.txt", 'text/plain', $this->plain($cues),
                'Plain text (.txt) — no timings'),
        ];

        // `$cues` is non-empty by here — the empty case threw above — so the last
        // cue's end time is the transcript's own duration.
        $duration = $cues[count($cues) - 1]['end'];

        return ToolResult::media($artifacts, summary: sprintf(
            '%s subtitles for “%s” — %d cues over %s, in three formats.',
            $track['label'],
            $title,
            count($cues),
            YouTubePage::clock((int) round($duration)),
        ))->withMeta([
            'video_id' => $videoId,
            'title' => $title,
            'language' => $track['language'],
            'auto_generated' => $track['auto'],
            'cue_count' => count($cues),
            'available_languages' => array_map(
                fn (array $candidate) => ['code' => $candidate['language'], 'label' => $candidate['label'],
                    'auto_generated' => $candidate['auto']],
                $tracks,
            ),
        ])->withWarnings($this->warnings($track, $tracks, $truncated));
    }

    /**
     * The caption tracks the watch page hands its own player.
     *
     * Matched with a regex rather than decoded, for the reason {@see YouTubePage}
     * gives: the page body is truncated, so a JSON decode of the whole player
     * response fails on exactly the long pages we most want to read.
     *
     * @return list<array{url: string, language: string, label: string, auto: bool}>
     */
    private function tracks(string $html): array
    {
        if (preg_match('/"captionTracks"\s*:\s*(\[.*?\])\s*,\s*"/s', $html, $match) !== 1) {
            return [];
        }

        $decoded = json_decode($match[1], true);

        if (! is_array($decoded)) {
            return [];
        }

        $tracks = [];

        foreach ($decoded as $entry) {
            if (! is_array($entry) || ! isset($entry['baseUrl']) || ! is_string($entry['baseUrl'])) {
                continue;
            }

            $language = is_string($entry['languageCode'] ?? null) ? $entry['languageCode'] : 'und';
            $name = $entry['name']['simpleText']
                ?? ($entry['name']['runs'][0]['text'] ?? null);

            $tracks[] = [
                'url' => html_entity_decode($entry['baseUrl'], ENT_QUOTES | ENT_HTML5),
                'language' => $language,
                'label' => is_string($name) && $name !== '' ? $name : $language,
                // `asr` is YouTube's own marker for automatic speech recognition.
                'auto' => ($entry['kind'] ?? null) === 'asr',
            ];
        }

        return $tracks;
    }

    /**
     * Pick the track to download.
     *
     * A human-written track always beats an auto-generated one in the same
     * language: they are the same words in the good case and very different words
     * in the bad one, and the bad case is the one worth defaulting away from.
     *
     * @param  list<array{url: string, language: string, label: string, auto: bool}>  $tracks
     * @return array{url: string, language: string, label: string, auto: bool}
     */
    private function choose(array $tracks, string $language, bool $includeAuto): array
    {
        $eligible = $includeAuto ? $tracks : array_values(array_filter($tracks, fn (array $t) => ! $t['auto']));

        if ($eligible === []) {
            throw ToolExecutionException::notFound(
                'a human-written caption track on that video — every track it publishes is '
                .'auto-generated. Turn on "Allow auto-generated captions" to download one anyway',
            );
        }

        if ($language !== '') {
            $matches = array_values(array_filter(
                $eligible,
                fn (array $track) => str_starts_with(mb_strtolower($track['language']), mb_strtolower($language)),
            ));

            if ($matches === []) {
                throw ToolExecutionException::notFound(
                    "a “{$language}” caption track on that video. It publishes: "
                    .implode(', ', array_unique(array_column($eligible, 'language')))
                );
            }

            $eligible = $matches;
        }

        foreach ($eligible as $track) {
            if (! $track['auto']) {
                return $track;
            }
        }

        return $eligible[0];
    }

    /**
     * Fetch and parse one track.
     *
     * The timedtext endpoint answers in its own XML by default; `fmt=json3` is the
     * shape the web player asks for and is far less brittle to parse, so it is
     * tried first and the XML kept as the fallback for tracks that refuse it.
     *
     * @return list<array{start: float, end: float, text: string}>
     */
    private function cues(string $url): array
    {
        $json = SafeHttpClient::attempt($url.(str_contains($url, '?') ? '&' : '?').'fmt=json3');

        if ($json !== null && $json->successful()) {
            $cues = $this->fromJson3($json->body());

            if ($cues !== []) {
                return $cues;
            }
        }

        $xml = SafeHttpClient::attempt($url);

        return $xml !== null && $xml->successful() ? $this->fromXml($xml->body()) : [];
    }

    /** @return list<array{start: float, end: float, text: string}> */
    private function fromJson3(string $body): array
    {
        $data = json_decode($body, true);

        if (! is_array($data) || ! is_array($data['events'] ?? null)) {
            return [];
        }

        $cues = [];

        foreach ($data['events'] as $event) {
            if (! is_array($event) || ! is_array($event['segs'] ?? null)) {
                continue;
            }

            $text = trim(implode('', array_map(
                fn (array $segment) => is_string($segment['utf8'] ?? null) ? $segment['utf8'] : '',
                array_filter($event['segs'], 'is_array'),
            )));

            if ($text === '') {
                continue;
            }

            $start = ((float) ($event['tStartMs'] ?? 0)) / 1000;
            // A zero-duration event is a rolling auto-caption line; a second is the
            // shortest reading time worth writing into a subtitle file.
            $duration = ((float) ($event['dDurationMs'] ?? 0)) / 1000;

            $cues[] = [
                'start' => $start,
                'end' => $start + ($duration > 0 ? $duration : 1.0),
                'text' => $this->clean($text),
            ];
        }

        return $this->tidy($cues);
    }

    /** @return list<array{start: float, end: float, text: string}> */
    private function fromXml(string $body): array
    {
        if (preg_match_all('/<text start="([\d.]+)"(?: dur="([\d.]+)")?[^>]*>(.*?)<\/text>/s', $body, $matches, PREG_SET_ORDER) === 0) {
            return [];
        }

        $cues = [];

        foreach ($matches as $match) {
            $text = $this->clean(html_entity_decode(strip_tags($match[3]), ENT_QUOTES | ENT_HTML5));

            if ($text === '') {
                continue;
            }

            $start = (float) $match[1];
            $duration = (float) ($match[2] !== '' ? $match[2] : 0);

            $cues[] = ['start' => $start, 'end' => $start + ($duration > 0 ? $duration : 1.0), 'text' => $text];
        }

        return $this->tidy($cues);
    }

    /**
     * Stop a cue overlapping the one after it.
     *
     * Auto-generated tracks roll: each line's stated duration runs past the start of
     * the next, which is how the two-line scroll on screen is produced. Written
     * straight out, that is an invalid SRT — most players cope, some silently drop
     * every second cue, and every validator complains.
     *
     * @param  list<array{start: float, end: float, text: string}>  $cues
     * @return list<array{start: float, end: float, text: string}>
     */
    private function tidy(array $cues): array
    {
        $count = count($cues);

        for ($i = 0; $i < $count - 1; $i++) {
            if ($cues[$i]['end'] > $cues[$i + 1]['start']) {
                $cues[$i]['end'] = max($cues[$i]['start'] + 0.1, $cues[$i + 1]['start'] - 0.001);
            }
        }

        return $cues;
    }

    /** @param  list<array{start: float, end: float, text: string}>  $cues */
    private function srt(array $cues): string
    {
        $out = '';

        foreach ($cues as $index => $cue) {
            $out .= ($index + 1)."\n"
                .$this->stamp($cue['start'], ',').' --> '.$this->stamp($cue['end'], ',')."\n"
                .$cue['text']."\n\n";
        }

        return $out;
    }

    /** @param  list<array{start: float, end: float, text: string}>  $cues */
    private function vtt(array $cues): string
    {
        $out = "WEBVTT\n\n";

        foreach ($cues as $cue) {
            $out .= $this->stamp($cue['start'], '.').' --> '.$this->stamp($cue['end'], '.')."\n"
                .$cue['text']."\n\n";
        }

        return $out;
    }

    /**
     * The words alone, rewrapped into paragraphs.
     *
     * Joining on the cue boundaries would produce one line per two seconds of
     * speech, which is unreadable. Breaking on sentence-ending punctuation instead
     * gives back something a person can actually read — and on an auto-generated
     * track with no punctuation at all, a fixed run length is the honest fallback.
     *
     * @param  list<array{start: float, end: float, text: string}>  $cues
     */
    private function plain(array $cues): string
    {
        $text = preg_replace('/\s+/u', ' ', implode(' ', array_map(
            fn (array $cue) => str_replace("\n", ' ', $cue['text']),
            $cues,
        ))) ?? '';

        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($sentences) <= 1) {
            return trim((string) preg_replace('/((?:\S+\s+){40})/u', "$1\n\n", trim($text)));
        }

        $paragraphs = array_map(
            fn (array $chunk) => implode(' ', $chunk),
            array_chunk($sentences, 4),
        );

        return implode("\n\n", $paragraphs);
    }

    /** `01:02:03,456` for SRT, `01:02:03.456` for WebVTT. */
    private function stamp(float $seconds, string $decimal): string
    {
        $seconds = max(0.0, $seconds);
        $whole = (int) floor($seconds);
        $milliseconds = (int) round(($seconds - $whole) * 1000);

        return sprintf(
            '%02d:%02d:%02d%s%03d',
            intdiv($whole, 3600),
            intdiv($whole % 3600, 60),
            $whole % 60,
            $decimal,
            min(999, $milliseconds),
        );
    }

    private function clean(string $text): string
    {
        // YouTube writes speaker changes and sound effects in brackets and doubles
        // them up on rolling tracks; the newline handling keeps a two-line cue.
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        return trim((string) preg_replace('/[ \t]+/u', ' ', $text));
    }

    private function artifact(string $key, string $filename, string $mime, string $body, string $label): ResultArtifact
    {
        return new ResultArtifact(
            key: $key,
            filename: $filename,
            mimeType: $mime,
            size: strlen($body),
            url: 'data:'.$mime.';charset=utf-8;base64,'.base64_encode($body),
            label: $label,
        );
    }

    /** A filename that survives every filesystem, built from the video's own title. */
    private function filename(string $title, string $videoId, string $language): string
    {
        $slug = mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '-', $title));
        $slug = trim((string) preg_replace('/-+/', '-', $slug), '-');
        $slug = $slug === '' ? $videoId : mb_substr($slug, 0, 60);

        return "{$slug}.{$language}";
    }

    /**
     * @param  array{url: string, language: string, label: string, auto: bool}  $track
     * @param  list<array{url: string, language: string, label: string, auto: bool}>  $tracks
     * @return list<string>
     */
    private function warnings(array $track, array $tracks, bool $truncated): array
    {
        $warnings = [];

        if ($track['auto']) {
            $warnings[] = 'This is an auto-generated track. YouTube\'s speech recognition drops most '
                .'punctuation and mis-hears names and jargon, so read it before you publish it — and never '
                .'quote it as though somebody wrote those words.';
        }

        $others = array_values(array_filter(
            $tracks,
            fn (array $candidate) => $candidate['language'] !== $track['language'],
        ));

        if ($others !== []) {
            $codes = array_unique(array_column($others, 'language'));
            $warnings[] = 'This video also publishes '.count($codes).' other language(s): '
                .implode(', ', array_slice($codes, 0, 12))
                .(count($codes) > 12 ? ' and more' : '').'. Set the language code above to download one.';
        }

        if ($truncated) {
            $warnings[] = 'This transcript runs past '.self::MAX_CUES.' cues and was cut there. For a video '
                .'that long, pull the track in sections using the timestamp tool.';
        }

        $warnings[] = 'Subtitles are part of the video and belong to its owner. Use them for '
            .'accessibility, translation, research or quotation — republishing somebody\'s script as your '
            .'own is neither fair use nor a good idea.';

        return $warnings;
    }
}
