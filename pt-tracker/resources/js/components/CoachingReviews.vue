<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { api, displayDate, localDate, message } from '@/lib/tracker-api';
import type { Page } from '@/lib/tracker-api';
const props = defineProps<{
    clientId: number;
    coachId: number | null;
    compact?: boolean;
}>();
const emit = defineEmits<{ open: [] }>();
type Schedule = {
    coach_id: number;
    coach_name: string;
    phase: 'prep' | 'offseason';
    interval_days: number;
    due_on: string;
    is_due: boolean;
};
type Review = {
    id: number;
    coach_id: number | null;
    coach: { name: string } | null;
    phase: 'prep' | 'offseason';
    review_date: string;
    feedback: string;
    next_steps: string | null;
};
type Response = {
    schedules: Schedule[];
    summary: {
        from: string;
        to: string;
        check_ins: number;
        weigh_ins: number;
        average_bodyweight: number | null;
        average_steps: number | null;
        workouts_planned: number;
        workouts_completed: number;
        nutrition: {
            hunger_level: { average: number | null; readings: number };
            appetite_level: { average: number | null; readings: number };
            digestive_discomfort: { average: number | null; readings: number };
            reports: {
                date: string;
                digestion_notes: string | null;
                dietary_difficulties: string | null;
            }[];
        };
    };
    reviews: Page<Review>;
};
const state = ref<Response | null>(null);
const nutritionMetrics = [
    {
        key: 'hunger_level' as const,
        label: 'Hunger',
        scale: '1 = not hungry; 5 = very hungry',
    },
    {
        key: 'appetite_level' as const,
        label: 'Appetite',
        scale: '1 = very low; 5 = very high',
    },
    {
        key: 'digestive_discomfort' as const,
        label: 'Digestive discomfort',
        scale: '1 = none; 5 = severe',
    },
];
const ownSchedule = computed(() =>
    state.value?.schedules.find(
        (schedule) => schedule.coach_id === props.coachId,
    ),
);
const visibleSchedules = computed(() =>
    props.coachId
        ? (state.value?.schedules.filter(
              (schedule) => schedule.coach_id === props.coachId,
          ) ?? [])
        : (state.value?.schedules ?? []),
);
const phase = ref<'prep' | 'offseason'>('offseason');
const form = reactive({
    review_date: localDate(),
    feedback: '',
    next_steps: '',
});
const loading = ref(true),
    busy = ref(false),
    error = ref(''),
    success = ref(''),
    page = ref(1);
