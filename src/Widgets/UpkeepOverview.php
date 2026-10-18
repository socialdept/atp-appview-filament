<?php

namespace SocialDept\AtpAppviewFilament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Str;
use SocialDept\AtpAppview\Status\AppviewStatus;
use SocialDept\AtpAppviewFilament\Support\Durations;

/**
 * The work that keeps the index correct: reconciliation, followed lexicons,
 * validation and the blocklist.
 */
class UpkeepOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $pollingInterval = '30s';

    protected ?string $heading = 'Upkeep';

    protected function getStats(): array
    {
        $status = app(AppviewStatus::class);
        $reconcile = $status->reconciliation();
        $lexicons = $status->lexicons();
        $validation = $status->validation();
        $blocklist = $status->blocklist();

        $coverage = $reconcile['due'] > 0 ? min(100, $reconcile['checkedRecently'] / $reconcile['due'] * 100) : 100;
        $topError = array_key_first($reconcile['errorKinds']);
        $invalid = array_sum($validation['invalid']);
        $validating = $validation['mode'] !== 'off';

        return [
            Stat::make('Reconciled', sprintf('%.0f%%', $coverage))
                ->description(sprintf('%s of %s in %d h', number_format($reconcile['checkedRecently']), number_format($reconcile['due']), $reconcile['everyHours']))
                ->descriptionIcon('heroicon-m-arrow-path-rounded-square')
                ->color($coverage >= 99 ? 'success' : 'warning'),
            Stat::make('Reconcile errors', number_format($reconcile['errors']))
                ->description($topError === null ? 'None' : sprintf('%s %s', number_format($reconcile['errorKinds'][$topError]), self::readable($topError)))
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color($reconcile['errors'] > 0 ? 'danger' : 'success'),
            Stat::make('Lexicons followed', number_format($lexicons['followed']))
                ->description(match (true) {
                    $lexicons['heldBack'] > 0 => sprintf('%d held back', $lexicons['heldBack']),
                    $validating && $invalid > 0 => sprintf('%s invalid records', number_format($invalid)),
                    default => 'Checked '.Durations::ago($lexicons['checkedAt']),
                })
                ->descriptionIcon($lexicons['heldBack'] > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-badge')
                ->color(match (true) {
                    $lexicons['heldBack'] > 0 => 'danger',
                    $validating && $invalid > 0 => 'warning',
                    default => 'success',
                }),
            Stat::make('Blocked', number_format($blocklist['dropped']))
                ->description(sprintf('%d %s, last %s', $blocklist['rules'], Str::plural('rule', $blocklist['rules']), Durations::ago($blocklist['lastDroppedAt'])))
                ->descriptionIcon('heroicon-m-no-symbol')
                ->color('gray'),
        ];
    }

    /**
     * An error kind as words in a sentence: "Host not found" stays as it is,
     * and an XRPC error name such as "UnknownError" reads "unknown error".
     */
    protected static function readable(string $kind): string
    {
        return strtolower(str_contains($kind, ' ') ? $kind : Str::headline($kind));
    }
}
