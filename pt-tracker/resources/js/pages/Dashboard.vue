<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref } from 'vue';
import AthleteVisual from '@/components/AthleteVisual.vue';
import BodyweightTrend from '@/components/BodyweightTrend.vue';
import CheckInPosing from '@/components/CheckInPosing.vue';
import ClientManagement from '@/components/ClientManagement.vue';
import CoachingReviews from '@/components/CoachingReviews.vue';
import EndOfDayNutrition from '@/components/EndOfDayNutrition.vue';
import ExerciseProgress from '@/components/ExerciseProgress.vue';
import TrainingAnalytics from '@/components/TrainingAnalytics.vue';
import JordanPetersQuote from '@/components/JordanPetersQuote.vue';
import TrainingDayPanel from '@/components/TrainingDayPanel.vue';
import TrainingSplitPanel from '@/components/TrainingSplitPanel.vue';
import NutritionPlans from '@/components/NutritionPlans.vue';
import PracticePlans from '@/components/PracticePlans.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import WeeklyHealth from '@/components/WeeklyHealth.vue';
import { athleteImages } from '@/lib/athlete-images';
import { jordanPetersQuotes } from '@/lib/jordan-peters-quotes';
import { motionScrollBehavior, vReveal } from '@/lib/motion';
import {
    api,
    ApiError,
    displayDate,
    localDate,
    message,
} from '@/lib/tracker-api';
import type {
    CheckIn,
    Exercise,
    Page,
    TrackerUser,
    Workout,
    WeightPoint,
} from '@/lib/tracker-api';

const user = ref<TrackerUser | null>(null);
const clients = ref<TrackerUser[]>([]);
const selected = ref<number | null>(null);
type DashboardTab =
    | 'overview'
    | 'checkins'
    | 'workouts'
    | 'progress'
    | 'health'
    | 'nutrition'
    | 'practice'
    | 'reviews'
    | 'clients';
const tab = ref<DashboardTab>('overview');
const navigation = computed<
    { id: DashboardTab; label: string; number: string }[]
>(() => [
    { id: 'overview', label: 'Overview', number: '01' },
    { id: 'checkins', label: 'Daily check-ins', number: '02' },
    { id: 'workouts', label: 'Workouts', number: '03' },
    {
        id: 'progress',
        label:
            user.value?.role === 'coach'
                ? 'Training analytics'
                : 'Exercise progress',
        number: '04',
    },
    { id: 'health', label: 'Weekly health', number: '05' },
    { id: 'nutrition', label: 'Meal plans', number: '06' },
    { id: 'practice', label: 'Cardio and practice', number: '07' },
    { id: 'reviews', label: 'Coach reviews', number: '08' },
    ...(user.value?.role === 'coach'
        ? [{ id: 'clients' as const, label: 'Clients', number: '09' }]
        : []),
]);
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const success = ref('');
const checkins = ref<CheckIn[]>([]);
const workouts = ref<Workout[]>([]);
const weightPoints = ref<WeightPoint[]>([]);
const exercises = ref<Exercise[]>([]);
const checkPage = ref(1),
    checkLast = ref(1),
    workoutPage = ref(1),
    workoutLast = ref(1);
const checkTotal = ref(0),
    workoutTotal = ref(0);
const recordsLoaded = ref(false);
const trainingDayPanel = ref<InstanceType<typeof TrainingDayPanel> | null>(
    null,
);
const editCheck = ref<number | null>(null);
const openPosing = ref<number | null>(null);
const savedPosingCheck = ref<CheckIn | null>(null);
const checkForm = reactive<Record<string, string | number | null>>({
    check_in_date: localDate(),
    bodyweight: '',
    sleep_hours: '',
    sleep_quality: '',
    energy_level: '',
    stress_level: '',
    steps: '',
    water_litres: '',
    notes: '',
});
const recoveryFields = [
    { key: 'sleep_hours', label: 'Sleep (hours)', max: 24, step: '0.25' },
    {
        key: 'sleep_quality',
        label: 'Sleep quality (1–5)',
        min: 1,
        max: 5,
        step: '1',
    },
    { key: 'energy_level', label: 'Energy (1–5)', min: 1, max: 5, step: '1' },
    { key: 'stress_level', label: 'Stress (1–5)', min: 1, max: 5, step: '1' },
    { key: 'steps', label: 'Steps', max: 4294967295, step: '1' },
    { key: 'water_litres', label: 'Water (litres)', max: 99.99, step: '0.1' },
];
const nutritionFields = [
    {
        key: 'hunger_level',
        label: 'Hunger',
        help: 'How hungry did you feel? 1 = not hungry, 5 = very hungry.',
    },
    {
        key: 'appetite_level',
        label: 'Appetite',
        help: 'How much did you want to eat? 1 = very low appetite, 5 = very high appetite.',
    },
    {
        key: 'digestive_discomfort',
        label: 'Digestive discomfort',
        help: '1 = no discomfort, 5 = severe discomfort.',
    },
];
function hasNutrition(record: CheckIn) {
    return (
        nutritionFields.some(
            ({ key }) => record[key as keyof CheckIn] != null,
        ) ||
        !!record.digestion_notes ||
        !!record.dietary_difficulties
    );
}
type ExerciseRow = {
    key: number;
    exercise_id: number | '';
    sets: number;
    reps: number | '';
    weight: number | '';
    rir: number | '';
};
let rowKey = 0,
    loadVersion = 0;
