@php
    /** @var \SocialDept\AtpAppview\Index\ReconcileResult|null $result */
    /** @var string|null $error */
    use SocialDept\AtpAppviewFilament\Support\Links;
@endphp

<div class="atp-reconcile">
    @if ($error)
        <p class="atp-reconcile-error">Could not read the repo from its PDS: {{ $error }}</p>
    @elseif ($result->skipped)
        <p>{{ $result->summary() }}</p>
    @elseif (! $result->changed() && $result->account === null)
        <p>In step with its PDS: {{ number_format($result->listed) }} records listed, nothing to change.</p>
    @else
        <p>
            Compared {{ number_format($result->listed) }} records with the PDS.
            @if ($result->account)
                The PDS reports the account as <strong>{{ $result->account }}</strong>.
            @endif
        </p>

        @foreach (['tombstoned' => 'To tombstone, gone from the PDS', 'indexed' => 'To index, missing or changed'] as $key => $label)
            @if ($result->{$key} !== [])
                <h4 class="atp-reconcile-heading">{{ $label }} <span class="atp-muted">{{ number_format(count($result->{$key})) }}</span></h4>
                <ul class="atp-reconcile-list">
                    @foreach (array_slice($result->{$key}, 0, 25) as $uri)
                        <li><a class="atp-mono" href="{{ Links::record($uri) }}" target="_blank" rel="noopener">{{ $uri }}</a></li>
                    @endforeach
                    @if (count($result->{$key}) > 25)
                        <li class="atp-muted">and {{ number_format(count($result->{$key}) - 25) }} more</li>
                    @endif
                </ul>
            @endif
        @endforeach
    @endif
</div>
