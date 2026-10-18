<?php

namespace SocialDept\AtpAppviewFilament\Support;

use Illuminate\Support\HtmlString;
use SocialDept\AtpAppview\Models\FollowedLexicon;
use SocialDept\AtpSupport\Facades\Resolver;
use Throwable;

/**
 * Pretty-printed, highlighted JSON for modals. AT-URIs and DIDs link as
 * recordLinks() says, web URLs open as they are, blob CIDs link to the blob
 * on the repo's PDS when the repo is known, and a $type naming a followed
 * lexicon links to its schema record.
 */
class Json
{
    protected const TOKEN = '/("(?:\\\\.|[^"\\\\])*")(\s*:)?|(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)|\b(true|false|null)\b|([{}\[\],])/';

    /**
     * What links inside a string: AT-URIs, DIDs and web URLs. A URL stops
     * before trailing punctuation, which is almost always the sentence's.
     */
    protected const LINK = '#at://[A-Za-z0-9._:%-]+(?:/[A-Za-z0-9.-]+(?:/[A-Za-z0-9._~:-]+)?)?|\bdid:(?:plc|web):[A-Za-z0-9._:%-]*[A-Za-z0-9]|https?://[^\s"<>\\\\]+[^\s"<>\\\\.,;:!?\'")\]]#';

    /**
     * Strings longer than this show their start and how much was cut.
     */
    protected const MAX_STRING = 400;

    /**
     * The key of the string being highlighted, so a $type value can link to
     * its lexicon.
     */
    protected ?string $key = null;

    /**
     * @param  array<string, true>  $blobs  CIDs of the blobs the value refers to
     * @param  array<string, string>  $lexicons  Schema record URIs of followed lexicons, by NSID
     */
    protected function __construct(
        protected array $blobs,
        protected ?string $did,
        protected ?string $pds,
        protected array $lexicons = [],
    ) {
    }

    /**
     * @param  string|null  $did  The repo the value came from, to link its blobs
     * @param  string|null  $pds  That repo's PDS, resolved from the DID when left out
     */
    public static function render(mixed $value, ?string $did = null, ?string $pds = null): HtmlString
    {
        $blobs = $did === null ? [] : array_fill_keys(self::blobCids($value), true);

        if ($blobs !== [] && $pds === null) {
            try {
                $pds = Resolver::resolvePds($did);
            } catch (Throwable) {
                $pds = null;
            }
        }

        // Only lexicons this app follows link, since their schema records are
        // already known. Any other NSID would cost a DNS lookup to resolve.
        $lexicons = FollowedLexicon::query()->pluck('uri', 'nsid')->all();

        return (new self($blobs, $did, $pds, $lexicons))->html($value);
    }

    /**
     * Every blob CID in a record: the current {$type: blob, ref: {$link}}
     * shape and the legacy {cid, mimeType} one.
     *
     * @return array<int, string>
     */
    public static function blobCids(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $found = [];

        if (($value['$type'] ?? null) === 'blob' && is_string($value['ref']['$link'] ?? null)) {
            $found[] = $value['ref']['$link'];
        } elseif (is_string($value['cid'] ?? null) && is_string($value['mimeType'] ?? null)) {
            $found[] = $value['cid'];
        }

        foreach ($value as $child) {
            array_push($found, ...self::blobCids($child));
        }

        return array_values(array_unique($found));
    }

    protected function html(mixed $value): HtmlString
    {
        $json = (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // One block per line, indented with padding, so a wrapped line keeps
        // its indent instead of falling back to the left edge.
        $lines = array_map(function (string $line) {
            $content = ltrim($line, ' ');
            $depth = intdiv(strlen($line) - strlen($content), 4);

            return '<div style="padding-left:'.($depth * 2).'ch">'.$this->highlight($content).'</div>';
        }, explode("\n", $json));

        return new HtmlString('<div class="atp-json">'.implode('', $lines).'</div>');
    }

    protected function highlight(string $line): string
    {
        $this->key = null;

        return (string) preg_replace_callback(self::TOKEN, function (array $m) {
            if (($m[1] ?? '') !== '' && ($m[2] ?? '') !== '') {
                $this->key = substr($m[1], 1, -1);

                return self::span('key', e($m[1])).self::span('punctuation', ':');
            }

            return match (true) {
                ($m[1] ?? '') !== '' => $this->string($m[1]),
                ($m[3] ?? '') !== '' => self::span('number', $m[3]),
                ($m[4] ?? '') !== '' => self::span('literal', $m[4]),
                default => self::span('punctuation', $m[5]),
            };
        }, $line);
    }

    protected function string(string $quoted): string
    {
        $inner = substr($quoted, 1, -1);

        if ($this->pds !== null && $this->did !== null && isset($this->blobs[$inner])) {
            return self::span('string', '"'.self::anchor(Links::blob($this->pds, $this->did, $inner), e($inner), 'blob').'"');
        }

        // A $type names a lexicon, with an optional #def after the NSID.
        $nsid = strtok($inner, '#');

        if ($this->key === '$type' && $nsid !== false && isset($this->lexicons[$nsid])) {
            return self::span('string', '"'.self::anchor(Links::record($this->lexicons[$nsid]), e($inner), 'type').'"');
        }

        if (mb_strlen($quoted) > self::MAX_STRING) {
            $cut = mb_strlen($quoted) - self::MAX_STRING;

            return self::span('string', $this->linked(mb_substr($quoted, 0, self::MAX_STRING)))
                .self::span('punctuation', ' … '.number_format($cut).' more characters"');
        }

        return self::span('string', $this->linked($quoted));
    }

    /**
     * Escape a piece of raw JSON text, turning what can be followed into links.
     */
    protected function linked(string $raw): string
    {
        $html = '';
        $offset = 0;

        preg_match_all(self::LINK, $raw, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[0] as [$text, $at]) {
            $html .= e(substr($raw, $offset, $at - $offset));
            $html .= self::anchor(match (true) {
                str_starts_with($text, 'http') => $text,
                str_starts_with($text, 'did:') => Links::repo($text),
                default => Links::record($text),
            }, e($text));
            $offset = $at + strlen($text);
        }

        return $html.e(substr($raw, $offset));
    }

    protected static function anchor(string $href, string $html, string $kind = 'link'): string
    {
        return '<a href="'.e($href).'" target="_blank" rel="noopener noreferrer" class="atp-json-'.$kind.'">'.$html.'</a>';
    }

    protected static function span(string $kind, string $html): string
    {
        return '<span class="atp-json-'.$kind.'">'.$html.'</span>';
    }
}
