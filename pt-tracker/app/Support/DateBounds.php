<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/** Calendar boundaries keep DATE and legacy midnight timestamps indexable. */
final class DateBounds
{
    public static function start(DateTimeInterface|string $date): string
    {
        return CarbonImmutable::parse($date)->toDateString();
    }

    public static function after(DateTimeInterface|string $date): string
    {
        return CarbonImmutable::parse($date)->addDay()->toDateString();
    }
}
