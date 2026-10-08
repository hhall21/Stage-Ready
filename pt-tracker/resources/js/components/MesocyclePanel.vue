<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { api, displayDate, localDate, message } from '@/lib/tracker-api';
import type {
    Exercise,
    Mesocycle,
    Page,
    WorkoutTemplate,
} from '@/lib/tracker-api';
const props = defineProps<{
    clientId: number;
    exercises: Exercise[];
    canEdit: boolean;
}>();
const emit = defineEmits<{ changed: [] }>();
const cycles = ref<Mesocycle[]>([]),
    error = ref(''),
    success = ref('');
const loading = ref(false),
    saving = ref(false),
    open = ref(false),
    editId = ref<number | null>(null);
const weeks = ref(8);
const weekdays = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
    'Saturday',
    'Sunday',
];
const form = reactive({
    name: '',
    start_date: localDate(),
    end_date: '',
    notes: '',
    templates: [] as WorkoutTemplate[],
});
let version = 0;
function applyDuration() {
    const start = new Date(form.start_date + 'T12:00:00');
    start.setDate(start.getDate() + weeks.value * 7 - 1);
    form.end_date = [
        start.getFullYear(),
        String(start.getMonth() + 1).padStart(2, '0'),
        String(start.getDate()).padStart(2, '0'),
    ].join('-');
}
function newCycle() {
    editId.value = null;
    form.name = '';
    form.start_date = localDate();
    weeks.value = 8;
    applyDuration();
    form.notes = '';
    form.templates = [];
    open.value = true;
    error.value = '';
    success.value = '';
}
function edit(cycle: Mesocycle) {
    editId.value = cycle.id;
    form.name = cycle.name;
    form.start_date = cycle.start_date.slice(0, 10);
    form.end_date = cycle.end_date.slice(0, 10);
    form.notes = cycle.notes ?? '';
    form.templates = JSON.parse(
        JSON.stringify(cycle.templates),
    ) as WorkoutTemplate[];
    weeks.value = Math.ceil(
        (new Date(form.end_date).getTime() -
            new Date(form.start_date).getTime() +
            86400000) /
            604800000,
    );
    open.value = true;
    error.value = '';
    success.value = '';
}
async function load() {
    const currentVersion = ++version;
    loading.value = true;
    error.value = '';
    try {
        const items: Mesocycle[] = [];
        let page = 1,
            last = 1;
        do {
            const result = await api<Page<Mesocycle>>(
                '/api/clients/' + props.clientId + '/mesocycles?page=' + page,
            );
            items.push(...result.data);
            last = result.last_page;
            page++;
        } while (page <= last && currentVersion === version);
        if (currentVersion === version) cycles.value = items;
    } catch (cause) {
        if (currentVersion === version) error.value = message(cause);
    } finally {
        if (currentVersion === version) loading.value = false;
    }
}
function addTemplate() {
    if (form.templates.length < 14)
        form.templates.push({ name: '', weekday: 1, notes: '', exercises: [] });
}
function addExercise(template: WorkoutTemplate) {
    if (template.exercises.length < 10)
        template.exercises.push({
            exercise_id: '',
            notes: '',
            sets: [{ reps: 8, weight: null, rir: 2 }],
        });
}
async function save() {
    saving.value = true;
    error.value = '';
    success.value = '';
    const clientId = props.clientId;
    try {
        await api(
            '/api/clients/' +
                clientId +
                '/mesocycles' +
                (editId.value ? '/' + editId.value : ''),
            editId.value ? 'PUT' : 'POST',
            {
                ...form,
                notes: form.notes || null,
                templates: form.templates.map((template) => ({
                    ...template,
                    notes: template.notes || null,
                    exercises: template.exercises.map((exercise) => ({
                        ...exercise,
                        notes: exercise.notes || null,
                        sets: exercise.sets.map((set) =>
                            Object.fromEntries(
                                Object.entries(set).map(([key, value]) => [
                                    key,
                                    value === '' ? null : value,
                                ]),
                            ),
                        ),
                    })),
                })),
            },
        );
        if (clientId === props.clientId) {
            open.value = false;
            await load();
            success.value =
                'Mesocycle saved. Future sessions are ready; logged history is preserved.';
            emit('changed');
        }
    } catch (cause) {
        if (clientId === props.clientId) error.value = message(cause);
    } finally {
        saving.value = false;
    }
}
watch(
    () => props.clientId,
    () => {
        open.value = false;
        cycles.value = [];
        success.value = '';
        void load();
    },
    { immediate: true },
);
</script>
<template>
    <section class="mesocycle-section">
        <div class="section-bar">
            <div>
                <h2>Mesocycles</h2>
                <p class="form-help">
                    A weekly training schedule repeated for a set period.
                </p>
            </div>
            <button
                v-if="canEdit"
                class="button button-small"
                :disabled="saving"
                @click="newCycle"
            >
                + New mesocycle
            </button>
        </div>
        <p v-if="error" class="notice" role="alert">{{ error }}</p>
        <p v-if="success" class="notice" role="status">{{ success }}</p>
        <p v-if="loading" class="form-help" role="status">
            Loading training plans…
        </p>
        <form v-if="open && canEdit" class="panel" @submit.prevent="save">
            <div class="section-bar">
                <h2>{{ editId ? 'Edit mesocycle' : 'Build a mesocycle' }}</h2>
                <button
                    class="text-link"
                    type="button"
                    :disabled="saving"
                    @click="open = false"
                >
                    Cancel
                </button>
            </div>
            <div class="form-grid">
                <label
                    >Plan name<input
                        v-model="form.name"
                        required
                        maxlength="150"
                        placeholder="e.g. Eight-week strength block" /></label
                ><label
                    >Duration (weeks)<input
                        v-model.number="weeks"
                        type="number"
                        min="1"
                        max="52"
                        step="1"
                        required
                        @change="applyDuration" /></label
                ><label
                    >Start date<input
                        v-model="form.start_date"
                        type="date"
                        required
                        @change="applyDuration" /></label
                ><label
                    >End date<input
                        v-model="form.end_date"
                        type="date"
                        :min="form.start_date"
                        required /></label
                ><label class="full-width"
                    >Plan notes<textarea
                        v-model="form.notes"
                        maxlength="5000"
                        rows="2"
                        placeholder="Goals, recovery considerations or injury modifications."
                    />
                </label>
            </div>
            <article
                v-for="(template, index) in form.templates"
                :key="index"
                class="template-editor"
            >
                <div class="section-bar">
                    <h3>Weekly workout {{ index + 1 }}</h3>
                    <button
                        type="button"
                        class="text-link"
                        :disabled="saving"
                        @click="form.templates.splice(index, 1)"
                    >
                        Remove workout
                    </button>
                </div>
                <div class="form-grid">
                    <label
                        >Workout name<input
                            v-model="template.name"
                            required
                            maxlength="150"
                            placeholder="e.g. Push / Pull / Legs" /></label
                    ><label
                        >Repeat every<select v-model="template.weekday">
                            <option
                                v-for="(day, dayIndex) in weekdays"
                                :key="day"
                                :value="dayIndex + 1"
                            >
                                {{ day }}
                            </option>
                        </select></label
                    >
                </div>
                <label class="notes-label"
                    >Workout notes<textarea
                        v-model="template.notes"
                        maxlength="5000"
                        rows="2"
                    />
                </label>
                <div
                    v-for="(exercise, exerciseIndex) in template.exercises"
                    :key="exerciseIndex"
                    class="exercise-editor"
                >
                    <div class="section-bar">
                        <h3>Exercise {{ exerciseIndex + 1 }}</h3>
                        <button
                            class="text-link"
                            type="button"
                            :disabled="saving"
                            @click="template.exercises.splice(exerciseIndex, 1)"
                        >
                            Remove exercise
                        </button>
                    </div>
                    <label
                        >Exercise<select
                            v-model="exercise.exercise_id"
                            required
                        >
                            <option value="" disabled>Select exercise</option>
                            <option
                                v-for="option in exercises"
                                :key="option.id"
                                :value="option.id"
                            >
                                {{ option.exercise_name }}
                            </option>
                        </select></label
                    >
                    <label class="notes-label"
                        >Technique / injury notes<input
                            v-model="exercise.notes"
                            maxlength="5000"
                            placeholder="e.g. Swap for a pain-free movement"
                    /></label>
                    <div
                        v-for="(set, setIndex) in exercise.sets"
                        :key="setIndex"
                        class="plan-set-row"
                    >
                        <span>Set {{ setIndex + 1 }}</span
                        ><label
                            >Reps<input
                                v-model.number="set.reps"
                                type="number"
                                min="0"
                                max="4294967295"
                                step="1" /></label
                        ><label
                            >Weight (kg)<input
                                v-model.number="set.weight"
                                type="number"
                                min="0"
                                max="99999.99"
                                step="0.01" /></label
                        ><label
                            >RIR<input
                                v-model.number="set.rir"
                                type="number"
                                min="0"
                                max="99.9"
                                step="0.1" /></label
                        ><button
                            type="button"
                            class="text-link"
                            :disabled="exercise.sets.length <= 1 || saving"
                            @click="exercise.sets.splice(setIndex, 1)"
                        >
                            Remove
                        </button>
                    </div>
                    <button
                        class="text-link"
                        type="button"
                        :disabled="exercise.sets.length >= 100 || saving"
                        @click="
                            exercise.sets.push({
                                reps: 8,
                                weight: null,
                                rir: 2,
                            })
                        "
                    >
                        + Add set
                    </button>
                </div>
                <div class="form-actions">
                    <button
                        class="button button-small button-secondary"
                        type="button"
                        :disabled="
                            template.exercises.length >= 10 ||
                            !exercises.length ||
                            saving
                        "
                        @click="addExercise(template)"
                    >
                        + Add exercise</button
                    ><span class="form-help"
                        >{{ template.exercises.length }} / 10 exercises</span
                    >
                </div>
            </article>
            <div class="form-actions">
                <button
                    class="button button-secondary"
                    type="button"
                    :disabled="form.templates.length >= 14 || saving"
                    @click="addTemplate"
                >
                    + Add weekly workout</button
                ><button
                    class="button"
                    :disabled="
                        saving ||
                        !form.templates.length ||
                        form.templates.some(
                            (template) => !template.exercises.length,
                        )
                    "
                >
                    {{
                        saving
                            ? 'Saving…'
                            : editId
                              ? 'Update mesocycle'
                              : 'Assign mesocycle'
                    }}
                    ↗
                </button>
            </div>
            <p class="form-help">
                Sessions repeat on the selected weekdays through the end date.
                Changes affect future unlogged sessions; past and logged
                workouts stay unchanged. Dates cannot overlap another mesocycle.
            </p>
        </form>
        <div v-if="!loading && !cycles.length && !open" class="empty-state">
            <p>
                {{
                    canEdit
                        ? 'Create a mesocycle once, and the weekly workouts repeat for the whole block.'
                        : 'Your coach’s repeating training plan will appear here.'
                }}
            </p>
        </div>
        <article v-for="cycle in cycles" :key="cycle.id" class="panel">
            <div class="section-bar">
                <div>
                    <p class="eyebrow">
                        {{ displayDate(cycle.start_date) }} –
                        {{ displayDate(cycle.end_date) }}
                    </p>
                    <h3>{{ cycle.name }}</h3>
                </div>
                <button
                    v-if="canEdit"
                    class="button button-small button-secondary"
                    :disabled="saving"
                    @click="edit(cycle)"
                >
                    Edit mesocycle ↗
                </button>
            </div>
            <p v-if="cycle.notes" class="record-notes">{{ cycle.notes }}</p>
            <div
                v-for="(template, index) in cycle.templates"
                :key="index"
                class="mesocycle-workout"
            >
                <strong
                    >{{ weekdays[template.weekday - 1] }} /
                    {{ template.name }}</strong
                ><span
                    >{{ template.exercises.length }} exercises · repeats
                    weekly</span
                >
                <p v-if="template.notes" class="record-notes">
                    {{ template.notes }}
                </p>
                <p
                    v-for="(exercise, exerciseIndex) in template.exercises"
                    :key="exerciseIndex"
                    class="form-help"
                >
                    {{ exerciseIndex + 1 }}.
                    {{
                        exercises.find(
                            (option) => option.id === exercise.exercise_id,
                        )?.exercise_name ?? 'Exercise'
                    }}
                    · {{ exercise.sets.length }} sets<span
                        v-if="exercise.notes"
                    >
                        · {{ exercise.notes }}</span
                    >
                </p>
            </div>
        </article>
    </section>
</template>
