<script setup lang="ts">
import DropdownSelect from '@/components/DropdownSelect.vue';
import { reactive, ref, watch } from 'vue';
import { api, displayDate, localDate, message } from '@/lib/tracker-api';
import type {
    Exercise,
    Page,
    SplitDay,
    TrainingSplit,
} from '@/lib/tracker-api';

const props = defineProps<{
    clientId: number;
    exercises: Exercise[];
    canEdit: boolean;
}>();
const emit = defineEmits<{ changed: [] }>();
const splits = ref<TrainingSplit[]>([]);
const loading = ref(false),
    saving = ref(false),
    open = ref(false);
const error = ref(''),
    success = ref(''),
    editId = ref<number | null>(null);
const form = reactive({
    name: '',
    start_date: localDate(),
    notes: '',
    days: [] as SplitDay[],
});
let version = 0;
async function load() {
    const current = ++version;
    loading.value = true;
    error.value = '';
    try {
        const items: TrainingSplit[] = [];
        let page = 1,
            last = 1;
        do {
            const result = await api<Page<TrainingSplit>>(
                `/api/clients/${props.clientId}/training-splits?page=${page}`,
            );
            items.push(...result.data);
            last = result.last_page;
            page++;
        } while (page <= last && current === version);
        if (current === version) splits.value = items;
    } catch (cause) {
        if (current === version) error.value = message(cause);
    } finally {
        if (current === version) loading.value = false;
    }
}
function addDay(rest = false) {
    if (form.days.length < 15)
        form.days.push({
            name: rest ? 'Rest day' : '',
            is_rest_day: rest,
            notes: '',
            exercises: [],
        });
}
function create() {
    editId.value = null;
    Object.assign(form, {
        name: '',
        start_date: localDate(),
        notes: '',
        days: [],
    });
    addDay();
    open.value = true;
    error.value = success.value = '';
}
function edit(split: TrainingSplit) {
    editId.value = split.id;
    Object.assign(form, {
        name: split.name,
        start_date: split.start_date.slice(0, 10),
        notes: split.notes ?? '',
        days: JSON.parse(JSON.stringify(split.days)) as SplitDay[],
    });
    open.value = true;
    error.value = success.value = '';
}
function move(index: number, offset: number) {
    const target = index + offset;
    if (target < 0 || target >= form.days.length) return;
    [form.days[index], form.days[target]] = [
        form.days[target],
        form.days[index],
    ];
}
function addExercise(day: SplitDay) {
    if (day.exercises.length < 10)
        day.exercises.push({
            exercise_id: '',
            notes: '',
            sets: [{ reps: 8, weight: null, rir: 2 }],
        });
}
async function save() {
    saving.value = true;
    error.value = success.value = '';
    const clientId = props.clientId;
    try {
        await api(
            `/api/clients/${clientId}/training-splits${editId.value ? '/' + editId.value : ''}`,
            editId.value ? 'PUT' : 'POST',
            {
                ...form,
                notes: form.notes || null,
                days: form.days.map((day) => ({
                    ...day,
                    name: day.is_rest_day ? 'Rest day' : day.name,
                    notes: day.notes || null,
                    exercises: day.is_rest_day
                        ? []
                        : day.exercises.map((exercise) => ({
                              ...exercise,
                              notes: exercise.notes || null,
                              sets: exercise.sets.map((set) =>
                                  Object.fromEntries(
                                      Object.entries(set).map(
                                          ([key, value]) => [
                                              key,
                                              value === '' ? null : value,
                                          ],
                                      ),
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
                'Training split saved. It repeats from the start date until the next split begins. Logged workouts are preserved.';
            emit('changed');
        }
    } catch (cause) {
        if (clientId === props.clientId) error.value = message(cause);
    } finally {
        saving.value = false;
    }
}
function status(split: TrainingSplit) {
    const start = split.start_date.slice(0, 10);
    if (start > localDate()) return 'Upcoming';
    return splits.value.some(
        (other) =>
            other.start_date.slice(0, 10) <= localDate() &&
            other.start_date > split.start_date,
    )
        ? 'Previous split'
        : 'Current split';
}
watch(
    () => props.clientId,
    () => {
        open.value = false;
        splits.value = [];
        success.value = '';
        void load();
    },
    { immediate: true },
);
</script>

<template>
    <section class="mesocycle-section" aria-label="Training split programs">
        <div class="section-bar">
            <div>
                <h2>Training splits</h2>
                <p class="form-help">
                    A repeating sequence of 1–15 days, including rest days.
                </p>
            </div>
            <button
                v-if="canEdit"
                class="button button-small"
                :disabled="saving"
                @click="create"
            >
                + New training split
            </button>
        </div>
        <p v-if="error" class="notice" role="alert">{{ error }}</p>
        <p v-if="success" class="notice" role="status">{{ success }}</p>
        <p v-if="loading" class="loading-message" role="status">
            Loading training splits…
        </p>
        <form v-if="open && canEdit" class="panel" @submit.prevent="save">
            <div class="section-bar">
                <h2>
                    {{
                        editId
                            ? 'Edit training split'
                            : 'Build a training split'
                    }}
                </h2>
                <button
                    type="button"
                    class="text-link"
                    :disabled="saving"
                    @click="open = false"
                >
                    Cancel
                </button>
            </div>
            <fieldset :disabled="saving" class="split-fields">
                <div class="form-grid">
                    <label
                        >Program name<input
                            v-model="form.name"
                            required
                            maxlength="150"
                            placeholder="e.g. Push / Pull / Legs rotation"
                    /></label>
                    <label
                        >Start date<input
                            v-model="form.start_date"
                            type="date"
                            required
                    /></label>
                    <label class="full-width"
                        >Program notes<textarea
                            v-model="form.notes"
                            rows="2"
                            maxlength="5000"
                        />
                    </label>
                </div>
                <p class="form-help">
                    Write a separate workout for every training day. Rest days
                    also count toward the split length. Day 1 begins on the
                    start date and the sequence repeats automatically.
                </p>
                <article
                    v-for="(day, index) in form.days"
                    :key="index"
                    class="template-editor"
                >
                    <div class="section-bar">
                        <h3>
                            Day {{ index + 1 }} ·
                            {{ day.is_rest_day ? 'Rest' : 'Training' }}
                        </h3>
                        <div class="row-actions">
                            <button
                                type="button"
                                class="text-link"
                                :disabled="index === 0"
                                :aria-label="`Move day ${index + 1} earlier`"
                                @click="move(index, -1)"
                            >
                                ↑
                            </button>
                            <button
                                type="button"
                                class="text-link"
                                :disabled="index === form.days.length - 1"
                                :aria-label="`Move day ${index + 1} later`"
                                @click="move(index, 1)"
                            >
                                ↓
                            </button>
                            <button
                                type="button"
                                class="text-link"
                                :disabled="form.days.length <= 1"
                                @click="form.days.splice(index, 1)"
                            >
                                Remove day
                            </button>
                        </div>
                    </div>
                    <label
                        >Day type<DropdownSelect v-model="day.is_rest_day">
                            <option :value="false">Training day</option>
                            <option :value="true">Rest day</option>
                        </DropdownSelect></label
                    >
                    <label v-if="!day.is_rest_day"
                        >Workout name<input
                            v-model="day.name"
                            required
                            maxlength="150"
                            placeholder="e.g. Pull A"
                    /></label>
                    <label class="notes-label"
                        >Day notes<textarea
                            v-model="day.notes"
                            rows="2"
                            maxlength="5000"
                        />
                    </label>
                    <template v-if="!day.is_rest_day">
                        <div
                            v-for="(exercise, exerciseIndex) in day.exercises"
                            :key="exerciseIndex"
                            class="exercise-editor"
                        >
                            <div class="section-bar">
                                <h4>Exercise {{ exerciseIndex + 1 }}</h4>
                                <button
                                    type="button"
                                    class="text-link"
                                    @click="
                                        day.exercises.splice(exerciseIndex, 1)
                                    "
                                >
                                    Remove exercise
                                </button>
                            </div>
                            <label
                                >Exercise<DropdownSelect
                                    v-model="exercise.exercise_id"
                                    required
                                >
                                    <option value="" disabled>
                                        Select exercise
                                    </option>
                                    <option
                                        v-for="option in exercises"
                                        :key="option.id"
                                        :value="option.id"
                                    >
                                        {{ option.exercise_name }}
                                    </option>
                                </DropdownSelect></label
                            >
                            <label
                                >Technique / injury notes<input
                                    v-model="exercise.notes"
                                    maxlength="5000"
                            /></label>
                            <div
                                v-for="(set, setIndex) in exercise.sets"
                                :key="setIndex"
                                class="plan-set-row"
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
                                        v-model.number="set.weight"
                                        type="number"
                                        min="0"
                                        max="99999.99"
                                        step="0.01"
                                /></label>
                                <label
                                    >RIR<input
                                        v-model.number="set.rir"
                                        type="number"
                                        min="0"
                                        max="99.9"
                                        step="0.1"
                                /></label>
                                <button
                                    type="button"
                                    class="text-link"
                                    :disabled="exercise.sets.length <= 1"
                                    @click="exercise.sets.splice(setIndex, 1)"
                                >
                                    Remove
                                </button>
                            </div>
                            <button
                                type="button"
                                class="text-link"
                                :disabled="exercise.sets.length >= 100"
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
                                type="button"
                                class="button button-small button-secondary"
                                :disabled="
                                    day.exercises.length >= 10 ||
                                    !exercises.length
                                "
                                @click="addExercise(day)"
                            >
                                + Add exercise</button
                            ><span class="form-help"
                                >{{ day.exercises.length }} / 10 exercises</span
                            >
                        </div>
                    </template>
                </article>
                <div class="form-actions">
                    <button
                        type="button"
                        class="button button-secondary"
                        :disabled="form.days.length >= 15"
                        @click="addDay()"
                    >
                        + Training day
                    </button>
                    <button
                        type="button"
                        class="button button-secondary"
                        :disabled="form.days.length >= 15"
                        @click="addDay(true)"
                    >
                        + Rest day
                    </button>
                    <span class="form-help"
                        >{{ form.days.length }} / 15 days</span
                    >
                    <button
                        class="button"
                        :disabled="
                            saving ||
                            !form.days.length ||
                            form.days.some(
                                (day) =>
                                    !day.is_rest_day && !day.exercises.length,
                            )
                        "
                    >
                        {{
                            saving
                                ? 'Saving…'
                                : editId
                                  ? 'Update split'
                                  : 'Assign split'
                        }}
                        ↗
                    </button>
                </div>
                <p class="form-help">
                    A newer split takes over on its own start date. Workout
                    edits apply to unlogged sessions from today onward. Create a
                    new split to change the length or start date once this split
                    has recorded history.
                </p>
            </fieldset>
        </form>
        <p v-if="!loading && !splits.length && !open" class="empty-state">
            {{
                canEdit
                    ? 'Build a split and choose its start date to give this client a repeating program.'
                    : 'Your coach’s training split will appear here.'
            }}
        </p>
        <article v-for="split in splits" :key="split.id" class="panel">
            <div class="section-bar">
                <div>
                    <p class="eyebrow">
                        {{ status(split) }} · starts
                        {{ displayDate(split.start_date) }}
                    </p>
                    <h3>{{ split.name }}</h3>
                    <p class="form-help">
                        Repeats every {{ split.days.length }}
                        {{ split.days.length === 1 ? 'day' : 'days' }}
                    </p>
                </div>
                <button
                    v-if="canEdit"
                    class="button button-small button-secondary"
                    :disabled="saving"
                    @click="edit(split)"
                >
                    Edit split ↗
                </button>
            </div>
            <p v-if="split.notes" class="record-notes">{{ split.notes }}</p>
            <div
                v-for="(day, index) in split.days"
                :key="index"
                class="mesocycle-workout"
            >
                <strong
                    >Day {{ index + 1 }} /
                    {{ day.is_rest_day ? 'Rest day' : day.name }}</strong
                ><span v-if="!day.is_rest_day"
                    >{{ day.exercises.length }} exercises</span
                >
                <p v-if="day.notes" class="record-notes">{{ day.notes }}</p>
                <p
                    v-for="(exercise, exerciseIndex) in day.exercises"
                    v-show="!day.is_rest_day"
                    :key="exerciseIndex"
                    class="form-help"
                >
                    {{ exerciseIndex + 1 }}.
                    {{
                        exercises.find(
                            (option) => option.id === exercise.exercise_id,
                        )?.exercise_name ?? 'Exercise'
                    }}
                    · {{ exercise.sets.length }} working sets<span
                        v-if="exercise.notes"
                    >
                        · {{ exercise.notes }}</span
                    >
                </p>
            </div>
        </article>
    </section>
</template>

<style scoped>
.split-fields {
    display: grid;
    gap: 24px;
    border: 0;
    padding: 0;
    margin: 0;
    min-width: 0;
}
.split-fields > .notes-label,
.split-fields > .form-actions {
    margin: 0;
}
.split-fields > .template-editor {
    margin-top: 8px;
}
</style>
