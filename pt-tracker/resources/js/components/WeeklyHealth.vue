<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { api, displayDate, localDate, message } from '@/lib/tracker-api';
import type { Page } from '@/lib/tracker-api';

const props = defineProps<{
    clientId: number;
    isCoach: boolean;
    compact?: boolean;
}>();
const emit = defineEmits<{ open: [] }>();
type Reading = {
    id: number;
    reading_date: string;
    systolic: number | null;
    diastolic: number | null;
    resting_heart_rate: number | null;
    notes: string | null;
};
type Health = {
    monitoring_enabled: boolean;
    coach_enabled: boolean | null;
    due_on: string | null;
    is_due: boolean;
    readings: Page<Reading>;
};
const state = ref<Health | null>(null);
const loading = ref(true),
    busy = ref(false),
    error = ref(''),
    success = ref('');
const page = ref(1),
    editing = ref<number | null>(null);
const form = reactive({
    reading_date: localDate(),
    systolic: '',
    diastolic: '',
    resting_heart_rate: '',
    notes: '',
});
const base = '/api/clients/' + props.clientId + '/weekly-health';

async function load() {
    loading.value = true;
    error.value = '';
    try {
        state.value = await api<Health>(base + '?page=' + page.value);
    } catch (cause) {
        error.value = message(cause);
    } finally {
        loading.value = false;
    }
}
async function toggleMonitoring() {
    if (!state.value) return;
    busy.value = true;
    error.value = '';
    success.value = '';
    try {
        state.value = await api<Health>(base + '/settings', 'PATCH', {
            enabled: !state.value.coach_enabled,
        });
        page.value = 1;
        success.value = state.value.coach_enabled
            ? 'Weekly monitoring enabled. The client can now record their readings.'
            : 'Your monitoring requirement is off. Previous readings are preserved.';
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
function reset() {
    editing.value = null;
    Object.assign(form, {
        reading_date: localDate(),
        systolic: '',
        diastolic: '',
        resting_heart_rate: '',
        notes: '',
    });
}
function edit(reading: Reading) {
    editing.value = reading.id;
    Object.assign(form, {
        reading_date: reading.reading_date.slice(0, 10),
        systolic: reading.systolic === null ? '' : String(reading.systolic),
        diastolic: reading.diastolic === null ? '' : String(reading.diastolic),
        resting_heart_rate:
            reading.resting_heart_rate === null
                ? ''
                : String(reading.resting_heart_rate),
        notes: reading.notes ?? '',
    });
}
async function save() {
    busy.value = true;
    error.value = '';
    success.value = '';
    try {
        await api(
            base + '/readings' + (editing.value ? '/' + editing.value : ''),
            editing.value ? 'PUT' : 'POST',
            form,
        );
        reset();
        page.value = 1;
        await load();
        success.value = 'Health reading saved.';
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
    <section aria-label="Weekly health monitoring">
        <p v-if="error" class="notice" role="alert">
            {{ error }}
            <button class="text-link" :disabled="loading || busy" @click="load">
                Retry
            </button>
        </p>
        <p v-if="loading" class="loading-message" role="status">
            Loading health readings…
        </p>
        <template v-else-if="state">
            <div v-if="compact && state.monitoring_enabled" class="panel">
                <div class="section-bar">
                    <h2>Weekly health</h2>
                    <span class="status-pill">{{
                        state.is_due ? 'Reading due' : 'Up to date'
                    }}</span>
                </div>
                <p class="form-help">
                    Blood pressure and resting heart rate ·
                    {{ state.is_due ? 'Due since ' : 'Next due '
                    }}{{ state.due_on ? displayDate(state.due_on) : '' }}
                </p>
                <button class="text-link" @click="emit('open')">
                    {{
                        isCoach
                            ? 'View health readings'
                            : 'Record or view readings'
                    }}
                </button>
            </div>
            <template v-else-if="!compact">
                <p v-if="success" class="notice" role="status">{{ success }}</p>
                <div class="panel">
                    <div class="section-bar">
                        <h2>Weekly health monitoring</h2>
                        <span class="status-pill">{{
                            !state.monitoring_enabled
                                ? 'Not enabled'
                                : state.is_due
                                  ? 'Reading due'
                                  : 'Up to date'
                        }}</span>
                    </div>
                    <p class="form-help">
                        Your coach can use this to follow weekly blood pressure
                        and resting heart rate. Save what you have and agree
                        check-ins with your coach.
                    </p>
                    <p v-if="state.due_on" class="form-help">
                        {{
                            state.is_due
                                ? 'Reading due since '
                                : 'Next reading due '
                        }}{{ displayDate(state.due_on) }}.
                    </p>
                    <template v-if="isCoach">
                        <p class="form-help">
                            Enable this for clients who need weekly monitoring
                            during their cycle.
                        </p>
                        <button
                            class="button button-small"
                            :disabled="busy"
                            :aria-pressed="state.coach_enabled === true"
                            @click="toggleMonitoring"
                        >
                            {{
                                state.coach_enabled
                                    ? 'Disable weekly monitoring'
                                    : 'Enable weekly monitoring'
                            }}
                        </button>
                        <p
                            v-if="
                                state.monitoring_enabled && !state.coach_enabled
                            "
                            class="form-help"
                        >
                            Another active coach has enabled monitoring for this
                            client.
                        </p>
                    </template>
                    <p v-else-if="!state.monitoring_enabled" class="form-help">
                        Your coach has not enabled weekly monitoring. You can
                        still view and correct previous readings.
                    </p>
                </div>
                <form
                    v-if="!isCoach && (state.monitoring_enabled || editing)"
                    class="panel"
                    @submit.prevent="save"
                >
                    <h2>
                        {{
                            editing
                                ? 'Edit health reading'
                                : 'Record a health reading'
                        }}
                    </h2>
                    <p class="form-help">
                        Leave any field blank. A blank date uses today for a new
                        reading or keeps the original date when editing. Missing
                        measurements stay marked as not recorded.
                    </p>
                    <fieldset :disabled="busy" class="health-fields">
                        <div class="form-grid">
                            <label
                                >Reading date<input
                                    v-model="form.reading_date"
                                    type="date"
                                    :max="localDate()"
                            /></label>
                            <label
                                >Systolic pressure (mmHg)<input
                                    v-model="form.systolic"
                                    type="number"
                                    min="1"
                                    max="400"
                                    step="1"
                                    placeholder="Not recorded"
                            /></label>
                            <label
                                >Diastolic pressure (mmHg)<input
                                    v-model="form.diastolic"
                                    type="number"
                                    min="1"
                                    max="300"
                                    step="1"
                                    placeholder="Not recorded"
                            /></label>
                            <label
                                >Resting heart rate (bpm)<input
                                    v-model="form.resting_heart_rate"
                                    type="number"
                                    min="1"
                                    max="300"
                                    step="1"
                                    placeholder="Not recorded"
                            /></label>
                        </div>
                        <label class="notes-label"
                            >Notes<textarea
                                v-model="form.notes"
                                rows="2"
                                maxlength="5000"
                                placeholder="Anything relevant to this reading."
                            />
                        </label>
                        <div class="form-actions">
                            <button class="button">
                                {{ busy ? 'Saving…' : 'Save reading' }}
                            </button>
                            <button
                                v-if="editing"
                                type="button"
                                class="text-link"
                                @click="reset"
                            >
                                Cancel edit
                            </button>
                        </div>
                    </fieldset>
                </form>
                <div class="section-bar">
                    <h2>Reading history</h2>
                    <span class="eyebrow"
                        >{{ state.readings.total }} READINGS</span
                    >
                </div>
                <p v-if="!state.readings.data.length" class="empty-state">
                    No health readings recorded yet.
                </p>
                <article
                    v-for="reading in state.readings.data"
                    :key="reading.id"
                    class="panel"
                >
                    <div class="section-bar">
                        <h3>{{ displayDate(reading.reading_date) }}</h3>
                        <button
                            v-if="!isCoach"
                            class="text-link"
                            :disabled="busy"
                            @click="edit(reading)"
                        >
                            Edit reading
                        </button>
                    </div>
                    <p>
                        Blood pressure:
                        <strong
                            v-if="
                                reading.systolic === null &&
                                reading.diastolic === null
                            "
                            >Not recorded</strong
                        >
                        <strong v-else
                            >{{ reading.systolic ?? '—' }}/{{
                                reading.diastolic ?? '—'
                            }}
                            mmHg</strong
                        >
                    </p>
                    <p>
                        Resting heart rate:
                        <strong
                            >{{ reading.resting_heart_rate ?? 'Not recorded'
                            }}{{
                                reading.resting_heart_rate === null
                                    ? ''
                                    : ' bpm'
                            }}</strong
                        >
                    </p>
                    <p v-if="reading.notes" class="record-notes">
                        {{ reading.notes }}
                    </p>
                </article>
                <div v-if="state.readings.last_page > 1" class="pagination">
                    <button
                        :disabled="loading || busy || page <= 1"
                        @click="paginate(page - 1)"
                    >
                        Previous
                    </button>
                    <span
                        >Page {{ page }} of {{ state.readings.last_page }}</span
                    >
                    <button
                        :disabled="
                            loading || busy || page >= state.readings.last_page
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

<style scoped>
.health-fields {
    border: 0;
    padding: 0;
    margin: 20px 0 0;
    min-width: 0;
}
</style>
