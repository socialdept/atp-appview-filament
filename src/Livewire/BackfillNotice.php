<?php

namespace SocialDept\AtpAppviewFilament\Livewire;

use Filament\Pages\Dashboard;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use SocialDept\AtpAppview\Backfill\Backfills;

/**
 * A callout at the top of every panel page while a backfill runs, saying
 * lists may be missing records. Left off the dashboard, whose backfill cards
 * say more. Polls, so it appears when one starts and goes when it ends.
 */
class BackfillNotice extends Component
{
    public bool $onDashboard = false;

    public function mount(): void
    {
        $this->onDashboard = request()->routeIs(Dashboard::getRouteName());
    }

    public function render(): View
    {
        return view('atp-appview-filament::livewire.backfill-notice', ['active' => app(Backfills::class)->active()]);
    }
}
