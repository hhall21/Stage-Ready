<script setup lang="ts">
import DropdownSelect from '@/components/DropdownSelect.vue';
import DropdownDetails from '@/components/DropdownDetails.vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import PerformanceChart from '@/components/PerformanceChart.vue';
import { api, displayDate, localDate, message } from '@/lib/tracker-api';
import type { Exercise } from '@/lib/tracker-api';
import type {
    ChartReading,
    PerformanceResult,
    PerformanceSession,
} from '@/lib/training-analytics';

const props = defineProps<{ clientId: number }>();
type Comparison = 'time' | 'calories' | 'bodyweight';
const comparison = ref<Comparison>('time');
const calorieSource = ref<'prescribed' | 'logged'>('prescribed');
const period = ref('all');
const selectedExercise = ref<number | null>(null);
const exercises = ref<Exercise[]>([]);
const sessions = ref<PerformanceSession[]>([]);
const loading = ref(true),
    error = ref('');
const selectedId = ref<number | null>(null);
let version = 0;
const calorieKey = computed(() =>
    calorieSource.value === 'prescribed'
        ? 'prescribed_calories_kcal'
        : 'logged_calories_kcal',
);
const calorieLabel = computed(() =>
    calorieSource.value === 'prescribed'
        ? 'Prescribed training-day calories'
        : 'Logged food calories',
);
const xLabel = computed(() =>
    comparison.value === 'time'
        ? 'Session date'
        : comparison.value === 'calories'
          ? `${calorieLabel.value} (kcal)`
          : 'Seven-day average bodyweight (kg)',
);
const selected = computed(
    () =>
        sessions.value.find(
            (session) => session.workout_id === selectedId.value,
        ) ?? sessions.value.at(-1),
);
const matched = computed(
    () => sessions.value.filter((session) => xValue(session) !== null).length,
);
function xValue(session: PerformanceSession): number | null {
    return comparison.value === 'time'
        ? Date.parse(session.date + 'T12:00:00Z')
        : comparison.value === 'calories'
          ? session[calorieKey.value]
          : session.average_bodyweight_kg;
}
function readings(metric: 'max_weight_kg' | 'max_reps'): ChartReading[] {
    return sessions.value.map((session) => ({
        id: session.workout_id,
        x: xValue(session),
        y: session[metric],
        description: `${displayDate(session.date)}, ${session.workout_name}: ${session[metric] === null ? 'weight not recorded' : session[metric] + (metric === 'max_weight_kg' ? ' kg' : ' reps')}. ${xLabel.value}: ${xValue(session) === null ? 'not recorded' : comparison.value === 'time' ? displayDate(session.date) : xValue(session)}.`,
    }));
}
function signed(value: number) {
    return (value > 0 ? '+' : '') + value.toFixed(2);
}
function calorieReadings(session: PerformanceSession) {
    return calorieSource.value === 'prescribed'
        ? `${session.prescribed_meal_readings}/${session.prescribed_meal_count} meals with calories`
        : `${session.logged_item_readings}/${session.logged_item_count} food items with calories · ${session.logged_meal_count} meals logged`;
}
async function load(reset = false) {
    const request = ++version;
    if (reset) {
        selectedExercise.value = null;
        exercises.value = [];
    }
    sessions.value = [];
    selectedId.value = null;
    loading.value = true;
    error.value = '';
    const params = new URLSearchParams();
    if (selectedExercise.value !== null)
        params.set('exercise_id', String(selectedExercise.value));
    if (period.value !== 'all') {
        const start = new Date(localDate() + 'T12:00:00Z');
        start.setUTCDate(start.getUTCDate() - Number(period.value) + 1);
        params.set('from', start.toISOString().slice(0, 10));
        params.set('to', localDate());
    }
    try {
        const result = await api<PerformanceResult>(
            `/api/clients/${props.clientId}/exercise-progress?${params}`,
        );
        if (request !== version) return;
        exercises.value = result.exercises;
        selectedExercise.value = result.exercise_id;
        sessions.value = result.data;
    } catch (cause) {
        if (request === version) error.value = message(cause);
    } finally {
        if (request === version) loading.value = false;
    }
}
watch(
    () => props.clientId,
    () => void load(true),
    { immediate: true },
);
onBeforeUnmount(() => version++);
</script>

