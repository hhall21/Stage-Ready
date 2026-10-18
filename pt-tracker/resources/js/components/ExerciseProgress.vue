<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { api, displayDate, message } from '@/lib/tracker-api';
import type { Exercise } from '@/lib/tracker-api';
const props = defineProps<{ clientId: number }>();
type Point = {
    workout_id: number;
    date: string;
    workout_name: string;
    max_weight_kg: number;
    max_reps: number;
    logged_sets: number;
    volume_kg: number;
};
type Result = {
    exercises: Exercise[];
    exercise_id: number | null;
    data: Point[];
};
const exercises = ref<Exercise[]>([]),
    points = ref<Point[]>([]);
const selected = ref<number | null>(null),
    error = ref('');
const loading = ref(true);
const metric = ref<'max_weight_kg' | 'max_reps'>('max_weight_kg');
let version = 0;
const label = computed(() =>
    metric.value === 'max_weight_kg'
        ? 'Heaviest set (kg)'
        : 'Most reps in a set',
);
const latest = computed(() => points.value.at(-1));
const scale = computed(() => {
    const values = points.value.map((point) => point[metric.value]);
    return values.length
        ? {
              low: Math.max(0, Math.min(...values) - 1),
              high: Math.max(...values) + 1,
          }
        : { low: 0, high: 1 };
});
const coordinates = computed(() => {
    const { low, high } = scale.value;
    return points.value.map((point, index) => ({
        ...point,
        x: 45 + (index / Math.max(1, points.value.length - 1)) * 610,
        y: 170 - ((point[metric.value] - low) / (high - low)) * 130,
    }));
});
const line = computed(() =>
    coordinates.value.map((point) => point.x + ',' + point.y).join(' '),
);
async function load(reset = false) {
    const current = ++version;
    if (reset) {
        selected.value = null;
        points.value = [];
        exercises.value = [];
    }
    loading.value = true;
    error.value = '';
    try {
        const result = await api<Result>(
            '/api/clients/' +
                props.clientId +
                '/exercise-progress' +
                (selected.value ? '?exercise_id=' + selected.value : ''),
        );
        if (current !== version) return;
        exercises.value = result.exercises;
        selected.value = result.exercise_id;
        points.value = result.data;
    } catch (cause) {
        if (current === version) {
            points.value = [];
            error.value = message(cause);
        }
    } finally {
        if (current === version) loading.value = false;
    }
}
watch(
    () => props.clientId,
    () => {
        void load(true);
    },
    { immediate: true },
);
</script>
<template>
    <section class="panel exercise-progress">
        <div class="section-bar">
            <h2>Exercise progress</h2>
            <span class="eyebrow">YOUR LOGGED TRAINING</span>
        </div>
        <p v-if="error" class="notice" role="alert">
            {{ error }} <button class="text-link" @click="load()">Retry</button>
        </p>
        <label v-if="exercises.length" for="progress-exercise"
            >Exercise<select
                id="progress-exercise"
                v-model="selected"
                :disabled="loading"
                @change="load()"
            >
                <option
                    v-for="exercise in exercises"
                    :key="exercise.id"
                    :value="exercise.id"
                >
                    {{ exercise.exercise_name }}
                </option>
            </select></label
        >
        <p v-if="loading" class="loading-message" role="status">
            Loading exercise history…
        </p>
        <template v-else-if="points.length">
            <div class="progress-stats">
                <div>
                    <span>Latest heaviest set</span
                    ><strong
                        >{{ latest?.max_weight_kg }} <small>kg</small></strong
                    >
                </div>
                <div>
                    <span>Latest most reps</span
                    ><strong>{{ latest?.max_reps }} <small>reps</small></strong>
                </div>
                <div>
                    <span>Sessions shown</span
                    ><strong>{{ points.length }}</strong>
                </div>
            </div>
            <div
                class="row-actions progress-switch"
                role="group"
                aria-label="Chart measure"
            >
                <button
                    class="button button-small"
                    :class="{ 'button-secondary': metric !== 'max_weight_kg' }"
                    :aria-pressed="metric === 'max_weight_kg'"
                    @click="metric = 'max_weight_kg'"
                >
                    Weight</button
                ><button
                    class="button button-small"
                    :class="{ 'button-secondary': metric !== 'max_reps' }"
                    :aria-pressed="metric === 'max_reps'"
                    @click="metric = 'max_reps'"
                >
                    Reps
                </button>
            </div>
            <p class="form-help">{{ label }}</p>
            <svg
                class="exercise-chart"
                viewBox="0 0 700 210"
                role="img"
                :aria-label="
                    label +
                    ' across ' +
                    points.length +
                    ' logged sessions. The table below contains each reading.'
                "
            >
                <line x1="45" y1="175" x2="655" y2="175" stroke="#555" />
                <line x1="45" y1="40" x2="655" y2="40" stroke="#333" />
                <text
                    x="35"
                    y="44"
                    fill="white"
                    font-size="10"
                    text-anchor="end"
                >
                    {{ scale.high }}
                </text>
                <text
                    x="35"
                    y="174"
                    fill="white"
                    font-size="10"
                    text-anchor="end"
                >
                    {{ scale.low }}
                </text>
                <polyline
                    :points="line"
                    fill="none"
                    stroke="white"
                    stroke-width="2"
                />
                <g v-for="point in coordinates" :key="point.workout_id">
                    <circle :cx="point.x" :cy="point.y" r="4" fill="white">
                        <title>
                            {{ displayDate(point.date) }}: {{ point[metric] }}
                            {{ metric === 'max_weight_kg' ? 'kg' : 'reps' }}
                        </title>
                    </circle>
                </g>
                <text x="45" y="202" fill="white" font-size="11">
                    {{ displayDate(points[0].date) }}
                </text>
                <text
                    x="655"
                    y="202"
                    fill="white"
                    font-size="11"
                    text-anchor="end"
                >
                    {{ displayDate(latest!.date) }}
                </text>
            </svg>
            <p class="form-help">
                Up to 60 logged sessions, ordered from oldest to newest. Only
                completed sets with a recorded weight and at least one rep are
                included. Weight and rep records can come from different sets.
            </p>
            <details class="progress-history">
                <summary>View session readings</summary>
                <div class="table-scroll">
                    <table>
                        <caption class="form-help">
                            {{
                                label
                            }}
                            and training volume by session
                        </caption>
                        <thead>
                            <tr>
                                <th scope="col">Session</th>
                                <th scope="col">Heaviest set</th>
                                <th scope="col">Most reps</th>
                                <th scope="col">Logged sets</th>
                                <th scope="col">Volume</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="point in [...points].reverse()"
                                :key="point.workout_id"
                            >
                                <th scope="row">
                                    {{ displayDate(point.date)
                                    }}<span>{{ point.workout_name }}</span>
                                </th>
                                <td>{{ point.max_weight_kg }} kg</td>
                                <td>{{ point.max_reps }}</td>
                                <td>{{ point.logged_sets }}</td>
                                <td>
                                    {{
                                        point.volume_kg.toLocaleString('en-GB')
                                    }}
                                    kg
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="form-help">
                    Volume is the sum of weight × reps across the included sets.
                </p>
            </details>
        </template>
        <p v-else-if="!error" class="empty-state">
            No exercise history yet. Complete a set with its weight and reps to
            start tracking progress. Future sessions are shown here once their
            training date arrives.
        </p>
    </section>
</template>
