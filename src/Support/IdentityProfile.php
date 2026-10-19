<?php

namespace SocialDept\AtpAppviewFilament\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use SocialDept\AtpAppview\Blocklist\Blocklist;
use SocialDept\AtpAppview\Labels\Effect;
use SocialDept\AtpAppview\Labels\Policies;
use SocialDept\AtpAppview\Labels\Target;
use SocialDept\AtpAppview\Models\AccountAction;
use SocialDept\AtpAppview\Models\Block;
use SocialDept\AtpAppview\Models\Identity;
use SocialDept\AtpAppview\Models\Label;
use SocialDept\AtpAppview\Models\Record;
use SocialDept\AtpAppview\Pds\Hosts;
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
     * @param  array<int, AccountAction>  $actions  Actions in force on the account
     * @param  bool  $hosted  Whether the operator runs its PDS
     * @param  array<int, array{label: Label, effect: Effect}>  $accountLabels  Labels on the account, with what they do
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
        public readonly array $actions = [],
        public readonly bool $hosted = false,
        public readonly array $accountLabels = [],
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
            AccountAction::query()->active()->where('did', $identity->did)->get()->all(),
            app(Hosts::class)->hostsAccount($identity->did),
            Label::query()->live()->where('subject', $identity->did)->get()
                ->filter(fn (Label $label) => Target::of($label->uri) === Target::ACCOUNT)
                ->map(fn (Label $label) => ['label' => $label, 'effect' => app(Policies::class)->effect($label->src, $label->val, Target::ACCOUNT)])
                ->values()->all(),
        );
    }

    public function banned(): bool
    {
        return collect($this->actions)->contains('action', AccountAction::BAN);
    }

    public function reviewed(): bool
    {
        return collect($this->actions)->contains('action', AccountAction::REVIEW);
    }

    public function takenDown(): bool
    {
        return collect($this->actions)->contains('action', AccountAction::TAKEDOWN);
    }

    public function pruneCancelled(): bool
    {
        return collect($this->actions)->where('action', AccountAction::BAN)->contains('prune_cancelled', true);
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