<template>
    <section
        class="training-analytics"
        aria-label="Training performance analytics"
    >
        <div class="panel">
            <div class="section-bar">
                <h2>Training analytics</h2>
                <span class="eyebrow">PERFORMANCE IN CONTEXT</span>
            </div>
            <p class="form-help">
                Compare one exercise at a time. Weight and reps use completed
                working sets with positive reps; records may come from different
                sets.
            </p>
            <div class="analytics-filters">
                <label
                    >Exercise<DropdownSelect
                        v-model="selectedExercise"
                        aria-label="Exercise"
                        :disabled="loading || !exercises.length"
                        @change="load()"
                    >
                        <option v-if="!exercises.length" :value="null">
                            No logged exercises yet
                        </option>
                        <option
                            v-for="exercise in exercises"
                            :key="exercise.id"
                            :value="exercise.id"
                        >
                            {{ exercise.exercise_name }}
                        </option>
                    </DropdownSelect></label
                >
                <label
                    >History<DropdownSelect
                        v-model="period"
                        aria-label="History"
                        :disabled="loading"
                        @change="load()"
                    >
                        <option value="all">Latest 60 sessions</option>
                        <option value="30">Last 30 days</option>
                        <option value="90">Last 90 days</option>
                        <option value="180">Last 180 days</option>
                        <option value="365">Last 365 days</option>
                    </DropdownSelect></label
                >
            </div>
            <div
                class="row-actions progress-switch"
                role="group"
                aria-label="Performance comparison"
            >
                <button
                    v-for="option in [
                        { id: 'time', label: 'Weight over time' },
                        { id: 'calories', label: 'Daily calories' },
                        { id: 'bodyweight', label: 'Average bodyweight' },
                    ]"
                    :key="option.id"
                    class="button button-small"
                    :class="{ 'button-secondary': comparison !== option.id }"
                    :aria-pressed="comparison === option.id"
                    @click="comparison = option.id as Comparison"
                >
                    {{ option.label }}
                </button>
            </div>
            <label
                v-if="comparison === 'calories'"
                class="analytics-calorie-source"
                >Calorie source<DropdownSelect
                    v-model="calorieSource"
                    aria-label="Calorie source"
                >
                    <option value="prescribed">Prescribed calories</option>
                    <option value="logged">Logged food calories</option>
                </DropdownSelect></label
            >
            <p v-if="comparison === 'time'" class="form-help">
                Dates are spaced by calendar time. Gaps in recorded weights
                remain gaps.
            </p>
            <p v-else-if="comparison === 'calories'" class="form-help">
                {{
                    calorieSource === 'prescribed'
                        ? 'Uses the training-day meal prescription effective on each session date. Every prescribed meal needs a calorie reading to plot a total.'
                        : 'Totals only the food logged on the session date, which may cover part of the day. Dates with missing item calories or empty meal logs are excluded from this comparison.'
                }}
            </p>
            <p v-else class="form-help">
                Each session uses recorded weigh-ins from that date and the
                preceding six days. Missing readings are excluded; future
                weigh-ins never influence earlier sessions.
            </p>
            <p v-if="error" class="notice" role="alert">
                {{ error }}
                <button class="text-link" @click="load()">Retry</button>
            </p>
        </div>
        <p v-if="loading" class="loading-message" role="status">
            Loading performance comparisons…
        </p>
        <template v-else-if="sessions.length">
            <p class="analytics-coverage form-help">
                {{ matched }} of {{ sessions.length }} sessions have
                {{
                    comparison === 'time'
                        ? 'a training date'
                        : comparison === 'calories'
                          ? 'matching calorie readings'
                          : 'matching bodyweight readings'
                }}. Up to 60 sessions per exercise.
            </p>
            <div class="analytics-charts" :data-comparison="comparison">
                <PerformanceChart
                    :title="
                        comparison === 'time'
                            ? 'Weight over time'
                            : 'Heaviest completed set'
                    "
                    unit="kg"
                    :x-label="xLabel"
                    :time-axis="comparison === 'time'"
                    :readings="readings('max_weight_kg')"
                    :selected-id="selected?.workout_id ?? null"
                    colour="var(--accent-teal)"
                    @select="selectedId = $event"
                />
                <PerformanceChart
                    v-if="comparison !== 'time'"
                    title="Most reps in a completed set"
                    unit="reps"
                    :x-label="xLabel"
                    :time-axis="false"
                    :readings="readings('max_reps')"
                    :selected-id="selected?.workout_id ?? null"
                    colour="var(--accent-sand)"
                    @select="selectedId = $event"
                />
            </div>
            <section
                v-if="selected"
                class="panel analytics-inspector"
                aria-label="Selected training session"
                aria-live="polite"
            >
                <div class="section-bar">
                    <h3>
                        {{ displayDate(selected.date) }} ·
                        {{ selected.workout_name }}
                    </h3>
                    <span class="eyebrow">SELECT A GRAPH POINT TO INSPECT</span>
                </div>
                <dl class="analytics-readings">
                    <div>
                        <dt>Heaviest set</dt>
                        <dd>
                            {{
                                selected.max_weight_kg === null
                                    ? 'Not recorded'
                                    : selected.max_weight_kg + ' kg'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt>Most reps</dt>
                        <dd>{{ selected.max_reps }}</dd>
                    </div>
                    <div>
                        <dt>Volume</dt>
                        <dd>{{ selected.working_sets }} working sets</dd>
                    </div>
                    <div>
                        <dt>{{ calorieLabel }}</dt>
                        <dd>
                            {{
                                selected[calorieKey] === null
                                    ? 'Not recorded'
                                    : selected[calorieKey] + ' kcal'
                            }}
                        </dd>
                        <small>{{ calorieReadings(selected) }}</small>
                    </div>
                    <div>
                        <dt>Seven-day average bodyweight</dt>
                        <dd>
                            {{
                                selected.average_bodyweight_kg === null
                                    ? 'Not recorded'
                                    : selected.average_bodyweight_kg.toFixed(
                                          2,
                                      ) + ' kg'
                            }}
                        </dd>
                        <small
                            >{{ selected.bodyweight_readings }} weigh-ins</small
                        >
                    </div>
                    <div>
                        <dt>Versus seven days earlier</dt>
                        <dd>
                            {{
                                selected.bodyweight_change_from_7_days_ago_kg ===
                                null
                                    ? 'More readings needed'
                                    : signed(
                                          selected.bodyweight_change_from_7_days_ago_kg,
                                      ) + ' kg'
                            }}
                        </dd>
                        <small
                            >{{ selected.prior_bodyweight_readings }} weigh-ins
                            in the earlier window</small
                        >
                    </div>
                </dl>
            </section>
            <DropdownDetails class="panel progress-history">
                <template #summary>View all session readings</template>
                <div class="table-scroll">
                    <table>
                        <caption>
                            Logged training,
                            {{
                                calorieLabel.toLowerCase()
                            }}
                            and rolling seven-day bodyweight
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Session</th>
                                <th scope="col">Heaviest set</th>
                                <th scope="col">Most reps</th>
                                <th scope="col">Working sets</th>
                                <th scope="col">Calories</th>
                                <th scope="col">Seven-day bodyweight</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="session in [...sessions].reverse()"
                                :key="session.workout_id"
                            >
                                <th scope="row">
                                    {{ displayDate(session.date)
                                    }}<span>{{ session.workout_name }}</span>
                                </th>
                                <td>
                                    {{
                                        session.max_weight_kg === null
                                            ? 'Not recorded'
                                            : session.max_weight_kg + ' kg'
                                    }}
                                </td>
                                <td>{{ session.max_reps }}</td>
                                <td>{{ session.working_sets }}</td>
                                <td>
                                    {{
                                        session[calorieKey] === null
                                            ? 'Not recorded'
                                            : session[calorieKey] + ' kcal'
                                    }}<small>{{
                                        calorieReadings(session)
                                    }}</small
                                    ><small
                                        v-if="
                                            calorieSource === 'logged' &&
                                            session.logged_calories_kcal ===
                                                null &&
                                            session.logged_calorie_subtotal_kcal !==
                                                null
                                        "
                                        >{{
                                            session.logged_calorie_subtotal_kcal
                                        }}
                                        kcal subtotal; incomplete log</small
                                    >
                                </td>
                                <td>
                                    {{
                                        session.average_bodyweight_kg === null
                                            ? 'Not recorded'
                                            : session.average_bodyweight_kg.toFixed(
                                                  2,
                                              ) + ' kg'
                                    }}<small
                                        >{{
                                            session.bodyweight_readings
                                        }}
                                        weigh-ins</small
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </DropdownDetails>
        </template>
        <p v-else-if="!error" class="panel empty-state">
            No logged working sets in this history range. Choose another
            exercise or a longer history. Completed sets with positive reps
            appear here, including bodyweight sets without a recorded load.
        </p>
    </section>
</template>
