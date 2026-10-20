<?php

namespace SocialDept\AtpAppviewFilament\Support;

use Closure;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use SocialDept\AtpAppview\Features;
use SocialDept\AtpAppview\Features\Moderation;
use SocialDept\AtpAppview\Models\Label;
use SocialDept\AtpAppview\Models\Record;

/**
 * The labels on a page of records, read together the first time a row asks,
 * so a page costs one query. Kept for the request.
 */
class RecordLabels
{
    /** @var array<string, array<int, Label>> */
    protected array $labels = [];

    /**
     * @param  Closure(): array<int, string>  $page  The uris on the same page
     * @return array<int, Label>
     */
    public function of(string $uri, Closure $page): array
    {
        if (! array_key_exists($uri, $this->labels)) {
            $uris = array_values(array_unique([$uri, ...$page()]));
            $found = Label::query()->live()->whereIn('uri', $uris)->orderBy('cts')->get()->groupBy('uri');

            foreach ($uris as $each) {
                $this->labels[$each] = ($found[$each] ?? collect())->all();
            }
        }

        return $this->labels[$uri];
    }

    /**
     * A column of a record's labels as badges, colored by what each does.
     */
    public static function column(): TextColumn
    {
        $labels = fn (Record $record, HasTable $livewire) => app(self::class)->of($record->uri, fn () => $livewire->getTableRecords()->pluck('uri')->all());

        return TextColumn::make('labels')
            ->state(fn (Record $record, HasTable $livewire) => array_map(fn (Label $label) => $label->val, $labels($record, $livewire)))
            ->badge()
            ->color(function (string $state, Record $record, HasTable $livewire) use ($labels) {
                $label = collect($labels($record, $livewire))->firstWhere('val', $state);

                return $label === null ? 'gray' : LabelBadges::color(LabelBadges::effect($label));
            })
            ->placeholder('')
            // Labels are the app's moderation feature, so the column goes with it.
            ->visible(fn () => Features::enabled(Moderation::class));
    }
}
