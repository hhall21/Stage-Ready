<script setup lang="ts">
import DropdownDetails from '@/components/DropdownDetails.vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import CheckInPosing from '@/components/CheckInPosing.vue';
import EndOfDayNutrition from '@/components/EndOfDayNutrition.vue';
import { motionScrollBehavior } from '@/lib/motion';
import { api, displayDate, localDate, message } from '@/lib/tracker-api';
import type { CheckIn, Page, TrackerUser } from '@/lib/tracker-api';
const props = defineProps<{ clientId: number; user: TrackerUser }>();
const selected = props.clientId;
const user = props.user;
const canCheckIn = computed(() => user.role === 'client');
const posingEnabled = ref(false);
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

const checkins = ref<CheckIn[]>([]);
const checkPage = ref(1),
    checkLast = ref(1),
    checkTotal = ref(0);
const editCheck = ref<number | null>(null);
const openPosing = ref<number | null>(null);
const savedPosingCheck = ref<CheckIn | null>(null);
const checkForm = reactive<Record<string, string | number | null>>({
    check_in_date: localDate(),
    bodyweight: '',
    sleep_hours: '',
    sleep_quality: '',
    energy_level: '',
    stress_level: '',
    steps: '',
    water_litres: '',
    notes: '',
});
const recoveryFields = [
    { key: 'sleep_hours', label: 'Sleep (hours)', max: 24, step: '0.25' },
    {
        key: 'sleep_quality',
        label: 'Sleep quality (1–5)',
        min: 1,
        max: 5,
        step: '1',
    },
    { key: 'energy_level', label: 'Energy (1–5)', min: 1, max: 5, step: '1' },
    { key: 'stress_level', label: 'Stress (1–5)', min: 1, max: 5, step: '1' },
    { key: 'steps', label: 'Steps', max: 4294967295, step: '1' },
    { key: 'water_litres', label: 'Water (litres)', max: 99.99, step: '0.1' },
];
const nutritionFields = [
    {
        key: 'hunger_level',
        label: 'Hunger',
        help: 'How hungry did you feel? 1 = not hungry, 5 = very hungry.',
    },
    {
        key: 'appetite_level',
        label: 'Appetite',
        help: 'How much did you want to eat? 1 = very low appetite, 5 = very high appetite.',
    },
    {
        key: 'digestive_discomfort',
        label: 'Digestive discomfort',
        help: '1 = no discomfort, 5 = severe discomfort.',
    },
];
function hasNutrition(record: CheckIn) {
    return (
        nutritionFields.some(
            ({ key }) => record[key as keyof CheckIn] != null,
        ) ||
        !!record.digestion_notes ||
        !!record.dietary_difficulties
    );
}

async function loadRecords() {
    const version = ++loadVersion;
    loading.value = true;
    error.value = '';
    try {
        const checks = await api<Page<CheckIn> & { posing_enabled: boolean }>(
            base.value + '/daily-check-ins?page=' + checkPage.value,
        );
        if (version !== loadVersion) return;
        posingEnabled.value = checks.posing_enabled;
        checkins.value = checks.data;
        checkLast.value = checks.last_page;
        checkTotal.value = checks.total;
    } catch (cause) {
        if (version === loadVersion) handleError(cause);
    } finally {
        if (version === loadVersion) loading.value = false;
    }
}
function resetCheck() {
    editCheck.value = null;
    Object.keys(checkForm).forEach((key) => {
        checkForm[key] = key === 'check_in_date' ? localDate() : '';
    });
}
function hasRecoveryDetails(record: CheckIn) {
    return (
        !!record.notes ||
        recoveryFields.some(({ key }) => {
            const value = record[key as keyof CheckIn];
            return value !== null && value !== undefined && value !== '';
        })
    );
}
function edit(record: CheckIn) {
    openPosing.value = null;
    editCheck.value = record.id;
    Object.keys(checkForm).forEach((key) => {
        checkForm[key] = record[key as keyof CheckIn] ?? '';
    });
    checkForm.check_in_date = record.check_in_date.slice(0, 10);
    document
        .getElementById('check-form')
        ?.scrollIntoView({ behavior: motionScrollBehavior(), block: 'start' });
}
async function saveCheck() {
    saving.value = true;
    error.value = '';
    success.value = '';
    const payload = Object.fromEntries(
        Object.entries(checkForm).map(([key, value]) => [
            key,
            value === '' ? null : value,
        ]),
    );
    try {
        const result = await api<{ data: CheckIn }>(
            base.value +
                '/daily-check-ins' +
                (editCheck.value ? '/' + editCheck.value : ''),
            editCheck.value ? 'PATCH' : 'POST',
            payload,
        );
        resetCheck();
        checkPage.value = 1;
        await loadRecords();
        openPosing.value = posingEnabled.value ? result.data.id : null;
        savedPosingCheck.value =
            !posingEnabled.value ||
            checkins.value.some((record) => record.id === result.data.id)
                ? null
                : result.data;
        success.value = !posingEnabled.value
            ? 'Check-in saved. Your coach can follow up on anything left blank.'
            : result.data.submitted_at
              ? 'Check-in details saved. Your submitted pose photos and coach feedback are below.'
              : 'Check-in saved. You can add any pose photos or submit it as it is. Your coach can follow up on anything left blank.';
    } catch (cause) {
        handleError(cause);
    } finally {
        saving.value = false;
    }
}

