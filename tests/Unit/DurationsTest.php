<?php

namespace SocialDept\AtpAppviewFilament\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocialDept\AtpAppviewFilament\Support\Durations;

class DurationsTest extends TestCase
{
    public function test_minutes_read_as_rough_durations(): void
    {
        $this->assertNull(Durations::minutes(null));
        $this->assertSame('~1 min', Durations::minutes(0));
        $this->assertSame('~14 min', Durations::minutes(14));
        $this->assertSame('~2 h', Durations::minutes(120));
        $this->assertSame('~2 h 10 min', Durations::minutes(130));
        $this->assertSame('~11 h', Durations::minutes(675));
    }
}
