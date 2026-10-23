<script setup lang="ts">
import { ref, watch } from 'vue';
import { api, displayDate, localDate, message } from '@/lib/tracker-api';
import type { TrainingSchedule, Workout } from '@/lib/tracker-api';

const props = defineProps<{ clientId: number; canLog: boolean }>();
const emit = defineEmits<{ changed: [] }>();
const state = ref<TrainingSchedule | null>(null),
    date = ref(localDate());
const loading = ref(false),
    busy = ref(false),
    error = ref(''),
    success = ref('');
const selectedDay = ref<number | null>(null);
let version = 0;
function apply(data: TrainingSchedule) {
    state.value = data;
    selectedDay.value = data.selection ?? null;
}
async function load() {
    const current = ++version;
    date.value ||= localDate();
    loading.value = true;
    error.value = '';
    try {
        const result = await api<{ data: TrainingSchedule }>(
            `/api/clients/${props.clientId}/training-schedule?date=${date.value}`,
        );
        if (current === version) apply(result.data);
    } catch (cause) {
        if (current === version) {
            state.value = null;
            error.value = message(cause);
        }
    } finally {
        if (current === version) loading.value = false;
    }
}
async function select() {
    const clientId = props.clientId;
    busy.value = true;
    error.value = success.value = '';
    try {
        const result = await api<{ data: TrainingSchedule }>(
            `/api/clients/${clientId}/training-schedule/selection`,
            'PUT',
            { date: state.value?.date, day_number: selectedDay.value },
        );
        if (clientId === props.clientId) {
            apply(result.data);
            success.value =
                'Split updated. The following days now continue from your choice.';
            emit('changed');
        }
    } catch (cause) {
        if (clientId === props.clientId) error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
async function saveSet(
    set: Workout['workout_exercises'][number]['sets'][number],
) {
    const workout = state.value?.workout;
    if (!workout) return;
    await savePerformance(`/workouts/${workout.id}/sets/${set.id}`, {
        reps: set.reps === null || String(set.reps) === '' ? null : set.reps,
        weight: set.weight === '' ? null : set.weight,
        rir: set.rir === '' ? null : set.rir,
        completed: set.completed,
    });
}
async function savePerformance(path: string, payload: unknown) {
    const clientId = props.clientId;
    busy.value = true;
    error.value = success.value = '';
    try {
        await api(`/api/clients/${clientId}${path}`, 'PATCH', payload);
        if (clientId === props.clientId) {
            await load();
            success.value = 'Performance saved.';
            emit('changed');
        }
    } catch (cause) {
        if (clientId === props.clientId) error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
watch(
    () => props.clientId,
    () => {
        state.value = null;
        date.value = localDate();
        success.value = '';
        void load();
    },
    { immediate: true },
);
defineExpose({ reload: load });
</script>

<template>
    <section class="panel" aria-label="Scheduled training day">
        <div class="section-bar">
            <h2>
                {{
                    date === localDate()
                        ? 'Today’s workout'
                        : 'Scheduled workout'
                }}
            </h2>
            <label
                >Workout date<input
                    v-model="date"
                    type="date"
                    :max="canLog ? localDate() : undefined"
                    :disabled="loading || busy"
                    @change="load"
            /></label>
        </div>
        <p v-if="error" class="notice" role="alert">
            {{ error }}
            <button
                type="button"
                class="text-link"
                :disabled="busy || loading"
                @click="load"
            >
                Refresh
            </button>
        </p>
        <p v-if="success" class="notice" role="status">{{ success }}</p>
        <p v-if="loading" class="loading-message" role="status">
            Loading scheduled workout…
        </p>
        <template v-else-if="state">
            <template v-if="state.split">
                <p class="eyebrow">
                    {{ state.split.name }} ·
                    {{
                        state.is_extra_rest
                            ? 'Extra rest day'
                            : `Day ${state.day_number} of ${state.split.days.length}`
                    }}
                </p>
                <h3>{{ state.day_name }}</h3>
                <p v-if="state.day_notes" class="record-notes">
                    {{ state.day_notes }}
                </p>
                <p v-if="state.is_rest_day" class="form-help">
                    {{
                        state.is_extra_rest
                            ? 'The split is paused for this day. The planned split day moves to tomorrow.'
                            : 'A planned rest day. The split continues with the next day tomorrow.'
                    }}
                </p>
                <p v-if="state.tomorrow" class="form-help">
                    Next day: {{ state.tomorrow.split_name }} · Day
                    {{ state.tomorrow.day_number }} / {{ state.tomorrow.name }}
                </p>
                <form
                    v-if="canLog"
                    class="split-selection"
                    @submit.prevent="select"
                >
                    <label
                        >What did you complete on this day?<select
                            v-model="selectedDay"
                            :disabled="busy || state.history_locked"
                        >
                            <option :value="null">
                                Follow the planned split (Day
                                {{ state.expected_day }})
                            </option>
                            <option :value="0">
                                Extra rest day — pause the split
                            </option>
                            <option
                                v-for="(day, index) in state.split.days"
                                :key="index"
                                :value="index + 1"
                            >
                                Day {{ index + 1 }} —
                                {{ day.is_rest_day ? 'Rest day' : day.name }}
                            </option>
                        </select></label
                    >
                    <p class="form-help">
                        Choose a different training or rest day when agreed with
                        your coach. Tomorrow continues from the day you choose.
                        Extra rest keeps the same split day for tomorrow.
                    </p>
                    <p v-if="state.history_locked" class="form-help">
                        This workout has logged performance and keeps its
                        original split day.
                    </p>
                    <button v-else class="button button-small" :disabled="busy">
                        {{ busy ? 'Updating…' : 'Update training day' }}
                    </button>
                    <p v-if="!state.history_locked" class="form-help">
                        Make your choice before entering performance.
                    </p>
                </form>
                <template v-if="state.workout">
                    <p class="status-pill">
                        {{
                            state.workout.completed
                                ? 'Workout completed'
                                : 'Coach-prescribed workout'
                        }}
                    </p>
                    <div
                        v-for="entry in state.workout.workout_exercises"
                        :key="entry.id"
                        class="exercise-detail"
                    >
                        <h4>{{ entry.exercise.exercise_name }}</h4>
                        <p v-if="entry.notes" class="form-help">
                            {{ entry.notes }}
                        </p>
                        <div
                            v-for="(set, index) in entry.sets"
                            :key="set.id"
                            class="set-detail"
                        >
                            <form
                                v-if="canLog"
                                class="logged-set-grid"
                                @submit.prevent="saveSet(set)"
                            >
                                <span>Set {{ index + 1 }}</span>
                                <label
                                    >Reps<input
                                        v-model.number="set.reps"
                                        type="number"
                                        min="0"
                                        max="4294967295"
                                        step="1"
                                        :disabled="busy"
                                /></label>
                                <label
                                    >Weight (kg)<input
                                        v-model="set.weight"
                                        type="number"
                                        min="0"
                                        max="99999.99"
                                        step="0.01"
                                        :disabled="busy"
                                /></label>
                                <label
                                    >RIR<input
                                        v-model="set.rir"
                                        type="number"
                                        min="0"
                                        max="99.9"
                                        step="0.1"
                                        :disabled="busy"
                                /></label>
                                <label class="set-check"
                                    ><input
                                        v-model="set.completed"
                                        type="checkbox"
                                        :disabled="busy"
                                    />Done</label
                                >
                                <button
                                    class="button button-small button-secondary"
                                    :disabled="busy"
                                >
                                    Save set
                                </button>
                            </form>
                            <p v-else class="form-help">
                                Set {{ index + 1 }} · {{ set.reps ?? '—' }} reps
                                · {{ set.weight ?? '—' }} kg ·
                                {{ set.rir ?? '—' }} RIR
                                {{ set.completed ? '✓' : '' }}
                            </p>
                        </div>
                    </div>
                    <button
                        v-if="canLog"
                        class="button button-small"
                        :disabled="busy"
                        @click="
                            savePerformance(`/workouts/${state.workout.id}`, {
                                completed: !state.workout.completed,
                            })
                        "
                    >
                        {{
                            state.workout.completed
                                ? 'Mark not completed'
                                : 'Mark workout completed'
                        }}
                    </button>
                </template>
            </template>
            <p v-else-if="state.next_split" class="form-help">
                {{ state.next_split.name }} starts on
                {{ displayDate(state.next_split.start_date) }}.
                {{
                    canLog
                        ? 'Your first workout will appear then.'
                        : 'The client’s first workout will appear then.'
                }}
            </p>
            <p v-else class="form-help">
                {{
                    canLog
                        ? 'Your coach has not assigned a training split for this date. Your earlier workouts remain in your history.'
                        : 'Assign a training split and choose its start date to schedule this client’s workouts.'
                }}
            </p>
        </template>
    </section>
</template>

<style scoped>
.split-selection {
    margin: 24px 0;
    padding: 20px 0;
    border-top: 1px solid #333;
    border-bottom: 1px solid #333;
}
.section-bar > label {
    max-width: 200px;
}
</style>
