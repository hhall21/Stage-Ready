<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import PlanVersions from '@/components/PlanVersions.vue';
import { defaultMeals } from '@/lib/coaching';
import type {
    MealPlanEntry,
    NutritionPlan,
    PlansResponse,
} from '@/lib/coaching';
import { api, displayDate, localDate, message } from '@/lib/tracker-api';
const props = defineProps<{ clientId: number; coachId: number | null }>();
const state = ref<PlansResponse<NutritionPlan> | null>(null);
const loading = ref(true),
    busy = ref(false),
    error = ref(''),
    success = ref(''),
    open = ref(false);
const date = ref(localDate()),
    dayType = ref<'training' | 'rest'>('training'),
    page = ref(1);
const preview = ref<NutritionPlan | null>(null);
const plan = computed(() => preview.value ?? state.value?.current);
const meals = computed(
    () =>
        (dayType.value === 'training'
            ? plan.value?.training_meals
            : plan.value?.rest_meals) ?? [],
);
const delay = ref(60);
const form = reactive({
    effective_from: localDate(),
    training_meals: defaultMeals(),
    rest_meals: defaultMeals(true),
    notes: '',
});
const macroFields = [
    { key: 'calories' as const, label: 'Calories (kcal)' },
    { key: 'protein' as const, label: 'Protein (g)' },
    { key: 'carbohydrates' as const, label: 'Carbohydrates (g)' },
    { key: 'fat' as const, label: 'Fat (g)' },
];
const base = '/api/clients/' + props.clientId + '/nutrition-plans';
async function load() {
    loading.value = true;
    error.value = '';
    preview.value = null;
    try {
        state.value = await api<PlansResponse<NutritionPlan>>(
            base + '?date=' + date.value + '&page=' + page.value,
        );
        dayType.value = state.value.day_type;
    } catch (cause) {
        state.value = null;
        error.value = message(cause);
    } finally {
        loading.value = false;
    }
}
function start(source: NutritionPlan | null = state.value?.current ?? null) {
    form.effective_from = date.value < localDate() ? localDate() : date.value;
    form.training_meals = source
        ? source.training_meals.map((meal) => ({ ...meal }))
        : defaultMeals();
    form.rest_meals = source
        ? source.rest_meals.map((meal) => ({ ...meal }))
        : defaultMeals(true);
    form.notes = source?.notes ?? '';
    open.value = true;
}
function copyRest() {
    if (
        !Number.isInteger(Number(delay.value)) ||
        Number(delay.value) < 0 ||
        Number(delay.value) > 240 ||
        form.training_meals.some(
            (meal) => !/^([01]\d|2[0-3]):[0-5]\d$/.test(meal.time),
        )
    ) {
        error.value =
            'Enter valid training meal times and a rest-day delay from 0 to 240 whole minutes before copying.';
        return;
    }
    form.rest_meals = form.training_meals.map((meal) => {
        const [hour, minute] = meal.time.split(':').map(Number);
        const total = Math.min(
            1439,
            Math.max(0, hour * 60 + minute + Number(delay.value)),
        );
        return {
            ...meal,
            time:
                String(Math.floor(total / 60)).padStart(2, '0') +
                ':' +
                String(total % 60).padStart(2, '0'),
        };
    });
}
function addMeal(target: MealPlanEntry[]) {
    target.push({
        ...defaultMeals()[0],
        name: 'Meal ' + (target.length + 1),
        time: '',
    });
}
async function save() {
    busy.value = true;
    error.value = '';
    success.value = '';
    try {
        await api(base, 'POST', form);
        date.value = form.effective_from;
        page.value = 1;
        open.value = false;
        await load();
        success.value =
            'Meal plan published. Both day types and their meal times are saved.';
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
async function cancel(id: number) {
    busy.value = true;
    error.value = '';
    success.value = '';
    try {
        await api(base + '/' + id, 'DELETE');
        await load();
        success.value = 'Scheduled meal plan cancelled.';
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
function useVersion(id: number) {
    const version = state.value?.versions.data.find((item) => item.id === id);
    if (!version) return;
    if (props.coachId) start(version);
    else preview.value = version;
}
async function paginate(next: number) {
    page.value = next;
    await load();
}
function total(key: 'calories' | 'protein' | 'carbohydrates' | 'fat') {
    if (meals.value.some((meal) => meal[key] == null || meal[key] === ''))
        return 'Not fully specified';
    return meals.value
        .reduce((sum, meal) => sum + Number(meal[key]), 0)
        .toFixed(1);
}
onMounted(load);
</script>
<template>
    <section aria-label="Meal plans">
        <p v-if="error" class="notice" role="alert">
            {{ error }}
            <button class="text-link" :disabled="busy || loading" @click="load">
                Retry
            </button>
        </p>
        <p v-if="success" class="notice" role="status">{{ success }}</p>
        <div class="panel">
            <div class="section-bar">
                <h2>Training and rest-day meal plans</h2>
                <button
                    v-if="coachId"
                    class="button button-small"
                    :disabled="busy || loading"
                    @click="start()"
                >
                    Create meal plan version
                </button>
            </div>
            <label
                >Plan for date<input
                    v-model="date"
                    type="date"
                    :disabled="busy || loading"
                    @change="load"
            /></label>
            <p class="form-help">
                The scheduled workout determines the suggested day type. You can
                view either meal plan below.
            </p>
            <div class="row-actions">
                <button
                    class="text-link"
                    :aria-pressed="dayType === 'training'"
                    @click="dayType = 'training'"
                >
                    Training day</button
                ><button
                    class="text-link"
                    :aria-pressed="dayType === 'rest'"
                    @click="dayType = 'rest'"
                >
                    Rest day
                </button>
            </div>
        </div>
        <p v-if="loading" role="status" class="loading-message">
            Loading meal plans…
        </p>
        <template v-else-if="state">
            <div v-if="plan" class="panel">
                <div class="section-bar">
                    <h2>
                        {{
                            dayType === 'training' ? 'Training day' : 'Rest day'
                        }}
                    </h2>
                    <span class="eyebrow"
                        >FROM {{ displayDate(plan.effective_from) }}</span
                    >
                </div>
                <p v-if="preview" class="notice">
                    Viewing version {{ preview.id }}.
                    <button class="text-link" @click="preview = null">
                        Back to selected date
                    </button>
                </p>
                <article
                    v-for="(meal, index) in meals"
                    :key="index"
                    class="meal-prescription"
                >
                    <h3>{{ meal.time }} · {{ meal.name }}</h3>
                    <p class="record-notes">{{ meal.foods }}</p>
                    <p class="form-help">
                        {{ meal.calories ?? '—' }} kcal · Protein
                        {{ meal.protein ?? '—' }} g · Carbs
                        {{ meal.carbohydrates ?? '—' }} g · Fat
                        {{ meal.fat ?? '—' }} g
                    </p>
                </article>
                <p class="form-help">
                    Daily totals: {{ total('calories') }} kcal · Protein
                    {{ total('protein') }} g · Carbs
                    {{ total('carbohydrates') }} g · Fat {{ total('fat') }} g
                </p>
                <p v-if="plan.notes" class="record-notes">{{ plan.notes }}</p>
            </div>
            <p v-else class="empty-state">
                No meal plan has been prescribed for this date.
            </p>
            <PlanVersions
                :versions="state.versions"
                :coach-id="coachId"
                :busy="busy || loading"
                @copy="useVersion"
                @cancel="cancel"
                @page="paginate"
            />
        </template>
        <form v-if="coachId && open" class="panel" @submit.prevent="save">
            <h2>Prescribe meals and timings</h2>
            <p class="form-help">
                Publishing creates a dated version. Previous versions remain in
                the history.
            </p>
            <fieldset :disabled="busy" class="coaching-fields">
                <label
                    >Starts on<input
                        v-model="form.effective_from"
                        type="date"
                        :min="localDate()"
                        required
                /></label>
                <template
                    v-for="kind in ['training', 'rest'] as const"
                    :key="kind"
                >
                    <h3>
                        {{
                            kind === 'training'
                                ? 'Training-day meals'
                                : 'Rest-day meals'
                        }}
                    </h3>
                    <div v-if="kind === 'rest'" class="form-actions">
                        <label
                            >Rest-day delay (minutes)<input
                                v-model.number="delay"
                                type="number"
                                min="0"
                                max="240"
                                step="1"
                        /></label>
                        <button
                            type="button"
                            class="text-link"
                            @click="copyRest"
                        >
                            Copy training meals with later times
                        </button>
                    </div>
                    <div
                        v-for="(meal, index) in kind === 'training'
                            ? form.training_meals
                            : form.rest_meals"
                        :key="index"
                        class="meal-prescription"
                    >
                        <div class="form-grid">
                            <label
                                >Meal name<input
                                    v-model="meal.name"
                                    maxlength="100"
                                    required /></label
                            ><label
                                >Meal time<input
                                    v-model="meal.time"
                                    type="time"
                                    required
                            /></label>
                        </div>
                        <label class="notes-label"
                            >Foods and portions<textarea
                                v-model="meal.foods"
                                rows="2"
                                maxlength="3000"
                                placeholder="Include food amounts and whether weights are raw or cooked."
                                required
                            />
                        </label>
                        <details>
                            <summary>Optional meal calories and macros</summary>
                            <div class="form-grid">
                                <label
                                    v-for="macro in macroFields"
                                    :key="macro.key"
                                    >{{ macro.label
                                    }}<input
                                        v-model="meal[macro.key]"
                                        type="number"
                                        min="0"
                                        max="10000"
                                        step="0.1"
                                /></label>
                            </div>
                        </details>
                        <button
                            type="button"
                            class="text-link"
                            :disabled="
                                (kind === 'training'
                                    ? form.training_meals
                                    : form.rest_meals
                                ).length <= 1
                            "
                            @click="
                                (kind === 'training'
                                    ? form.training_meals
                                    : form.rest_meals
                                ).splice(index, 1)
                            "
                        >
                            Remove meal
                        </button>
                    </div>
                    <button
                        type="button"
                        class="text-link"
                        :disabled="
                            (kind === 'training'
                                ? form.training_meals
                                : form.rest_meals
                            ).length >= 12
                        "
                        @click="
                            addMeal(
                                kind === 'training'
                                    ? form.training_meals
                                    : form.rest_meals,
                            )
                        "
                    >
                        Add meal
                    </button>
                </template>
                <label class="notes-label"
                    >Plan notes<textarea
                        v-model="form.notes"
                        rows="2"
                        maxlength="5000"
                    />
                </label>
                <div class="form-actions">
                    <button class="button">
                        {{ busy ? 'Publishing…' : 'Publish meal plan' }}</button
                    ><button
                        type="button"
                        class="text-link"
                        @click="open = false"
                    >
                        Cancel
                    </button>
                </div>
            </fieldset>
        </form>
    </section>
</template>
