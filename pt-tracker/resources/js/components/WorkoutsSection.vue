<script setup lang="ts">
import DropdownSelect from '@/components/DropdownSelect.vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import TrainingDayPanel from '@/components/TrainingDayPanel.vue';
import TrainingSplitPanel from '@/components/TrainingSplitPanel.vue';
import {
    allPages,
    api,
    displayDate,
    localDate,
    message,
} from '@/lib/tracker-api';
import type { Exercise, Page, TrackerUser, Workout } from '@/lib/tracker-api';
const props = defineProps<{ clientId: number; user: TrackerUser }>();
const selected = props.clientId;
const user = props.user;
const canCheckIn = computed(() => user.role === 'client');
const base = computed(() => '/api/clients/' + selected);
const loading = ref(true),
    saving = ref(false),
    error = ref(''),
    success = ref('');
let loadVersion = 0;
function handleError(cause: unknown) {
    error.value = message(cause);
}
onBeforeUnmount(() => loadVersion++);

const workouts = ref<Workout[]>([]),
    exercises = ref<Exercise[]>([]);
let catalogueLoaded = false;
const workoutPage = ref(1),
    workoutLast = ref(1),
    workoutTotal = ref(0);
const trainingDayPanel = ref<InstanceType<typeof TrainingDayPanel> | null>(
    null,
);
type ExerciseRow = {
    key: number;
    exercise_id: number | '';
    sets: number;
    reps: number | '';
    weight: number | '';
    rir: number | '';
};
let rowKey = 0;
const workoutForm = reactive({
    workout_name: '',
    workout_date: localDate(),
    notes: '',
    exercises: [] as ExerciseRow[],
});

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

async function loadRecords() {
    const version = ++loadVersion;
    loading.value = true;
    error.value = '';
    try {
        const [training, catalogue] = await Promise.all([
            api<Page<Workout>>(
                base.value + '/workouts?page=' + workoutPage.value,
            ),
            catalogueLoaded
                ? Promise.resolve(exercises.value)
                : allPages<Exercise>('/api/exercises'),
        ]);
        if (version !== loadVersion) return;
        exercises.value = catalogue;
        catalogueLoaded = true;
        workouts.value = training.data;
        workoutLast.value = training.last_page;
        workoutTotal.value = training.total;
    } catch (cause) {
        if (version === loadVersion) handleError(cause);
    } finally {
        if (version === loadVersion) loading.value = false;
    }
}
async function refreshTraining() {
    await loadRecords();
    await trainingDayPanel.value?.reload();
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

async function removeRecord(_kind: 'workouts', id: number) {
    if (
        !window.confirm(
            'Delete this workout and its exercise sets? This cannot be undone.',
        )
    )
        return;
    saving.value = true;
    error.value = '';
    try {
        await api(base.value + '/workouts/' + id, 'DELETE');
        workoutPage.value = 1;
        await refreshTraining();
        success.value = 'Workout deleted.';
    } catch (cause) {
        handleError(cause);
    } finally {
        saving.value = false;
    }
}
async function changePage(_kind: 'workout', delta: number) {
    workoutPage.value += delta;
    await loadRecords();
}
onMounted(loadRecords);
</script>
<template>
    <section aria-label="Workouts">
        <p v-if="error" class="notice" role="alert">
            {{ error }}
            <button class="text-link" :disabled="loading" @click="loadRecords">
                Retry
            </button>
        </p>
        <p v-if="success" class="notice" role="status">{{ success }}</p>
        <p v-if="loading" class="loading-message" role="status">
            Loading records…
        </p>
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
                    >{{ workoutForm.exercises.length }} / 10 EXERCISES</span
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
                        @click="workoutForm.exercises.splice(index, 1)"
                    >
                        Remove
                    </button>
                </div>
                <label
                    >Exercise<DropdownSelect v-model="row.exercise_id" required>
                        <option value="" disabled>Select an exercise</option>
                        <option
                            v-for="exercise in exercises"
                            :key="exercise.id"
                            :value="exercise.id"
                        >
                            {{ exercise.exercise_name }}
                        </option>
                    </DropdownSelect></label
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
                          ? 'Add exercises to the catalogue before prescribing a workout.'
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
            <h2>
                {{
                    user.role === 'coach' ? 'Client workouts' : 'Your workouts'
                }}
            </h2>
            <span class="eyebrow">{{ workoutTotal }} SESSIONS</span>
        </div>
        <div v-if="!workouts.length" class="empty-state">
            <p>
                No sessions yet. Assigned training split workouts will appear
                here.
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
                v-for="(exercise, index) in workout.workout_exercises"
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
                        workout.completed ? 'Mark as planned' : 'Mark completed'
                    }}</button
                ><button
                    v-if="user.role === 'coach'"
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
