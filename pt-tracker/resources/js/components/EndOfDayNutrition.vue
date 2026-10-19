<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { api, localDate, message } from '@/lib/tracker-api';
const props = defineProps<{ clientId: number; canEdit: boolean }>();
const emit = defineEmits<{ saved: [] }>();
type Responses = {
    hunger_level: number | null;
    appetite_level: number | null;
    digestive_discomfort: number | null;
    digestion_notes: string | null;
    dietary_difficulties: string | null;
    nutrition_completed_at: string | null;
};
const date = ref(localDate()),
    loading = ref(true),
    saving = ref(false),
    error = ref(''),
    success = ref(''),
    completedAt = ref<string | null>(null);
const ready = ref(false);
const form = reactive<Record<string, string | number | null>>({
    hunger_level: '',
    appetite_level: '',
    digestive_discomfort: '',
    digestion_notes: '',
    dietary_difficulties: '',
});
const fields = [
    {
        key: 'hunger_level',
        label: 'Hunger',
        help: 'Across today: 1 = not hungry, 5 = very hungry.',
    },
    {
        key: 'appetite_level',
        label: 'Appetite',
        help: 'Desire to eat across today: 1 = very low, 5 = very high.',
    },
    {
        key: 'digestive_discomfort',
        label: 'Digestive discomfort',
        help: 'Across today: 1 = none, 5 = severe.',
    },
];
const base = '/api/clients/' + props.clientId + '/end-of-day-nutrition';
function apply(data: Responses | null) {
    Object.keys(form).forEach((key) => {
        form[key] = data?.[key as keyof Responses] ?? '';
    });
    completedAt.value = data?.nutrition_completed_at ?? null;
}
async function load() {
    date.value ||= localDate();
    loading.value = true;
    ready.value = false;
    error.value = '';
    success.value = '';
    apply(null);
    try {
        apply(
            (
                await api<{ data: Responses | null }>(
                    base + '?date=' + date.value,
                )
            ).data,
        );
        ready.value = true;
    } catch (cause) {
        error.value = message(cause);
    } finally {
        loading.value = false;
    }
}
async function save() {
    saving.value = true;
    error.value = '';
    success.value = '';
    try {
        const payload = Object.fromEntries(
            Object.entries(form).map(([key, value]) => [
                key,
                value === '' ? null : value,
            ]),
        );
        apply(
            (
                await api<{ data: Responses | null }>(base, 'PUT', {
                    check_in_date: date.value,
                    ...payload,
                })
            ).data,
        );
        success.value = completedAt.value
            ? 'End-of-day nutrition check-in saved.'
            : 'Nutrition responses cleared. This day is not marked complete.';
        emit('saved');
    } catch (cause) {
        error.value = message(cause);
    } finally {
        saving.value = false;
    }
}
onMounted(load);
</script>
<template>
    <section class="panel" aria-label="End-of-day nutrition check-in">
        <div class="section-bar">
            <h2>End-of-day nutrition check-in</h2>
            <span class="status-pill">{{
                completedAt ? 'Completed' : 'Not completed'
            }}</span>
        </div>
        <p class="form-help">
            {{
                canEdit
                    ? 'Complete this at the end of your day, after your meals. Reflect on the whole day rather than how you feel right now. Save it separately from your posing photos.'
                    : 'The client completes this after their meals at the end of the day.'
            }}
        </p>
        <p v-if="error" class="notice" role="alert">
            {{ error }}
            <button
                class="text-link"
                :disabled="loading || saving"
                @click="load"
            >
                Retry
            </button>
        </p>
        <p v-if="success" class="notice" role="status">{{ success }}</p>
        <label
            >Nutrition check-in date<input
                v-model="date"
                type="date"
                :max="localDate()"
                :disabled="loading || saving"
                @change="load"
        /></label>
        <p v-if="loading" class="loading-message" role="status">
            Loading nutrition responses…
        </p>
        <form v-else-if="ready" @submit.prevent="save">
            <fieldset :disabled="saving || !canEdit" class="coaching-fields">
                <div class="form-grid">
                    <label v-for="field in fields" :key="field.key"
                        >{{ field.label }} (1–5)<input
                            v-model.number="form[field.key]"
                            :aria-label="field.label + ' (1–5)'"
                            type="number"
                            min="1"
                            max="5"
                            step="1"
                            placeholder="Not recorded"
                        /><span class="form-help">{{ field.help }}</span></label
                    >
                </div>
                <label class="notes-label"
                    >Digestion notes<textarea
                        v-model="form.digestion_notes"
                        rows="2"
                        maxlength="5000"
                        placeholder="Bloating, bowel regularity, discomfort, or foods that were difficult to tolerate today."
                    />
                </label>
                <label class="notes-label"
                    >Dietary difficulties<textarea
                        v-model="form.dietary_difficulties"
                        rows="2"
                        maxlength="5000"
                        placeholder="Trouble finishing meals, missed meals, cravings, food access, or anything that made today's plan difficult."
                    />
                </label>
                <p class="form-help">
                    Leave anything you did not record blank. Previous days can
                    be completed or corrected here too.
                </p>
                <button v-if="canEdit" class="button">
                    {{ saving ? 'Saving…' : 'Save end-of-day nutrition' }}
                </button>
            </fieldset>
        </form>
    </section>
</template>
