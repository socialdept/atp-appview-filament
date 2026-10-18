@php
    $names = array_column($active, 'collection');
    $list = count($names) <= 1 ? ($names[0] ?? '') : implode(', ', array_slice($names, 0, -1)).' and '.end($names);
@endphp

<div wire:poll.15s class="atp-notice">
    @if ($active !== [] && ! $onDashboard)
        <x-filament::callout icon="heroicon-o-arrow-path" color="info" heading="Backfill in progress">
            <x-slot name="description">
                {{ $list }} {{ count($names) === 1 ? 'is' : 'are' }} still being backfilled, so lists and counts may be missing records until {{ count($names) === 1 ? 'it finishes' : 'they finish' }}.
            </x-slot>

            <x-slot name="controls">
                <x-filament::link :href="\Filament\Pages\Dashboard::getUrl()" icon="heroicon-m-arrow-right" icon-position="after" size="sm">
                    Progress
                </x-filament::link>
            </x-slot>
        </x-filament::callout>
    @endif
</div>
