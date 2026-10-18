import type { Page } from './tracker-api';

export type PlanVersion = {
    id: number;
    coach_id: number | null;
    effective_from: string;
    cancelled_at: string | null;
    notes: string | null;
};
export type MealPlanEntry = {
    name: string;
    time: string;
    foods: string;
    calories: number | string | null;
    protein: number | string | null;
    carbohydrates: number | string | null;
    fat: number | string | null;
};
export type NutritionPlan = PlanVersion & {
    training_meals: MealPlanEntry[];
    rest_meals: MealPlanEntry[];
};
export type PracticeDay = {
    weekday: number;
    cardio_mode: string;
    cardio_minutes: number;
    cardio_intensity: string | null;
    posing_rounds: number;
    pose_hold_seconds: number;
    vacuum_sets: number;
    vacuum_hold_seconds: number;
    notes: string | null;
};
export type PracticePlan = PlanVersion & {
    show_date: string | null;
    days: PracticeDay[];
};
export type PlansResponse<T> = {
    date: string;
    day_type: 'training' | 'rest';
    weekday: number;
    current: T | null;
    versions: Page<T>;
};
export const weekdays = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
];
export function defaultDays(): PracticeDay[] {
    return weekdays.map((_, index) => ({
        weekday: index + 1,
        cardio_mode: 'Walking',
        cardio_minutes: 0,
        cardio_intensity: '',
        posing_rounds: 0,
        pose_hold_seconds: 10,
        vacuum_sets: 0,
        vacuum_hold_seconds: 10,
        notes: '',
    }));
}
export function defaultMeals(rest = false): MealPlanEntry[] {
    return [8, 12, 16, 20].map((hour, index) => ({
        name: 'Meal ' + (index + 1),
        time: String(hour + (rest ? 1 : 0)).padStart(2, '0') + ':00',
        foods: '',
        calories: null,
        protein: null,
        carbohydrates: null,
        fat: null,
    }));
}