const base = '/api/clients/' + props.clientId + '/coaching-reviews';
async function load() {
    loading.value = true;
    error.value = '';
    try {
        state.value = await api<Response>(base + '?page=' + page.value);
        phase.value = ownSchedule.value?.phase ?? 'offseason';
    } catch (cause) {
        state.value = null;
        error.value = message(cause);
    } finally {
        loading.value = false;
    }
}
async function setSchedule() {
    busy.value = true;
    error.value = '';
    success.value = '';
    try {
        state.value = await api<Response>(base + '/schedule', 'PATCH', {
            phase: phase.value,
        });
        page.value = 1;
        success.value =
            'Review schedule saved. Prep reviews are weekly; off-season reviews are fortnightly.';
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
async function publish() {
    busy.value = true;
    error.value = '';
    success.value = '';
    try {
        await api(base, 'POST', form);
        form.feedback = '';
        form.next_steps = '';
        form.review_date = localDate();
        page.value = 1;
        await load();
        success.value = 'Written check-in review published to the client.';
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
async function paginate(next: number) {
    page.value = next;
    await load();
}
onMounted(load);
</script>
<template>
    <section aria-label="Coach check-in reviews">
        <p v-if="error" class="notice" role="alert">
            {{ error }}
            <button class="text-link" :disabled="busy || loading" @click="load">
                Retry
            </button>
        </p>
        <p v-if="loading" class="loading-message" role="status">
            Loading coach reviews…
        </p>
        <template v-else-if="state">
            <div v-if="compact && visibleSchedules.length" class="panel">
                <div class="section-bar">
                    <h2>Coach check-in reviews</h2>
                    <button class="text-link" @click="emit('open')">
                        {{
                            coachId
                                ? 'Review client check-in'
                                : 'Read coach feedback'
                        }}
                    </button>
                </div>
                <p
                    v-for="schedule in visibleSchedules"
                    :key="schedule.coach_id"
                    class="form-help"
                >
                    {{ schedule.coach_name }} ·
                    {{
                        schedule.phase === 'prep'
                            ? 'Weekly prep review'
                            : 'Fortnightly off-season review'
                    }}
                    · {{ schedule.is_due ? 'Due since' : 'Next due' }}
                    {{ displayDate(schedule.due_on) }}
                </p>
            </div>
            <template v-else-if="!compact">
                <p v-if="success" class="notice" role="status">{{ success }}</p>
                <div class="panel">
                    <h2>Written coach reviews</h2>
                    <p class="form-help">
                        {{
                            coachId
                                ? 'Review the client weekly during prep and every fourteen days in the off-season. Daily check-ins and posing feedback remain separate.'
                                : 'Weekly during prep. Every fourteen days in the off-season. Your daily check-ins and posing feedback remain separate.'
                        }}
                    </p>
                    <p
                        v-for="schedule in visibleSchedules"
                        :key="schedule.coach_id"
                        class="notice"
                    >
                        {{ schedule.coach_name }} ·
                        {{
                            schedule.phase === 'prep'
                                ? 'Prep · every 7 days'
                                : 'Off-season · every 14 days'
                        }}
                        ·
                        {{
                            schedule.is_due
                                ? 'Review due since'
                                : 'Next review due'
                        }}
                        {{ displayDate(schedule.due_on) }}
                    </p>
                    <p v-if="!visibleSchedules.length" class="form-help">
                        No active coaching review schedule. Previous reviews are
                        still available below.
                    </p>
                    <form v-if="coachId" @submit.prevent="setSchedule">
                        <fieldset :disabled="busy" class="coaching-fields">
                            <label
                                >Current coaching phase<select
                                    v-model="phase"
                                    aria-label="Current coaching phase"
                                >
                                    <option value="prep">
                                        Competition prep — weekly reviews
                                    </option>
                                    <option value="offseason">
                                        Off-season — fortnightly reviews
                                    </option>
                                </select></label
                            >
                            <p class="form-help">
                                Changing phase starts the new review interval
                                today and preserves previous reviews.
                            </p>
                            <button class="button button-small">
                                Save review schedule
                            </button>
                        </fieldset>
                    </form>
                </div>
                <div class="panel">
                    <h2>Recent check-in summary</h2>
                    <p class="form-help">
                        {{ displayDate(state.summary.from) }} –
                        {{ displayDate(state.summary.to) }}
                    </p>
                    <div class="practice-grid">
                        <article>
                            <h3>Bodyweight</h3>
                            <p>
                                {{
                                    state.summary.average_bodyweight === null
                                        ? 'No recorded weights'
                                        : Number(
                                              state.summary.average_bodyweight,
                                          ).toFixed(2) + ' kg average'
                                }}
                            </p>
                            <p class="form-help">
                                {{ state.summary.weigh_ins }} weigh-ins
                            </p>
                        </article>
                        <article>
                            <h3>Training</h3>
                            <p>
                                {{ state.summary.workouts_completed }} /
                                {{ state.summary.workouts_planned }} sessions
                                completed
                            </p>
                        </article>
                        <article>
                            <h3>Daily activity</h3>
                            <p>
                                {{
                                    state.summary.average_steps === null
                                        ? 'No recorded steps'
                                        : Math.round(
                                              state.summary.average_steps,
                                          ).toLocaleString() + ' average steps'
                                }}
                            </p>
                            <p class="form-help">
                                {{ state.summary.check_ins }} daily check-ins
                            </p>
                        </article>
                    </div>
                </div>
                <div class="panel">
                    <h2>Nutrition check-in summary</h2>
                    <p class="form-help">
                        For the same review window above. Averages use recorded
                        responses only.
                    </p>
                    <div class="practice-grid">
                        <article
                            v-for="metric in nutritionMetrics"
                            :key="metric.key"
                        >
                            <h3>{{ metric.label }}</h3>
                            <p>
                                {{
                                    state.summary.nutrition[metric.key]
                                        .average === null
                                        ? 'Not recorded'
                                        : Number(
                                              state.summary.nutrition[
                                                  metric.key
                                              ].average,
                                          ).toFixed(1) + ' / 5 average'
                                }}
                            </p>
                            <p class="form-help">
                                {{
                                    state.summary.nutrition[metric.key].readings
                                }}
                                responses · {{ metric.scale }}
                            </p>
                        </article>
                    </div>
                    <details v-if="state.summary.nutrition.reports.length" open>
                        <summary>
                            Digestion notes and dietary difficulties
                        </summary>
                        <article
                            v-for="report in state.summary.nutrition.reports"
                            :key="report.date"
                            class="meal-prescription"
                        >
                            <h3>{{ displayDate(report.date) }}</h3>
                            <p
                                v-if="report.digestion_notes"
                                class="record-notes"
                            >
                                Digestion: {{ report.digestion_notes }}
                            </p>
                            <p
                                v-if="report.dietary_difficulties"
                                class="record-notes"
                            >
                                Dietary difficulties:
                                {{ report.dietary_difficulties }}
                            </p>
                        </article>
                    </details>
                    <p v-else class="form-help">
                        No digestion notes or dietary difficulties reported in
                        this review window.
                    </p>
                </div>
                <form
                    v-if="coachId && ownSchedule"
                    class="panel"
                    @submit.prevent="publish"
                >
                    <h2>
                        Write
                        {{
                            ownSchedule.phase === 'prep' ? 'prep' : 'off-season'
                        }}
                        check-in review
                    </h2>
                    <fieldset :disabled="busy" class="coaching-fields">
                        <label
                            >Review date<input
                                v-model="form.review_date"
                                type="date"
                                :max="localDate()"
                                required /></label
                        ><label class="notes-label"
                            >Review and feedback<textarea
                                v-model="form.feedback"
                                rows="6"
                                maxlength="10000"
                                placeholder="Review progress, adherence, recovery and physique changes."
                                required
                            /></label
                        ><label class="notes-label"
                            >Next steps<textarea
                                v-model="form.next_steps"
                                rows="3"
                                maxlength="5000"
                                placeholder="What to continue or change before the next review."
                            />
                        </label>
                        <p class="form-help">
                            Publishing shares this review with the client.
                            Publish meal or activity changes in their respective
                            plan sections.
                        </p>
                        <button class="button">
                            {{
                                busy ? 'Publishing…' : 'Publish written review'
                            }}
                        </button>
                    </fieldset>
                </form>
                <div class="section-bar">
                    <h2>Review history</h2>
                    <span class="eyebrow"
                        >{{ state.reviews.total }} REVIEWS</span
                    >
                </div>
                <p v-if="!state.reviews.data.length" class="empty-state">
                    No written check-in reviews yet.
                </p>
                <article
                    v-for="review in state.reviews.data"
                    :key="review.id"
                    class="panel"
                >
                    <div class="section-bar">
                        <h3>{{ displayDate(review.review_date) }}</h3>
                        <span class="status-pill">{{
                            review.phase === 'prep' ? 'Prep' : 'Off-season'
                        }}</span>
                    </div>
                    <p class="form-help">
                        Written by {{ review.coach?.name ?? 'Former coach' }}
                    </p>
                    <p class="record-notes">{{ review.feedback }}</p>
                    <template v-if="review.next_steps"
                        ><h3>Next steps</h3>
                        <p class="record-notes">
                            {{ review.next_steps }}
                        </p></template
                    >
                </article>
                <div v-if="state.reviews.last_page > 1" class="pagination">
                    <button
                        :disabled="busy || loading || page <= 1"
                        @click="paginate(page - 1)"
                    >
                        Previous</button
                    ><span>{{ page }} / {{ state.reviews.last_page }}</span
                    ><button
                        :disabled="
                            busy || loading || page >= state.reviews.last_page
                        "
                        @click="paginate(page + 1)"
                    >
                        Next
                    </button>
                </div>
            </template>
        </template>
    </section>
</template>
