<?php

namespace App\Support;

use App\Models\User;
use App\Services\CoachCapacity;

class CoachPricing
{
    /** @return array<string, array{discipline: string, label: string, amount_pence: int, price_label: string, currency: string, interval: string, client_limit: int}> */
    public static function plans(): array
    {
        return [
            'powerlifting' => ['discipline' => 'powerlifting', 'label' => 'Powerlifting', 'amount_pence' => 600,
                'price_label' => '£6', 'currency' => 'gbp', 'interval' => 'month', 'client_limit' => CoachCapacity::LIMIT],
            'bodybuilding' => ['discipline' => 'bodybuilding', 'label' => 'Bodybuilding', 'amount_pence' => 800,
                'price_label' => '£8', 'currency' => 'gbp', 'interval' => 'month', 'client_limit' => CoachCapacity::LIMIT],
        ];
    }

    /** @return array{discipline: string, label: string, amount_pence: int, price_label: string, currency: string, interval: string, client_limit: int} */
    public static function forCoach(User $coach): array
    {
        return self::plans()[$coach->is_bodybuilding_coach ? 'bodybuilding' : 'powerlifting'];
    }

    public static function priceId(User $coach): ?string
    {
        $price = config('services.stripe.prices.'.self::forCoach($coach)['discipline']);

        return is_string($price) && $price !== '' ? $price : null;
    }
}