const workoutForm = reactive({
    workout_name: '',
    workout_date: localDate(),
    notes: '',
    exercises: [] as ExerciseRow[],
});
const activeClient = computed(() =>
    user.value?.role === 'client'
        ? user.value
        : clients.value.find((client) => client.id === selected.value),
);
const latest = computed(() =>
    checkins.value.find((record) => record.bodyweight !== null),
);
const canCheckIn = computed(() => user.value?.role === 'client');
const base = computed(() => '/api/clients/' + selected.value);
function workingSets(workout: Workout, completed = false) {
    return workout.workout_exercises.reduce(
        (total, exercise) =>
            total +
            exercise.sets.filter(
                (set) => !completed || (set.completed && (set.reps ?? 0) > 0),
            ).length,
        0,
    );
}

function handleError(cause: unknown) {
    error.value = message(cause);
    if (cause instanceof ApiError && cause.status === 401)
        router.visit('/login');
}
async function allPages<T>(url: string): Promise<T[]> {
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
async function initialize() {
    loading.value = true;
    error.value = '';
    try {
        user.value = (await api<{ data: TrackerUser }>('/api/user')).data;
        if (user.value.role === 'coach')
            clients.value = await allPages<TrackerUser>('/api/clients');
        selected.value =
            user.value.role === 'client'
                ? user.value.id
                : (clients.value[0]?.id ?? null);
        exercises.value = await allPages<Exercise>('/api/exercises');
        await loadRecords();
    } catch (cause) {
        handleError(cause);
    } finally {
        loading.value = false;
    }
}
async function loadRecords() {
    const version = ++loadVersion;
    if (!selected.value) {
        checkins.value = [];
        workouts.value = [];
        return;
    }
    loading.value = true;
    error.value = '';
    try {
        const [checks, training, trend] = await Promise.all([
            api<Page<CheckIn>>(
                base.value + '/daily-check-ins?page=' + checkPage.value,
            ),
            api<Page<Workout>>(
                base.value + '/workouts?page=' + workoutPage.value,
            ),
            api<{ data: WeightPoint[] }>(base.value + '/bodyweight-trend'),
        ]);
        if (version !== loadVersion) return;
        checkins.value = checks.data;
        checkLast.value = checks.last_page;
        checkTotal.value = checks.total;
        workouts.value = training.data;
        workoutLast.value = training.last_page;
        workoutTotal.value = training.total;
        weightPoints.value = trend.data;
        recordsLoaded.value = true;
    } catch (cause) {
        if (version === loadVersion) handleError(cause);
    } finally {
        if (version === loadVersion) loading.value = false;
    }
}
async function selectClient() {
    recordsLoaded.value = false;
    openPosing.value = null;
    savedPosingCheck.value = null;
    checkins.value = [];
    workouts.value = [];
    weightPoints.value = [];
    checkTotal.value = 0;
    workoutTotal.value = 0;
    checkPage.value = 1;
    workoutPage.value = 1;
    success.value = '';
    resetCheck();
    workoutForm.workout_name = '';
    workoutForm.workout_date = localDate();
    workoutForm.notes = '';
    workoutForm.exercises = [];
    await loadRecords();
}

async function refreshTraining() {
    await loadRecords();
    await trainingDayPanel.value?.reload();
}

async function refreshClients() {
    error.value = '';
    try {
        clients.value = await allPages<TrackerUser>('/api/clients');
        if (!clients.value.some((client) => client.id === selected.value))
            selected.value = clients.value[0]?.id ?? null;
        await selectClient();
    } catch (cause) {
        handleError(cause);
    }
}
async function selectTab(next: DashboardTab) {
    tab.value = next;
    if (
        next === 'overview' &&
        (checkPage.value !== 1 || workoutPage.value !== 1)
    ) {
        checkPage.value = 1;
        workoutPage.value = 1;
        await loadRecords();
    }
}
function resetCheck() {
    editCheck.value = null;
    Object.keys(checkForm).forEach((key) => {
        checkForm[key] = key === 'check_in_date' ? localDate() : '';
    });
}
function hasRecoveryDetails(record: CheckIn) {
    return (
        !!record.notes ||
        recoveryFields.some(({ key }) => {
            const value = record[key as keyof CheckIn];
            return value !== null && value !== undefined && value !== '';
        })
    );
}
function edit(record: CheckIn) {
    openPosing.value = null;
    editCheck.value = record.id;
    Object.keys(checkForm).forEach((key) => {
        checkForm[key] = record[key as keyof CheckIn] ?? '';
    });
    checkForm.check_in_date = record.check_in_date.slice(0, 10);
    tab.value = 'checkins';
    document
        .getElementById('check-form')
        ?.scrollIntoView({ behavior: motionScrollBehavior(), block: 'start' });
}
async function saveCheck() {
    saving.value = true;
    error.value = '';
    success.value = '';
    const payload = Object.fromEntries(
        Object.entries(checkForm).map(([key, value]) => [
            key,
            value === '' ? null : value,
        ]),
    );
    try {
        const result = await api<{ data: CheckIn }>(
            base.value +
                '/daily-check-ins' +
                (editCheck.value ? '/' + editCheck.value : ''),
            editCheck.value ? 'PATCH' : 'POST',
            payload,
        );
        resetCheck();
        openPosing.value = result.data.id;
        checkPage.value = 1;
        await loadRecords();
        savedPosingCheck.value = checkins.value.some(
            (record) => record.id === result.data.id,
        )
            ? null
            : result.data;
        success.value = result.data.submitted_at
            ? 'Check-in details saved. Your submitted pose photos and coach feedback are below.'
            : 'Check-in saved. You can add any pose photos or submit it as it is. Your coach can follow up on anything left blank.';
    } catch (cause) {
        handleError(cause);
    } finally {
        saving.value = false;
    }
}
function addExercise() {
    if (workoutForm.exercises.length < 10)
        workoutForm.exercises.push({
            key: ++rowKey,
            exercise_id: '',
            sets: 3,
            reps: 8,
            weight: '',
            rir: '',
        });
}
async function saveWorkout() {
    saving.value = true;
    error.value = '';
    success.value = '';
    try {
        await api(base.value + '/workouts', 'POST', {
            workout_name: workoutForm.workout_name,
            workout_date: workoutForm.workout_date,
            notes: workoutForm.notes || null,
            exercises: workoutForm.exercises.map((row) => ({
                exercise_id: row.exercise_id,
                sets: Array.from({ length: row.sets }, () => ({
                    reps: row.reps === '' ? null : row.reps,
                    weight: row.weight === '' ? null : row.weight,
                    rir: row.rir === '' ? null : row.rir,
                })),
            })),
        });
        workoutForm.workout_name = '';
        workoutForm.notes = '';
        workoutForm.exercises = [];
        workoutPage.value = 1;
        await loadRecords();
        success.value = 'Workout saved.';
    } catch (cause) {
        handleError(cause);
    } finally {
        saving.value = false;
    }
}
async function toggleWorkout(workout: Workout) {
    saving.value = true;
    error.value = '';
    try {
        await api(base.value + '/workouts/' + workout.id, 'PATCH', {
            completed: !workout.completed,
        });
        await refreshTraining();
    } catch (cause) {
        handleError(cause);
    } finally {
        saving.value = false;
    }
}

async function saveSet(
    workout: Workout,
    set: Workout['workout_exercises'][number]['sets'][number],
) {
    saving.value = true;
    error.value = '';
    success.value = '';
    try {
        await api(
            base.value + '/workouts/' + workout.id + '/sets/' + set.id,
            'PATCH',
            {
                reps:
                    set.reps === null || String(set.reps) === ''
                        ? null
                        : set.reps,
                weight: set.weight === '' ? null : set.weight,
                rir: set.rir === '' ? null : set.rir,
                completed: set.completed,
            },
        );
        await refreshTraining();
        success.value =
            'Set logged. This session is preserved when your coach changes the program.';
    } catch (cause) {
        handleError(cause);
    } finally {
        saving.value = false;
    }
}
async function removeRecord(kind: 'workouts' | 'daily-check-ins', id: number) {
    if (
        !window.confirm(
            'Delete this ' +
                (kind === 'workouts'
                    ? 'workout and its exercise sets'
                    : 'check-in and its progress photo records') +
                '? This cannot be undone.',
        )
    )
        return;
    saving.value = true;
    error.value = '';
    try {
        await api(base.value + '/' + kind + '/' + id, 'DELETE');
        checkPage.value = 1;
        workoutPage.value = 1;
        await loadRecords();
        success.value = 'Record deleted.';
    } catch (cause) {
        handleError(cause);
    } finally {
        saving.value = false;
    }
}
async function logout() {
    saving.value = true;
    try {
        await api('/api/logout', 'POST');
        router.visit('/login');
    } catch (cause) {
        handleError(cause);
        saving.value = false;
    }
}
async function changePage(kind: 'check' | 'workout', delta: number) {
    if (kind === 'check') checkPage.value += delta;
    else workoutPage.value += delta;
    await loadRecords();
}
onMounted(() => {
    void initialize();
});
</script>

<template>
    <Head title="Your training space" />
    <SiteHeader dashboard
        ><span class="account-role">{{
            user?.role === 'coach' ? 'COACH SPACE' : 'MEMBER SPACE'
        }}</span
        ><button class="text-link" :disabled="saving" @click="logout">
            Sign out ↗
        </button></SiteHeader
    >
    <div class="dashboard-layout">
        <aside class="sidebar">
            <p class="eyebrow">YOUR WORKSPACE</p>
            <nav aria-label="Dashboard navigation">
                <button
                    v-for="item in navigation"
                    :key="item.id"
                    :data-section="item.id"
                    :class="{ active: tab === item.id }"
                    :aria-current="tab === item.id ? 'page' : undefined"
                    @click="selectTab(item.id)"
                >
                    <span>{{ item.number }}</span
                    >{{ item.label }}<span aria-hidden="true">↗</span>
                </button>
            </nav>
            <div class="sidebar-bottom">
                <p class="eyebrow">THE DAILY STANDARD</p>
                <JordanPetersQuote
                    :quote="jordanPetersQuotes.recovery"
                    variant="compact"
                />
            </div>
        </aside>
        <main class="dashboard-main" :data-section="tab">
            <div class="workspace-heading">
                <div>
                    <p class="eyebrow">
                        {{
                            user?.role === 'coach'
                                ? 'COACH DASHBOARD'
                                : 'YOUR DAILY STANDARD'
                        }}
                    </p>
                    <h1 :key="tab" class="workspace-title">
                        {{
                            tab === 'overview'
                                ? 'Let’s get to work.'
                                : tab === 'checkins'
                                  ? 'Check in. Move forward.'
                                  : tab === 'nutrition'
                                    ? 'Fuel your progress.'
                                    : tab === 'practice'
                                      ? 'Your daily prescriptions.'
                                      : tab === 'reviews'
                                        ? 'Review. Adjust. Progress.'
                                        : tab === 'health'
                                          ? 'Your health readings.'
                                          : tab === 'clients'
                                            ? 'Your coaching roster.'
                                            : tab === 'progress'
                                              ? 'See your progress.'
                                              : 'Train with intent.'
                        }}
                    </h1>
                    <p>
                        {{
                            user
                                ? 'Welcome back, ' +
                                  user.name.split(' ')[0] +
                                  '.'
                                : 'Loading your training space…'
                        }}
                    </p>
                </div>
                <span class="today">{{ displayDate(localDate()) }}</span>
            </div>
            <p v-if="error" class="notice" role="alert">
                {{ error }}
                <button
                    class="text-link"
                    :disabled="loading"
                    @click="initialize"
                >
                    Retry
                </button>
            </p>
            <p v-if="success" class="notice" role="status">{{ success }}</p>
            <div
                v-if="user?.role === 'coach' && tab !== 'clients'"
                class="client-picker"
            >
                <label for="client-select">Assigned client</label
                ><select
                    id="client-select"
                    v-model="selected"
                    :disabled="loading || saving || !clients.length"
                    @change="selectClient"
                >
                    <option v-if="!clients.length" :value="null">
                        No active clients assigned
                    </option>
                    <option
                        v-for="client in clients"
                        :key="client.id"
                        :value="client.id"
                    >
                        {{ client.name }}
                    </option></select
                ><span
                    >{{ clients.length }} active
                    {{ clients.length === 1 ? 'client' : 'clients' }}</span
                >
            </div>
            <ClientManagement
                data-section="clients"
                v-if="user?.role === 'coach' && tab === 'clients'"
                :clients="clients"
                @changed="refreshClients"
            />
            <p
                v-else-if="loading && !recordsLoaded"
                class="loading-message"
                role="status"
            >
                Loading your records…
            </p>
            <div v-else-if="!selected" class="empty-state">
                <h2>Your workspace is ready.</h2>
                <p>
                    {{
                        user?.role === 'coach'
                            ? 'Invite your first client to start coaching. Their access begins as soon as they join.'
                            : 'Sign in to access your training records.'
                    }}
                </p>
                <button
                    v-if="user?.role === 'coach'"
                    class="button button-small"
                    @click="selectTab('clients')"
                >
                    Manage clients ↗
                </button>
            </div>
            <template v-else>
                <NutritionPlans
                    data-section="nutrition"
                    v-if="tab === 'nutrition' && selected"
                    :key="'nutrition-' + selected"
                    :client-id="selected"
                    :coach-id="user?.role === 'coach' ? user.id : null"
                />
                <PracticePlans
                    data-section="practice"
                    v-if="tab === 'practice' && selected"
                    :key="'practice-' + selected"
                    :client-id="selected"
                    :coach-id="user?.role === 'coach' ? user.id : null"
                />
                <CoachingReviews
                    data-section="reviews"
                    v-if="tab === 'reviews' && selected"
                    :key="'reviews-' + selected"
                    :client-id="selected"
                    :coach-id="user?.role === 'coach' ? user.id : null"
                />
                <CoachingReviews
                    data-section="reviews"
                    v-if="tab === 'overview' && selected"
                    :key="'review-summary-' + selected"
                    :client-id="selected"
                    :coach-id="user?.role === 'coach' ? user.id : null"
                    compact
                    @open="selectTab('reviews')"
                />
                <WeeklyHealth
                    data-section="health"
                    v-if="tab === 'health' && selected"
                    :key="'health-' + selected"
                    :client-id="selected"
                    :is-coach="user?.role === 'coach'"
                />
                <WeeklyHealth
                    data-section="health"
                    v-if="tab === 'overview' && selected"
                    :key="'health-summary-' + selected"
                    :client-id="selected"
                    :is-coach="user?.role === 'coach'"
                    compact
                    @open="selectTab('health')"
                />
                <TrainingAnalytics
                    data-section="progress"
                    v-if="
                        tab === 'progress' && selected && user?.role === 'coach'
                    "
                    :key="'analytics-' + selected"
                    :client-id="selected"
                />
                <ExerciseProgress
                    data-section="progress"
                    v-else-if="tab === 'progress' && selected"
                    :client-id="selected"
                />
                <section v-if="tab === 'overview'">
                    <TrainingDayPanel
                        data-section="workouts"
                        ref="trainingDayPanel"
                        v-if="selected"
                        :key="'today-' + selected"
                        :client-id="selected"
                        :can-log="canCheckIn"
                        @changed="loadRecords"
                    />
                    <div class="dashboard-banner">
                        <div>
                            <p class="eyebrow">
                                {{ activeClient?.name }} / THE NEXT STEP
                            </p>
                            <JordanPetersQuote
                                :quote="jordanPetersQuotes.progress"
                                variant="display"
                            />
                            <button
                                class="button"
                                @click="
                                    tab = canCheckIn ? 'checkins' : 'workouts'
                                "
                            >
                                {{
                                    canCheckIn
                                        ? 'Upload your daily poses'
                                        : 'Plan a workout'
                                }}
                                ↗
                            </button>
                        </div>
                        <AthleteVisual compact :src="athleteImages.dashboard" />
                    </div>
                    <BodyweightTrend
                        data-section="progress"
                        :points="weightPoints"
                    />
                    <div class="stats-grid">
                        <article v-reveal data-section="checkins">
                            <p class="eyebrow">POSING CHECK-INS</p>
                            <strong>{{ checkTotal }}</strong
                            ><span>Daily entries, including drafts</span>
                        </article>
                        <article v-reveal data-section="workouts">
                            <p class="eyebrow">WORKOUTS</p>
                            <strong>{{ workoutTotal }}</strong
                            ><span>Sessions in your training log</span>
                        </article>
                        <article v-reveal data-section="progress">
                            <p class="eyebrow">LATEST BODYWEIGHT</p>
                            <strong
                                >{{ latest?.bodyweight ?? '—'
                                }}<small v-if="latest?.bodyweight">
                                    kg</small
                                ></strong
                            ><span>{{
                                latest
                                    ? displayDate(latest.check_in_date)
                                    : 'Add a weigh-in'
                            }}</span>
                        </article>
                    </div>
                    <div class="section-bar" data-section="workouts">
                        <h2>Scheduled training</h2>
                        <button class="text-link" @click="tab = 'workouts'">
                            View workouts ↗
                        </button>
                    </div>
                    <div v-if="!workouts.length" class="empty-state">
                        <p>No workouts yet. Your next session starts here.</p>
                    </div>
                    <article
                        v-for="workout in workouts.slice(0, 3)"
                        :key="workout.id"
                        class="workout-summary"
                    >
                        <div>
                            <p class="eyebrow">
                                {{ displayDate(workout.workout_date) }}
                            </p>
                            <h3>{{ workout.workout_name }}</h3>
                            <p>
                                {{ workout.workout_exercises.length }} exercises
                            </p>
                        </div>
                        <span class="status-pill">{{
                            workout.completed ? 'Completed' : 'Planned'
                        }}</span>
                    </article>
                </section>
                <section v-show="tab === 'checkins'">
                    <EndOfDayNutrition
                        data-section="nutrition"
                        v-if="selected"
                        :key="'nutrition-check-in-' + selected"
                        :client-id="selected"
                        :can-edit="canCheckIn"
                        @saved="loadRecords"
                    />
                    <form
                        v-if="canCheckIn"
                        id="check-form"
                        class="panel"
                        @submit.prevent="saveCheck"
                    >
                        <div class="section-bar">
                            <h2>
                                {{
                                    editCheck
                                        ? 'Edit your posing check-in'
                                        : 'Your daily posing check-in'
                                }}
                            </h2>
                        </div>
                        <p class="form-help checkin-form-help">
                            Share what you want to record. Your coach can follow
                            up on your check-ins. Save nutrition separately at
                            the end of your day.
                        </p>
                        <div class="form-grid">
                            <label
                                >Check-in date<input
                                    v-model="checkForm.check_in_date"
                                    type="date"
                            /></label>
                            <label
                                >Bodyweight (kg)<input
                                    v-model.number="checkForm.bodyweight"
                                    type="number"
                                    min="0"
                                    max="999.99"
                                    step="0.01"
                                    placeholder="—"
                            /></label>
                        </div>
                        <p class="form-help">
                            Leave the date blank to use today, or keep the
                            original date when editing.
                        </p>
                        <details class="checkin-details" :open="!!editCheck">
                            <summary>Recovery and notes</summary>
                            <div class="form-grid">
                                <label
                                    v-for="field in recoveryFields"
                                    :key="field.key"
                                    >{{ field.label
                                    }}<input
                                        v-model.number="checkForm[field.key]"
                                        type="number"
                                        :min="field.min ?? 0"
                                        :max="field.max"
                                        :step="field.step"
                                        placeholder="—" /></label
                                ><label class="full-width"
                                    >Notes<textarea
                                        v-model="checkForm.notes"
                                        rows="3"
                                        maxlength="5000"
                                        placeholder="How are you feeling today?"
                                    />
                                </label>
                            </div>
                        </details>
                        <div class="form-actions">
                            <button class="button" :disabled="saving">
                                {{ saving ? 'Saving…' : 'Save check-in' }}
                                ↗</button
                            ><button
                                v-if="editCheck"
                                type="button"
                                class="text-link"
                                :disabled="saving"
                                @click="resetCheck"
                            >
                                Cancel edit
                            </button>
                        </div>
                    </form>
                    <div
                        v-if="
                            savedPosingCheck &&
                            selected &&
                            user &&
                            !checkins.some(
                                (record) => record.id === savedPosingCheck!.id,
                            )
                        "
                        class="checkin-record"
                    >
                        <h3>
                            Continue your
                            {{ displayDate(savedPosingCheck.check_in_date) }}
                            check-in
                        </h3>
                        <CheckInPosing
                            :key="selected + ':saved:' + savedPosingCheck.id"
                            :client-id="selected"
                            :check-in="savedPosingCheck"
                            :user="user"
                            initially-open
                            @updated="Object.assign(savedPosingCheck!, $event)"
                        />
                    </div>
                    <div class="section-bar">
                        <h2>Posing check-in history</h2>
                        <span class="eyebrow">{{ checkTotal }} ENTRIES</span>
                    </div>
                    <div v-if="!checkins.length" class="empty-state">
                        <p>
                            No posing check-ins yet. Your daily pose photos and
                            coach feedback will appear here.
                        </p>
                    </div>
                    <div
                        v-for="record in checkins"
                        :key="record.id"
                        class="checkin-record"
                    >
                        <div class="section-bar">
                            <h3>{{ displayDate(record.check_in_date) }}</h3>
                            <div v-if="canCheckIn" class="row-actions">
                                <button
                                    class="text-link"
                                    :disabled="saving"
                                    @click="edit(record)"
                                >
                                    Edit</button
                                ><button
                                    class="text-link"
                                    :disabled="saving"
                                    @click="
                                        removeRecord(
                                            'daily-check-ins',
                                            record.id,
                                        )
                                    "
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                        <div class="checkin-values">
                            <span
                                >Bodyweight<strong>{{
                                    record.bodyweight === null
                                        ? 'Not recorded'
                                        : record.bodyweight + ' kg'
                                }}</strong></span
                            >
                        </div>
                        <CheckInPosing
                            v-if="selected && user"
                            :key="selected + ':' + record.id"
                            :client-id="selected"
                            :check-in="record"
                            :user="user"
                            :initially-open="openPosing === record.id"
                            @updated="Object.assign(record, $event)"
                        />
                        <details
                            v-if="hasRecoveryDetails(record)"
                            class="checkin-details"
                        >
                            <summary>Recovery and notes</summary>
                            <div class="checkin-values">
                                <span
                                    >Sleep
                                    <strong
                                        >{{
                                            record.sleep_hours ?? '—'
                                        }}
                                        h</strong
                                    ></span
                                ><span
                                    >Steps
                                    <strong>{{
                                        record.steps?.toLocaleString() ?? '—'
                                    }}</strong></span
                                ><span
                                    >Water
                                    <strong
                                        >{{
                                            record.water_litres ?? '—'
                                        }}
                                        L</strong
                                    ></span
                                ><span
                                    >Sleep quality
                                    <strong
                                        >{{
                                            record.sleep_quality ?? '—'
                                        }}/5</strong
                                    ></span
                                ><span
                                    >Energy
                                    <strong
                                        >{{
                                            record.energy_level ?? '—'
                                        }}/5</strong
                                    ></span
                                ><span
                                    >Stress
                                    <strong
                                        >{{
                                            record.stress_level ?? '—'
                                        }}/5</strong
                                    ></span
                                >
                            </div>
                            <p v-if="record.notes" class="record-notes">
                                {{ record.notes }}
                            </p>
                        </details>
                        <details
                            v-if="hasNutrition(record)"
                            class="checkin-details"
                            open
                        >
                            <summary>End-of-day nutrition responses</summary>
                            <div class="checkin-values">
                                <span
                                    v-for="field in nutritionFields"
                                    :key="field.key"
                                    >{{ field.label
                                    }}<strong
                                        >{{
                                            record[
                                                field.key as keyof CheckIn
                                            ] ?? '—'
                                        }}/5</strong
                                    ></span
                                >
                            </div>
                            <template v-if="record.digestion_notes"
                                ><h3>Digestion notes</h3>
                                <p class="record-notes">
                                    {{ record.digestion_notes }}
                                </p></template
                            >
                            <template v-if="record.dietary_difficulties"
                                ><h3>Dietary difficulties</h3>
                                <p class="record-notes">
                                    {{ record.dietary_difficulties }}
                                </p></template
                            >
                        </details>
                    </div>
                    <div v-if="checkLast > 1" class="pagination">
                        <button
                            :disabled="checkPage === 1 || loading"
                            @click="changePage('check', -1)"
                        >
                            ← Previous</button
                        ><span>{{ checkPage }} / {{ checkLast }}</span
                        ><button
                            :disabled="checkPage === checkLast || loading"
                            @click="changePage('check', 1)"
                        >
                            Next →
                        </button>
                    </div>
                </section>
                <section v-if="tab === 'workouts'">
                    <TrainingDayPanel
                        ref="trainingDayPanel"
                        v-if="selected"
                        :key="'workout-day-' + selected"
                        :client-id="selected"
                        :can-log="canCheckIn"
                        @changed="loadRecords"
                    />
                    <TrainingSplitPanel
                        v-if="selected"
                        :client-id="selected"
                        :exercises="exercises"
                        :can-edit="user?.role === 'coach'"
                        @changed="refreshTraining"
                    />
                    <form
                        v-if="user?.role === 'coach'"
                        class="panel"
                        @submit.prevent="saveWorkout"
                    >
                        <div class="section-bar">
                            <h2>Add a coach workout</h2>
                            <span class="eyebrow"
                                >{{ workoutForm.exercises.length }} / 10
                                EXERCISES</span
                            >
                        </div>
                        <div class="form-grid">
                            <label
                                >Workout name<input
                                    v-model="workoutForm.workout_name"
                                    required
                                    maxlength="150"
                                    placeholder="e.g. Upper body" /></label
                            ><label
                                >Date<input
                                    v-model="workoutForm.workout_date"
                                    required
                                    type="date"
                            /></label>
                        </div>
                        <div
                            v-for="(row, index) in workoutForm.exercises"
                            :key="row.key"
                            class="exercise-editor"
                        >
                            <div class="section-bar">
                                <h3>
                                    Exercise
                                    {{ String(index + 1).padStart(2, '0') }}
                                </h3>
                                <button
                                    class="text-link"
                                    type="button"
                                    :disabled="saving"
                                    @click="
                                        workoutForm.exercises.splice(index, 1)
                                    "
                                >
                                    Remove
                                </button>
                            </div>
                            <label
                                >Exercise<select
                                    v-model="row.exercise_id"
                                    required
                                >
                                    <option value="" disabled>
                                        Select an exercise
                                    </option>
                                    <option
                                        v-for="exercise in exercises"
                                        :key="exercise.id"
                                        :value="exercise.id"
                                    >
                                        {{ exercise.exercise_name }}
                                    </option>
                                </select></label
                            >
                            <div class="set-grid">
                                <label
                                    >Sets<input
                                        v-model.number="row.sets"
                                        type="number"
                                        required
                                        min="1"
                                        max="100"
                                        step="1" /></label
                                ><label
                                    >Reps per set<input
                                        v-model.number="row.reps"
                                        type="number"
                                        min="0"
                                        max="4294967295"
                                        step="1" /></label
                                ><label
                                    >Weight (kg)<input
                                        v-model.number="row.weight"
                                        type="number"
                                        min="0"
                                        max="99999.99"
                                        step="0.01"
                                        placeholder="—" /></label
                                ><label
                                    >Reps in reserve<input
                                        v-model.number="row.rir"
                                        type="number"
                                        min="0"
                                        max="99.9"
                                        step="0.1"
                                        placeholder="—"
                                /></label>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button
                                class="button button-secondary"
                                type="button"
                                :disabled="
                                    workoutForm.exercises.length >= 10 ||
                                    !exercises.length ||
                                    saving
                                "
                                @click="addExercise"
                            >
                                + Add exercise</button
                            ><span class="form-help">{{
                                workoutForm.exercises.length === 10
                                    ? 'Maximum of 10 exercises reached.'
                                    : !exercises.length
                                      ? 'Your coach needs to add exercises to the catalogue first.'
                                      : 'Up to 10 exercises per workout.'
                            }}</span>
                        </div>
                        <label class="notes-label"
                            >Workout notes<textarea
                                v-model="workoutForm.notes"
                                rows="2"
                                maxlength="5000"
                                placeholder="Focus, technique, or anything to remember."
                            /></label
                        ><button class="button" :disabled="saving">
                            {{ saving ? 'Saving…' : 'Save workout' }} ↗
                        </button>
                    </form>
                    <div class="section-bar">
                        <h2>Your workouts</h2>
                        <span class="eyebrow">{{ workoutTotal }} SESSIONS</span>
                    </div>
                    <div v-if="!workouts.length" class="empty-state">
                        <p>
                            No sessions yet. Assigned training split workouts
                            will appear here.
                        </p>
                    </div>
                    <article
                        v-for="workout in workouts"
                        :key="workout.id"
                        class="workout-record"
                    >
                        <div class="section-bar">
                            <div>
                                <p class="eyebrow">
                                    {{ displayDate(workout.workout_date) }}
                                </p>
                                <h3>{{ workout.workout_name }}</h3>
                            </div>
                            <span class="status-pill">{{
                                workout.completed ? 'Completed' : 'Planned'
                            }}</span>
                        </div>
                        <p class="form-help">
                            {{ workingSets(workout) }} working sets planned ·
                            {{ workingSets(workout, true) }} completed
                        </p>
                        <p v-if="workout.notes" class="record-notes">
                            {{ workout.notes }}
                        </p>
                        <div
                            v-for="(
                                exercise, index
                            ) in workout.workout_exercises"
                            :key="exercise.id"
                            class="exercise-detail"
                        >
                            <strong
                                >{{ String(index + 1).padStart(2, '0') }} /
                                {{ exercise.exercise.exercise_name }}</strong
                            >
                            <div
                                v-for="(set, setIndex) in exercise.sets"
                                :key="set.id"
                                class="set-detail"
                            >
                                <form
                                    v-if="canCheckIn"
                                    class="logged-set-grid"
                                    @submit.prevent="saveSet(workout, set)"
                                >
                                    <span>Set {{ setIndex + 1 }}</span>
                                    <label
                                        >Reps<input
                                            v-model.number="set.reps"
                                            type="number"
                                            min="0"
                                            max="4294967295"
                                            step="1"
                                    /></label>
                                    <label
                                        >Weight (kg)<input
                                            v-model="set.weight"
                                            type="number"
                                            min="0"
                                            max="99999.99"
                                            step="0.01"
                                    /></label>
                                    <label
                                        >RIR<input
                                            v-model="set.rir"
                                            type="number"
                                            min="0"
                                            max="99.9"
                                            step="0.1"
                                    /></label>
                                    <label class="set-check"
                                        ><input
                                            v-model="set.completed"
                                            type="checkbox"
                                        />Done</label
                                    >
                                    <button
                                        class="button button-small button-secondary"
                                        :disabled="saving"
                                    >
                                        Save set
                                    </button>
                                </form>
                                <template v-else>
                                    <span>Set {{ setIndex + 1 }}</span
                                    ><span>{{ set.reps ?? '—' }} reps</span
                                    ><span>{{ set.weight ?? '—' }} kg</span
                                    ><span
                                        >{{ set.rir ?? '—' }} RIR
                                        {{ set.completed ? '✓' : '' }}</span
                                    >
                                </template>
                            </div>
                        </div>
                        <div class="row-actions">
                            <button
                                class="button button-small button-secondary"
                                :disabled="saving"
                                @click="toggleWorkout(workout)"
                            >
                                {{
                                    workout.completed
                                        ? 'Mark as planned'
                                        : 'Mark completed'
                                }}</button
                            ><button
                                class="text-link"
                                :disabled="saving"
                                @click="removeRecord('workouts', workout.id)"
                            >
                                Delete workout
                            </button>
                        </div>
                    </article>
                    <div v-if="workoutLast > 1" class="pagination">
                        <button
                            :disabled="workoutPage === 1 || loading"
                            @click="changePage('workout', -1)"
                        >
                            ← Previous</button
                        ><span>{{ workoutPage }} / {{ workoutLast }}</span
                        ><button
                            :disabled="workoutPage === workoutLast || loading"
                            @click="changePage('workout', 1)"
                        >
                            Next →
                        </button>
                    </div>
                </section>
            </template>
        </main>
    </div>
</template>
