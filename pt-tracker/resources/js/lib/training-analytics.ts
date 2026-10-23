import type { Exercise } from './tracker-api';

export type PerformanceSession = {
    workout_id: number;
    date: string;
    workout_name: string;
    max_weight_kg: number | null;
    max_reps: number;
    working_sets: number;
    prescribed_calories_kcal: number | null;
    prescribed_meal_readings: number;
    prescribed_meal_count: number;
    logged_calories_kcal: number | null;
    logged_calorie_subtotal_kcal: number | null;
    logged_item_readings: number;
    logged_item_count: number;
    logged_meal_count: number;
    average_bodyweight_kg: number | null;
    bodyweight_readings: number;
    bodyweight_change_from_7_days_ago_kg: number | null;
    prior_bodyweight_readings: number;
};

export type PerformanceResult = {
    exercises: Exercise[];
    exercise_id: number | null;
    data: PerformanceSession[];
    session_limit: number;
};

export type ChartReading = {
    id: number;
    x: number | null;
    y: number | null;
    description: string;
};
