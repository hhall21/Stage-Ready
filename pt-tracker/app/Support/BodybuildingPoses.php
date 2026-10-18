<?php

namespace App\Support;

class BodybuildingPoses
{
    /** @return list<array{key: string, label: string, photo_type: string}> */
    public static function all(): array
    {
        return [...self::required(), ...self::optional()];
    }

    /** @return list<array{key: string, label: string, photo_type: string}> */
    public static function required(): array
    {
        return [
            ['key' => 'front_double_biceps', 'label' => 'Front double biceps', 'photo_type' => 'front'],
            ['key' => 'front_lat_spread', 'label' => 'Front lat spread', 'photo_type' => 'front'],
            ['key' => 'side_chest', 'label' => 'Side chest', 'photo_type' => 'side'],
            ['key' => 'back_double_biceps', 'label' => 'Back double biceps', 'photo_type' => 'back'],
            ['key' => 'back_lat_spread', 'label' => 'Back lat spread', 'photo_type' => 'back'],
            ['key' => 'side_triceps', 'label' => 'Side triceps', 'photo_type' => 'side'],
            ['key' => 'abdominals_and_thighs', 'label' => 'Abdominals and thighs', 'photo_type' => 'front'],
            ['key' => 'most_muscular', 'label' => 'Most muscular', 'photo_type' => 'front'],
            ['key' => 'front_relaxed', 'label' => 'Front relaxed', 'photo_type' => 'front'],
            ['key' => 'rear_relaxed', 'label' => 'Rear relaxed', 'photo_type' => 'back'],
            ['key' => 'left_side_relaxed', 'label' => 'Left side relaxed', 'photo_type' => 'side'],
            ['key' => 'right_side_relaxed', 'label' => 'Right side relaxed', 'photo_type' => 'side'],
        ];
    }

    /** @return list<array{key: string, label: string, photo_type: string}> */
    public static function optional(): array
    {
        return [['key' => 'favourite_classic_pose', 'label' => 'Favourite classic pose', 'photo_type' => 'other']];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_column(self::all(), 'key');
    }

    /** @return array{key: string, label: string, photo_type: string} */
    public static function find(string $key): array
    {
        foreach (self::all() as $pose) {
            if ($pose['key'] === $key) {
                return $pose;
            }
        }

        abort(404);
    }
}
