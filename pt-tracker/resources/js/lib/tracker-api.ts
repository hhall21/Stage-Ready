export type TrackerUser = {
    id: number;
    name: string;
    email: string;
    role: 'coach' | 'client';
};
export type Page<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
};
export type CheckIn = {
    id: number;
    check_in_date: string;
    bodyweight: string | null;
    sleep_hours: string | null;
    sleep_quality: number | null;
    energy_level: number | null;
    stress_level: number | null;
    steps: number | null;
    water_litres: string | null;
    notes: string | null;
};
export type Exercise = { id: number; exercise_name: string };
export type PlanSet = {
    reps: number | null;
    weight: number | string | null;
    rir: number | string | null;
};
export type PlanExercise = {
    exercise_id: number | '';
    notes?: string | null;
    sets: PlanSet[];
};
export type WorkoutTemplate = {
    name: string;
    weekday: number;
    notes?: string | null;
    exercises: PlanExercise[];
};
export type Mesocycle = {
    id: number;
    name: string;
    start_date: string;
    end_date: string;
    notes: string | null;
    templates: WorkoutTemplate[];
};
export type WeightPoint = {
    date: string;
    average_kg: number | null;
    readings: number;
    change_from_7_days_ago_kg: number | null;
};
export type Workout = {
    id: number;
    workout_name: string;
    workout_date: string;
    completed: boolean;
    notes: string | null;
    workout_exercises: {
        id: number;
        exercise: Exercise;
        sets: {
            id: number;
            reps: number | null;
            weight: string | null;
            rir: string | null;
            completed: boolean;
        }[];
    }[];
};
export class ApiError extends Error {
    constructor(
        public status: number,
        message: string,
        public errors: Record<string, string[]> = {},
    ) {
        super(message);
    }
}
let csrf = '';
export async function api<T>(
    url: string,
    method = 'GET',
    body?: unknown,
): Promise<T> {
    if (method !== 'GET' && !csrf) {
        const response = await fetch('/api/csrf-token', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (!response.ok)
            throw new ApiError(
                response.status,
                'Unable to start a secure session. Please refresh.',
            );
        const data = (await response.json()) as { csrf_token: string };
        csrf = data.csrf_token;
    }
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(method !== 'GET'
                ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf }
                : {}),
        },
        ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
    });
    if (response.status === 204) return undefined as T;
    const data = (await response.json()) as {
        message?: string;
        errors?: Record<string, string[]>;
    };
    if (!response.ok) {
        if ([401, 403, 419].includes(response.status)) csrf = '';
        throw new ApiError(
            response.status,
            data.message ?? 'Something went wrong. Please try again.',
            data.errors,
        );
    }
    if (
        url === '/api/login' ||
        url === '/api/logout' ||
        url === '/api/reset-password' ||
        /^\/api\/invitations\/[A-Za-z0-9]{64}\/accept$/.test(url)
    )
        csrf = '';
    return data as T;
}
export function message(error: unknown): string {
    return error instanceof ApiError
        ? Object.values(error.errors).flat().join(' ') || error.message
        : 'Could not connect. Please try again.';
}
export function localDate(): string {
    const date = new Date();
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}
export function displayDate(value: string): string {
    return new Intl.DateTimeFormat('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value.slice(0, 10) + 'T12:00:00'));
}
