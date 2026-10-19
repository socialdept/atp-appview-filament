<?php

namespace SocialDept\AtpAppviewFilament\Widgets;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use SocialDept\AtpAppview\Labels\Effect;
use SocialDept\AtpAppview\Labels\Labels;
use SocialDept\AtpAppview\Labels\Policies;
use SocialDept\AtpAppview\Labels\Target;
use SocialDept\AtpAppview\Models\Label;
use SocialDept\AtpAppview\Models\Labeler;
use SocialDept\AtpAppview\Models\LabelPolicy;
use SocialDept\AtpAppviewFilament\Support\LabelBadges;

/**
 * The values one labeler declares, how it defines each, what each does
 * here on content and on accounts, and how many labels of each are in force.
 */
class LabelerValues extends TableWidget
{
    public string $did;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Values';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => $this->rows())
            ->description('What each label does here: the labeler\'s default unless a policy says otherwise. Changing one applies it to every label of that value at once.')
            ->columns([
                TextColumn::make('name')
                    ->weight('medium')
                    ->description(fn (array $record) => $record['value'] === $record['name'] ? null : $record['value'])
                    ->tooltip(fn (array $record) => $record['description']),
                TextColumn::make('severity')->badge()->color('gray'),
                TextColumn::make('blurs')->color('gray'),
                TextColumn::make('default')->label('Labeler default')->color('gray'),
                TextColumn::make('content')
                    ->label('On content')
                    ->badge()
                    ->color(fn (string $state) => LabelBadges::color(Effect::from($state)))
                    ->description(fn (array $record) => $record['content_policy'] ? 'policy' : null),
                TextColumn::make('account')
                    ->label('On accounts')
                    ->badge()
                    ->color(fn (string $state) => LabelBadges::color(Effect::from($state)))
                    ->description(fn (array $record) => $record['account_policy'] ? 'policy' : null),
                TextColumn::make('labels')->label('In force')->numeric()->alignEnd(),
            ])
            ->recordActions([
                Action::make('change')
                    ->iconButton()
                    ->tooltip('Change what it does')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->modalHeading(fn (array $record) => $record['name'])
                    ->modalWidth('md')
                    ->fillForm(fn (array $record) => [
                        'content' => $record['content_policy'] ?? 'default',
                        'account' => $record['account_policy'] ?? 'default',
                    ])
                    ->schema(fn (array $record) => [
                        Select::make('content')
                            ->label('On content')
                            ->options(['default' => 'Labeler default ('.$record['content_default'].')', ...LabelBadges::options(Target::CONTENT)])
                            ->selectablePlaceholder(false),
                        Select::make('account')
                            ->label('On accounts')
                            ->options(['default' => 'Labeler default ('.$record['account_default'].')', ...LabelBadges::options(Target::ACCOUNT)])
                            ->selectablePlaceholder(false),
                    ])
                    ->action(function (array $record, array $data) {
                        $labels = app(Labels::class);

                        foreach ([Target::CONTENT, Target::ACCOUNT] as $target) {
                            $data[$target] === 'default'
                                ? $labels->clear($this->did, $record['value'], $target)
                                : $labels->set($this->did, $record['value'], $target, Effect::from($data[$target]));
                        }

                        Notification::make()->title(e($record['name']).' updated')->success()->send();
                    }),
            ])
            ->emptyStateHeading('No values declared yet')
            ->paginated(false);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function rows(): array
    {
        $labeler = Labeler::query()->find($this->did);

        if ($labeler === null) {
            return [];
        }

        $labels = app(Labels::class);
        $counts = Label::query()->where('src', $this->did)->selectRaw('val, count(*) as n')->groupBy('val')->pluck('n', 'val');
        $policies = LabelPolicy::query()->where('labeler', $this->did)->get()->groupBy('value');
        $rows = [];

        foreach ($labeler->values() as $value) {
            $definition = $labeler->definition($value) ?? [];
            $locale = collect($definition['locales'] ?? [])->first();
            $policy = ($policies[$value] ?? collect())->keyBy('target');

            $rows[$value] = [
                'value' => $value,
                'name' => is_array($locale) && filled($locale['name'] ?? null) ? $locale['name'] : $value,
                'description' => is_array($locale) ? ($locale['description'] ?? null) : null,
                'severity' => $definition['severity'] ?? null,
                'blurs' => $definition['blurs'] ?? null,
                'default' => $definition['defaultSetting'] ?? null,
                'content' => $labels->effect($this->did, $value, Target::CONTENT)->value,
                'account' => $labels->effect($this->did, $value, Target::ACCOUNT)->value,
                'content_policy' => $policy[Target::CONTENT]?->effect->value ?? null,
                'account_policy' => $policy[Target::ACCOUNT]?->effect->value ?? null,
                'content_default' => $this->withoutPolicy($value, Target::CONTENT),
                'account_default' => $this->withoutPolicy($value, Target::ACCOUNT),
                'labels' => (int) ($counts[$value] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * What the value does without an operator's policy, for the select.
     */
    protected function withoutPolicy(string $value, string $target): string
    {
        return app(Policies::class)->fallback($this->did, $value, $target)->value;
    }
}
