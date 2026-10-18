<?php

namespace SocialDept\AtpAppviewFilament\Support;

use Illuminate\Support\Carbon;

/**
 * Short times for cards and notices, where space is tight.
 */
final class Durations
{
    /**
     * How long ago, such as "4m ago", or $never.
     */
    public static function ago(Carbon|string|null $at, string $never = 'never'): string
    {
        return $at === null ? $never : Carbon::parse($at)->diffForHumans(short: true);
    }

    /**
     * A rough duration, such as "~14 min" or "~2 h 10 min".
     */
    public static function minutes(?int $minutes): ?string
    {
        return match (true) {
            $minutes === null => null,
            $minutes < 60 => '~'.max(1, $minutes).' min',
            $minutes % 60 === 0 || $minutes >= 600 => '~'.intdiv($minutes, 60).' h',
            default => '~'.intdiv($minutes, 60).' h '.($minutes % 60).' min',
        };
    }
}