async function removeRecord(_kind: 'daily-check-ins', id: number) {
    if (
        !window.confirm(
            'Delete this check-in and all its attached records? This cannot be undone.',
        )
    )
        return;
    saving.value = true;
    error.value = '';
    try {
        await api(base.value + '/daily-check-ins/' + id, 'DELETE');
        checkPage.value = 1;
        await loadRecords();
        success.value = 'Check-in deleted.';
    } catch (cause) {
        handleError(cause);
    } finally {
        saving.value = false;
    }
}
async function changePage(_kind: 'check', delta: number) {
    checkPage.value += delta;
    await loadRecords();
}
onMounted(loadRecords);
</script>
<template>
    <section aria-label="Daily check-ins">
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
        <EndOfDayNutrition
            data-section="nutrition"
            v-if="selected"
            :key="'nutrition-check-in-' + selected"
            :client-id="selected"
            :can-edit="canCheckIn"
            @saved="loadRecords"
        />
        <form
            v-if="canCheckIn"
            id="check-form"
            class="panel"
            @submit.prevent="saveCheck"
        >
            <div class="section-bar">
                <h2>
                    {{
                        editCheck
                            ? posingEnabled
                                ? 'Edit your posing check-in'
                                : 'Edit your daily check-in'
                            : posingEnabled
                              ? 'Your daily posing check-in'
                              : 'Your daily check-in'
                    }}
                </h2>
            </div>
            <p class="form-help checkin-form-help">
                Share what you want to record. Your coach can follow up on your
                check-ins. Save nutrition separately at the end of your day.
            </p>
            <div class="form-grid">
                <label
                    >Check-in date<input
                        v-model="checkForm.check_in_date"
                        type="date"
                /></label>
                <label
                    >Bodyweight (kg)<input
                        v-model.number="checkForm.bodyweight"
                        type="number"
                        min="0"
                        max="999.99"
                        step="0.01"
                        placeholder="—"
                /></label>
            </div>
            <p class="form-help">
                Leave the date blank to use today, or keep the original date
                when editing.
            </p>
            <DropdownDetails class="checkin-details" :open="!!editCheck">
                <template #summary>Recovery and notes</template>
                <div class="form-grid">
                    <label v-for="field in recoveryFields" :key="field.key"
                        >{{ field.label
                        }}<input
                            v-model.number="checkForm[field.key]"
                            type="number"
                            :min="field.min ?? 0"
                            :max="field.max"
                            :step="field.step"
                            placeholder="—" /></label
                    ><label class="full-width"
                        >Notes<textarea
                            v-model="checkForm.notes"
                            rows="3"
                            maxlength="5000"
                            placeholder="How are you feeling today?"
                        />
                    </label>
                </div>
            </DropdownDetails>
            <div class="form-actions">
                <button class="button" :disabled="saving">
                    {{ saving ? 'Saving…' : 'Save check-in' }}
                    ↗</button
                ><button
                    v-if="editCheck"
                    type="button"
                    class="text-link"
                    :disabled="saving"
                    @click="resetCheck"
                >
                    Cancel edit
                </button>
            </div>
        </form>
        <div
            v-if="
                savedPosingCheck &&
                posingEnabled &&
                selected &&
                user &&
                !checkins.some((record) => record.id === savedPosingCheck!.id)
            "
            class="checkin-record"
        >
            <h3>
                Continue your
                {{ displayDate(savedPosingCheck.check_in_date) }}
                check-in
            </h3>
            <CheckInPosing
                :key="selected + ':saved:' + savedPosingCheck.id"
                :client-id="selected"
                :check-in="savedPosingCheck"
                :user="user"
                initially-open
                @updated="Object.assign(savedPosingCheck!, $event)"
            />
        </div>
        <div class="section-bar">
            <h2>
                {{
                    posingEnabled
                        ? 'Posing check-in history'
                        : 'Daily check-in history'
                }}
            </h2>
            <span class="eyebrow">{{ checkTotal }} ENTRIES</span>
        </div>
        <div v-if="!checkins.length" class="empty-state">
            <p>
                {{
                    posingEnabled
                        ? canCheckIn
                            ? 'No posing check-ins yet. Your daily pose photos and coach feedback will appear here.'
                            : 'No posing check-ins yet. The client’s daily entries and your feedback will appear here.'
                        : 'No daily check-ins recorded yet.'
                }}
            </p>
        </div>
        <div v-for="record in checkins" :key="record.id" class="checkin-record">
            <div class="section-bar">
                <h3>{{ displayDate(record.check_in_date) }}</h3>
                <div v-if="canCheckIn" class="row-actions">
                    <button
                        class="text-link"
                        :disabled="saving"
                        @click="edit(record)"
                    >
                        Edit</button
                    ><button
                        class="text-link"
                        :disabled="saving"
                        @click="removeRecord('daily-check-ins', record.id)"
                    >
                        Delete
                    </button>
                </div>
            </div>
            <div class="checkin-values">
                <span
                    >Bodyweight<strong>{{
                        record.bodyweight === null
                            ? 'Not recorded'
                            : record.bodyweight + ' kg'
                    }}</strong></span
                >
            </div>
            <CheckInPosing
                v-if="posingEnabled && selected && user"
                :key="selected + ':' + record.id"
                :client-id="selected"
                :check-in="record"
                :user="user"
                :initially-open="openPosing === record.id"
                @updated="Object.assign(record, $event)"
            />
            <DropdownDetails
                v-if="hasRecoveryDetails(record)"
                class="checkin-details"
            >
                <template #summary>Recovery and notes</template>
                <div class="checkin-values">
                    <span
                        >Sleep
                        <strong>{{ record.sleep_hours ?? '—' }} h</strong></span
                    ><span
                        >Steps
                        <strong>{{
                            record.steps?.toLocaleString() ?? '—'
                        }}</strong></span
                    ><span
                        >Water
                        <strong
                            >{{ record.water_litres ?? '—' }} L</strong
                        ></span
                    ><span
                        >Sleep quality
                        <strong
                            >{{ record.sleep_quality ?? '—' }}/5</strong
                        ></span
                    ><span
                        >Energy
                        <strong
                            >{{ record.energy_level ?? '—' }}/5</strong
                        ></span
                    ><span
                        >Stress
                        <strong
                            >{{ record.stress_level ?? '—' }}/5</strong
                        ></span
                    >
                </div>
                <p v-if="record.notes" class="record-notes">
                    {{ record.notes }}
                </p>
            </DropdownDetails>
            <DropdownDetails
                v-if="hasNutrition(record)"
                class="checkin-details"
                open
            >
                <template #summary>End-of-day nutrition responses</template>
                <div class="checkin-values">
                    <span v-for="field in nutritionFields" :key="field.key"
                        >{{ field.label
                        }}<strong
                            >{{
                                record[field.key as keyof CheckIn] ?? '—'
                            }}/5</strong
                        ></span
                    >
                </div>
                <template v-if="record.digestion_notes"
                    ><h3>Digestion notes</h3>
                    <p class="record-notes">
                        {{ record.digestion_notes }}
                    </p></template
                >
                <template v-if="record.dietary_difficulties"
                    ><h3>Dietary difficulties</h3>
                    <p class="record-notes">
                        {{ record.dietary_difficulties }}
                    </p></template
                >
            </DropdownDetails>
        </div>
        <div v-if="checkLast > 1" class="pagination">
            <button
                :disabled="checkPage === 1 || loading"
                @click="changePage('check', -1)"
            >
                ← Previous</button
            ><span>{{ checkPage }} / {{ checkLast }}</span
            ><button
                :disabled="checkPage === checkLast || loading"
                @click="changePage('check', 1)"
            >
                Next →
            </button>
        </div>
    </section>
</template>
