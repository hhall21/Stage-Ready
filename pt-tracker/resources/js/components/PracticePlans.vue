<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import PlanVersions from '@/components/PlanVersions.vue';
import { defaultDays, weekdays } from '@/lib/coaching';
import type { PlansResponse, PracticePlan } from '@/lib/coaching';
import { api, displayDate, localDate, message } from '@/lib/tracker-api';
const props = defineProps<{ clientId: number; coachId: number | null }>();
const state = ref<PlansResponse<PracticePlan> | null>(null);
const loading = ref(true),
    busy = ref(false),
    error = ref(''),
    success = ref(''),
    open = ref(false);
const date = ref(localDate()),
    page = ref(1),
    selectedWeekday = ref(1);
const preview = ref<PracticePlan | null>(null);
const plan = computed(() => preview.value ?? state.value?.current);
const day = computed(() =>
    plan.value?.days.find((item) => item.weekday === selectedWeekday.value),
);
const form = reactive({
    effective_from: localDate(),
    show_date: '',
    days: defaultDays(),
    notes: '',
});
const base = '/api/clients/' + props.clientId + '/practice-plans';
const weeksOut = computed(() => {
    if (!plan.value?.show_date) return null;
    const difference =
        new Date(plan.value.show_date.slice(0, 10) + 'T12:00:00Z').getTime() -
        new Date(date.value + 'T12:00:00Z').getTime();
    return Math.ceil(difference / (7 * 86400000));
});
async function load() {
    loading.value = true;
    error.value = '';
    preview.value = null;
    try {
        state.value = await api<PlansResponse<PracticePlan>>(
            base + '?date=' + date.value + '&page=' + page.value,
        );
        selectedWeekday.value = state.value.weekday;
    } catch (cause) {
        state.value = null;
        error.value = message(cause);
    } finally {
        loading.value = false;
    }
}
function start(source: PracticePlan | null = state.value?.current ?? null) {
    form.effective_from = date.value < localDate() ? localDate() : date.value;
    form.show_date = source?.show_date?.slice(0, 10) ?? '';
    form.days = source
        ? source.days.map((item) => ({ ...item }))
        : defaultDays();
    form.notes = source?.notes ?? '';
    open.value = true;
}
function copyMonday() {
    const monday = form.days.find((item) => item.weekday === 1);
    if (monday)
        form.days = weekdays.map((_, index) => ({
            ...monday,
            weekday: index + 1,
        }));
}
async function save() {
    busy.value = true;
    error.value = '';
    success.value = '';
    try {
        await api(base, 'POST', { ...form, show_date: form.show_date || null });
        date.value = form.effective_from;
        page.value = 1;
        open.value = false;
        await load();
        success.value =
            'Daily cardio, posing and vacuum prescriptions published.';
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
        success.value = 'Scheduled practice plan cancelled.';
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
onMounted(load);
</script>
<template>
    <section aria-label="Daily cardio and practice prescriptions">
        <p v-if="error" class="notice" role="alert">
            {{ error }}
            <button class="text-link" :disabled="busy || loading" @click="load">
                Retry
            </button>
        </p>
        <p v-if="success" class="notice" role="status">{{ success }}</p>
        <div class="panel">
            <div class="section-bar">
                <h2>Daily cardio, posing and vacuums</h2>
                <button
                    v-if="coachId"
                    class="button button-small"
                    :disabled="busy || loading"
                    @click="start()"
                >
                    Create prescription version
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
                Your coach sets each day's work. Scheduled versions can increase
                posing rounds as show day approaches.
            </p>
        </div>
        <p v-if="loading" role="status" class="loading-message">
            Loading daily prescriptions…
        </p>
        <template v-else-if="state">
            <div v-if="plan" class="panel">
                <div class="section-bar">
                    <h2>
                        Prescription from {{ displayDate(plan.effective_from) }}
                    </h2>
                    <span
                        v-if="weeksOut !== null && weeksOut >= 0"
                        class="eyebrow"
                        >{{ weeksOut }} WEEKS OUT</span
                    >
                </div>
                <p v-if="plan.show_date" class="form-help">
                    Show date: {{ displayDate(plan.show_date) }}
                </p>
                <p v-if="preview" class="notice">
                    Viewing version {{ preview.id }}.
                    <button class="text-link" @click="preview = null">
                        Back to selected date
                    </button>
                </p>
                <label
                    >Day of week<select
                        v-model.number="selectedWeekday"
                        aria-label="Day of week"
                    >
                        <option
                            v-for="(name, index) in weekdays"
                            :key="name"
                            :value="index + 1"
                        >
                            {{ name }}
                        </option>
                    </select></label
                >
                <div v-if="day" class="practice-grid">
                    <article>
                        <h3>Cardio</h3>
                        <p>
                            {{
                                day.cardio_minutes
                                    ? day.cardio_minutes +
                                      ' minutes · ' +
                                      day.cardio_mode
                                    : 'No cardio prescribed'
                            }}
                        </p>
                        <p
                            v-if="day.cardio_minutes && day.cardio_intensity"
                            class="form-help"
                        >
                            {{ day.cardio_intensity }}
                        </p>
                    </article>
                    <article>
                        <h3>Mandatory posing</h3>
                        <p>
                            {{
                                day.posing_rounds
                                    ? day.posing_rounds +
                                      ' rounds · ' +
                                      day.pose_hold_seconds +
                                      ' seconds per pose'
                                    : 'No posing rounds prescribed'
                            }}
                        </p>
                        <p v-if="day.posing_rounds" class="form-help">
                            One round means one complete run through your
                            mandatory poses.
                        </p>
                    </article>
                    <article>
                        <h3>Vacuum practice</h3>
                        <p>
                            {{
                                day.vacuum_sets
                                    ? day.vacuum_sets +
                                      ' sets · ' +
                                      day.vacuum_hold_seconds +
                                      ' seconds per hold'
                                    : 'No vacuum practice prescribed'
                            }}
                        </p>
                    </article>
                </div>
                <p v-if="day?.notes" class="record-notes">{{ day.notes }}</p>
                <p v-if="plan.notes" class="record-notes">{{ plan.notes }}</p>
            </div>
            <p v-else class="empty-state">
                No cardio or practice plan has been prescribed for this date.
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
            <h2>Prescribe the week</h2>
            <p class="form-help">
                Set zero for an activity on days it is not prescribed. To
                increase rounds nearer a show, publish another version with a
                later start date.
            </p>
            <fieldset :disabled="busy" class="coaching-fields">
                <div class="form-grid">
                    <label
                        >Starts on<input
                            v-model="form.effective_from"
                            type="date"
                            :min="localDate()"
                            required /></label
                    ><label
                        >Show date<input
                            v-model="form.show_date"
                            type="date"
                            :min="form.effective_from"
                    /></label>
                </div>
                <button type="button" class="text-link" @click="copyMonday">
                    Copy Monday's prescription to every day
                </button>
                <details
                    v-for="item in form.days"
                    :key="item.weekday"
                    class="practice-editor"
                    :open="item.weekday === 1"
                >
                    <summary>
                        {{ weekdays[item.weekday - 1] }} ·
                        {{ item.cardio_minutes }} min cardio ·
                        {{ item.posing_rounds }} posing rounds ·
                        {{ item.vacuum_sets }} vacuum sets
                    </summary>
                    <div class="form-grid">
                        <label
                            >Cardio type<input
                                v-model="item.cardio_mode"
                                maxlength="150"
                                required
                        /></label>
                        <label
                            >Cardio duration (minutes)<input
                                v-model.number="item.cardio_minutes"
                                type="number"
                                min="0"
                                max="300"
                                step="1"
                                required
                        /></label>
                        <label
                            >Cardio intensity / target<input
                                v-model="item.cardio_intensity"
                                maxlength="250"
                                placeholder="Coach's prescribed effort, pace or heart-rate target"
                        /></label>
                        <label
                            >Mandatory posing rounds<input
                                v-model.number="item.posing_rounds"
                                type="number"
                                min="0"
                                max="100"
                                step="1"
                                required
                        /></label>
                        <label
                            >Hold each pose (seconds)<input
                                v-model.number="item.pose_hold_seconds"
                                type="number"
                                min="1"
                                max="300"
                                step="1"
                                required
                        /></label>
                        <label
                            >Vacuum sets<input
                                v-model.number="item.vacuum_sets"
                                type="number"
                                min="0"
                                max="100"
                                step="1"
                                required
                        /></label>
                        <label
                            >Vacuum hold (seconds)<input
                                v-model.number="item.vacuum_hold_seconds"
                                type="number"
                                min="1"
                                max="300"
                                step="1"
                                required
                        /></label>
                    </div>
                    <label class="notes-label"
                        >Day notes<textarea
                            v-model="item.notes"
                            rows="2"
                            maxlength="2000"
                        />
                    </label>
                </details>
                <label class="notes-label"
                    >Plan notes<textarea
                        v-model="form.notes"
                        rows="2"
                        maxlength="5000"
                    />
                </label>
                <div class="form-actions">
                    <button class="button">
                        {{
                            busy ? 'Publishing…' : 'Publish daily prescriptions'
                        }}</button
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
