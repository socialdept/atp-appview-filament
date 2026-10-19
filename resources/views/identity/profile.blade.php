@php
    /** @var \SocialDept\AtpAppviewFilament\Support\IdentityProfile $p */
    use SocialDept\AtpAppviewFilament\Support\Avatars;
    use SocialDept\AtpAppviewFilament\Support\Links;

    $identity = $p->identity;
    $status = $identity->active ? null : ($identity->status ?? 'inactive');
    $pronouns = $p->profile['pronouns'] ?? null;
@endphp

<div class="atp-profile">
    <div class="atp-profile-banner" @if ($p->profile['banner'] ?? null) style="background-image: url('{{ $p->profile['banner'] }}')" @endif></div>

    <div class="atp-profile-body">
        <img
            class="atp-profile-avatar"
            src="{{ $p->profile['avatar'] ?? Avatars::placeholder($identity->handle) }}"
            alt=""
        >

        <div class="atp-profile-name">
            <h2>{{ $p->name() }}</h2>
            @if ($status)
                <x-filament::badge :color="$status === 'deleted' ? 'danger' : 'warning'" size="sm">{{ $status }}</x-filament::badge>
            @endif
            @foreach ($p->blocks as $block)
                <x-filament::badge color="danger" size="sm" icon="heroicon-m-no-symbol" :tooltip="$block->note">
                    {{ $block->isBlanket() ? 'Blocked' : 'Blocked in '.$block->collection }}
                </x-filament::badge>
            @endforeach
            @if ($p->blockedByHost)
                <x-filament::badge color="danger" size="sm" icon="heroicon-m-no-symbol">Blocked by its host</x-filament::badge>
            @endif
        </div>

        <div class="atp-profile-handle atp-muted">
            @if ($identity->handle)
                <a href="{{ Links::repo($identity->did) }}" target="_blank" rel="noopener">{{ '@'.$identity->handle }}</a>
            @else
                No handle
            @endif
            @if (is_string($pronouns) && trim($pronouns) !== '')
                <span>· {{ $pronouns }}</span>
            @endif
        </div>

        @if ($bio = $p->bio())
            <p class="atp-profile-bio">{{ $bio }}</p>
        @endif

        <div class="atp-profile-meta atp-muted">
            @if ($website = $p->website())
                <a href="{{ $website }}" target="_blank" rel="noopener nofollow">
                    <x-filament::icon icon="heroicon-m-link" class="atp-profile-icon" />{{ preg_replace('#^https?://(www\.)?#i', '', rtrim($website, '/')) }}
                </a>
            @endif
            @if ($identity->pds)
                <span><x-filament::icon icon="heroicon-m-globe-alt" class="atp-profile-icon" />{{ parse_url($identity->pds, PHP_URL_HOST) }}</span>
            @endif
            @if ($p->first)
                <span title="{{ $p->first->toIso8601ZuluString() }}"><x-filament::icon icon="heroicon-m-calendar" class="atp-profile-icon" />Records since {{ $p->first->format('M Y') }}</span>
            @endif
            <span class="atp-mono atp-profile-did">{{ $identity->did }}</span>
        </div>

        <div class="atp-profile-stats">
            <span><strong>{{ number_format($p->records) }}</strong> {{ str('record')->plural($p->records) }}</span>
            <span><strong>{{ number_format(count($p->collections)) }}</strong> {{ str('collection')->plural(count($p->collections)) }}</span>
            @if ($p->latest)
                <span title="{{ $p->latest->toIso8601ZuluString() }}">Last record <strong>{{ $p->latest->diffForHumans(short: true) }}</strong></span>
            @endif
            <span>Reconciled <strong>{{ $identity->reconciled_at?->diffForHumans(short: true) ?? 'not yet' }}</strong></span>
        </div>

        @if ($p->collections !== [])
            <div class="atp-profile-collections">
                @foreach ($p->collections as $c)
                    <button
                        type="button"
                        class="atp-profile-collection"
                        x-on:click="Livewire.dispatch('atp-identity-collection', { collection: @js($c['collection']) })"
                    >
                        <span class="atp-mono">{{ $c['collection'] }}</span>
                        <span class="atp-muted">{{ number_format($c['total']) }}</span>
                    </button>
                @endforeach
            </div>
        @endif
    </div>
</div>
