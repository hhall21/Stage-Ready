<?php

namespace App\Support;

use App\Models\User;

class CoachPricing
{
    /** @return array<string, array{discipline: string, label: string, amount_pence: int, price_label: string, currency: string, interval: string}> */
    public static function plans(): array
    {
        return [
            'powerlifting' => ['discipline' => 'powerlifting', 'label' => 'Powerlifting', 'amount_pence' => 600,
                'price_label' => '£6', 'currency' => 'gbp', 'interval' => 'month'],
            'bodybuilding' => ['discipline' => 'bodybuilding', 'label' => 'Bodybuilding', 'amount_pence' => 800,
                'price_label' => '£8', 'currency' => 'gbp', 'interval' => 'month'],
        ];
    }

    /** @return array{discipline: string, label: string, amount_pence: int, price_label: string, currency: string, interval: string} */
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
