<?php

namespace SocialDept\AtpAppviewFilament\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use SocialDept\AtpAppview\Blocklist\Blocklist;
use SocialDept\AtpAppview\Models\Block;
use SocialDept\AtpAppview\Models\Identity;
use SocialDept\AtpAppview\Models\Record;
use SocialDept\AtpAppview\Profiles\Profiles;
use Throwable;

/**
 * What an identity's page shows: its profile from Profiles, and what the
 * index holds for it, counted per collection in one grouped query on the
 * records table's (did, collection) index.
 */
class IdentityProfile
{
    /**
     * @param  array<string, mixed>  $profile  As Profiles::for() builds it
     * @param  array<int, array{collection: string, total: int}>  $collections  Largest first
     * @param  array<int, Block>  $blocks  Rules naming this DID
     * @param  bool  $blockedByHost  Whether a host rule keeps the whole repo out
     */
    public function __construct(
        public readonly Identity $identity,
        public readonly array $profile,
        public readonly array $collections,
        public readonly int $records,
        public readonly ?Carbon $first,
        public readonly ?Carbon $latest,
        public readonly array $blocks = [],
        public readonly bool $blockedByHost = false,
    ) {
    }

    public static function for(Identity $identity): self
    {
        try {
            $profile = app(Profiles::class)->for([$identity->did])[$identity->did] ?? [];
        } catch (Throwable) {
            $profile = [];
        }

        // Hidden records count too: the page is about the account, active or not.
        $rows = Record::query()->withHidden()->where('did', $identity->did)
            ->selectRaw('collection, count(*) as total, min(sort_at) as first, max(sort_at) as latest')
            ->groupBy('collection')
            ->get();

        $first = $rows->pluck('first')->filter()->min();
        $latest = $rows->pluck('latest')->filter()->max();

        $blocks = Block::query()->where('subject', $identity->did)->orderBy('collection')->get()->all();

        return new self(
            $identity,
            $profile,
            $rows->map(fn ($row) => ['collection' => $row->collection, 'total' => (int) $row->total])->sortByDesc('total')->values()->all(),
            (int) $rows->sum('total'),
            $first === null ? null : Carbon::parse($first, 'UTC'),
            $latest === null ? null : Carbon::parse($latest, 'UTC'),
            $blocks,
            ! collect($blocks)->contains(fn (Block $block) => $block->isBlanket()) && app(Blocklist::class)->blocksRepo($identity->did, $identity->pds),
        );
    }

    public function name(): string
    {
        $name = trim((string) ($this->profile['displayName'] ?? ''));

        return $name !== '' ? $name : ($this->identity->handle ?? $this->identity->did);
    }

    public function website(): ?string
    {
        $website = $this->profile['website'] ?? null;

        return is_string($website) && preg_match('#^https?://#i', $website) ? $website : null;
    }

    /**
     * The bio, escaped, with web URLs linked and line breaks kept.
     */
    public function bio(): ?HtmlString
    {
        $bio = trim((string) ($this->profile['description'] ?? ''));

        if ($bio === '') {
            return null;
        }

        // Web addresses with or without a scheme, as profiles write them.
        $html = preg_replace_callback(
            '#\b(?:https?://|www\.)[^\s<]+[^\s<.,;:!?\'")\]]#i',
            fn (array $m) => '<a href="'.(preg_match('#^https?://#i', $m[0]) ? $m[0] : 'https://'.$m[0]).'" target="_blank" rel="noopener nofollow">'.preg_replace('#^https?://#i', '', $m[0]).'</a>',
            e($bio),
        );

        return new HtmlString(nl2br($html, false));
    }
}
