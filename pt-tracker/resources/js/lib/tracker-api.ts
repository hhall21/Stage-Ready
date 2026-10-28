export type TrackerUser = {
    id: number;
    name: string;
    email: string;
    role: 'coach' | 'client';
    is_admin: boolean;
    is_bodybuilding_coach: boolean;
    has_active_coaching?: boolean;
    has_coaching_handovers?: boolean;
    has_suspended_coaching?: boolean;
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
    hunger_level: number | null;
    appetite_level: number | null;
    digestive_discomfort: number | null;
    digestion_notes: string | null;
    dietary_difficulties: string | null;
    nutrition_completed_at: string | null;
    photo_version: number;
    submitted_at: string | null;
    pose_photos_count: number;
};
export type PosingReview = {
    id: number;
    coach_id: number;
    coach_name: string;
    photo_version: number;
    posing_rating: number;
    feedback: string;
    is_competition_prep: boolean;
    flatness_rating: number | null;
    fullness_rating: number | null;
    is_current: boolean;
    reviewed_at: string;
};
export type CheckInPosing = {
    check_in_id: number;
    photo_version: number;
    submitted_at: string | null;
    standard_poses: { key: string; label: string; photo_type: string }[];
    additional_poses: { key: string; label: string; photo_type: string }[];
    retained_photo_limit: number;
    photos: {
        id: number | string;
        pose_key: string;
        url: string;
        updated_at: string;
        expires_at: string | null;
        coach_viewed_at: string | null;
        retained: boolean;
    }[];
    reviews: PosingReview[];
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
export type SplitDay = {
    name: string;
    is_rest_day: boolean;
    notes: string | null;
    exercises: PlanExercise[];
};
export type TrainingSplit = {
    id: number;
    name: string;
    start_date: string;
    notes: string | null;
    days: SplitDay[];
};
export type TrainingSchedule = {
    date: string;
    split: TrainingSplit | null;
    next_split?: TrainingSplit | null;
    day_number?: number;
    expected_day?: number;
    selection?: number | null;
    is_rest_day?: boolean;
    is_extra_rest?: boolean;
    day_name?: string;
    day_notes?: string | null;
    history_locked?: boolean;
    workout: Workout | null;
    tomorrow?: { day_number: number; name: string; split_name: string } | null;
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
        notes: string | null;
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
        public retryAfter: number | null = null,
    ) {
        super(message);
    }
}
function retryAfter(response: Response): number | null {
    const value = response.headers.get('Retry-After');
    const seconds = value && /^\d+$/.test(value) ? Number(value) : 0;
    return Number.isSafeInteger(seconds) && seconds > 0 ? seconds : null;
}
function rateLimitMessage(seconds: number | null): string {
    return seconds
        ? `Too many requests. Please try again in ${seconds} seconds.`
        : 'Too many requests. Please wait a moment and try again.';
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
                response.status === 429
                    ? rateLimitMessage(retryAfter(response))
                    : 'Unable to start a secure session. Please refresh.',
                {},
                retryAfter(response),
            );
        const data = (await response.json()) as { csrf_token: string };
        csrf = data.csrf_token;
    }
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(method !== 'GET' ? { 'X-CSRF-TOKEN': csrf } : {}),
            ...(body !== undefined && !(body instanceof FormData)
                ? { 'Content-Type': 'application/json' }
                : {}),
        },
        ...(body !== undefined
            ? { body: body instanceof FormData ? body : JSON.stringify(body) }
            : {}),
    });
    if (response.status === 204) return undefined as T;
    const data = (await response.json().catch(() => ({}))) as {
        message?: string;
        errors?: Record<string, string[]>;
    };
    if (!response.ok) {
        if ([401, 403, 419].includes(response.status)) csrf = '';
        throw new ApiError(
            response.status,
            response.status === 429
                ? rateLimitMessage(retryAfter(response))
                : (data.message ??
                      (response.status === 413
                          ? 'This upload exceeds the server limit. Choose a smaller photo and try again.'
                          : response.status === 419
                            ? 'Your secure session expired. Please refresh and try again.'
                            : 'Something went wrong. Please try again.')),
            data.errors,
            retryAfter(response),
        );
    }
    if (
        url === '/api/login' ||
        url === '/api/register' ||
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

export async function allPages<T>(url: string): Promise<T[]> {
    const items: T[] = [];
    let page = 1,
        last = 1;
    do {
        const result = await api<Page<T>>(url + '?page=' + page);
        items.push(...result.data);
        last = result.last_page;
        page++;
    } while (page <= last);
    return items;
}
