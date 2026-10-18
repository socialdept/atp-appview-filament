@php
    use SocialDept\AtpAppviewFilament\Support\Durations;
@endphp

{{-- The widget is the root, so the dashboard grid gives it its column span. --}}
<x-filament-widgets::widget wire:poll.5s class="atp-backfills-widget">
    @if ($backfills !== [])
        <div class="atp-backfills">
            @foreach ($backfills as $b)
                @php
                    $finished = $b['status'] !== 'running';
                    [$label, $color, $modifier] = match ($b['status']) {
                        'finished' => ['Finished', 'success', 'finished'],
                        'failed' => ['Failed', 'danger', 'failed'],
                        default => ['Backfilling', 'primary', 'running'],
                    };
                    $empty = $b['repos'] - $b['delivered'] - $b['unreachable'];
                @endphp

                <x-filament::section>
                    <div class="atp-backfill-head">
                        <span class="atp-mono atp-backfill-collection">{{ $b['collection'] }}</span>
                        <x-filament::badge :color="$color" size="sm">{{ $label }}</x-filament::badge>
                        <span class="atp-muted atp-backfill-when">
                            {{ $finished ? 'Finished '.Durations::ago($b['finishedAt']) : 'Started '.Durations::ago($b['startedAt']) }}
                        </span>
                    </div>

                    <div class="atp-backfill-figures">
                        @if ($b['status'] === 'failed')
                            <span class="atp-backfill-figure">{{ number_format($b['delivered']) }}</span>
                            <span class="atp-muted">of {{ number_format($b['repos']) }} repos delivered</span>
                        @elseif ($finished)
                            {{-- Finished is finished: what is left is explained, not pending. --}}
                            <span class="atp-backfill-figure">{{ number_format($b['delivered']) }}</span>
                            <span class="atp-muted">
                                repos delivered{{ $b['repos'] > $b['delivered'] ? ', '.collect([$empty > 0 ? number_format($empty).' had nothing to send' : null, $b['unreachable'] > 0 ? number_format($b['unreachable']).' unreachable' : null])->filter()->implode(', ') : '' }}
                            </span>
                        @else
                            <span class="atp-backfill-figure">{{ $b['percent'] }}%</span>
                            <span class="atp-muted">{{ number_format($b['delivered']) }} of {{ number_format($b['repos']) }} repos</span>
                            @if ($b['etaMinutes'] !== null)
                                <span class="atp-backfill-eta">{{ Durations::minutes($b['etaMinutes']) }} left</span>
                            @endif
                        @endif
                    </div>

                    <div class="atp-bar">
                        <div class="atp-bar-fill atp-bar-fill--{{ $modifier }}" style="width: {{ $finished ? 100 : $b['percent'] }}%"></div>
                    </div>

                    @if ($b['status'] === 'failed' && $b['error'])
                        <p class="atp-backfill-error">{{ $b['error'] }}</p>
                    @endif

                    <div class="atp-muted atp-backfill-foot">
                        <span>{{ number_format($b['records']) }} records</span>
                        @if (! $finished && $b['perMinute'] > 0)
                            <span>{{ rtrim(rtrim(number_format($b['perMinute'], 1), '0'), '.') }} repos a minute</span>
                        @endif
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-widgets::widget>
