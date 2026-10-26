<script setup lang="ts">
import { ref, watch } from 'vue';
import { api, message } from '@/lib/tracker-api';
import type { TrackerUser } from '@/lib/tracker-api';

const props = defineProps<{ user: TrackerUser }>();
const emit = defineEmits<{ updated: [user: TrackerUser] }>();
const bodybuilding = ref(props.user.is_bodybuilding_coach);
const busy = ref(false),
    error = ref(''),
    success = ref('');
watch(
    () => props.user.is_bodybuilding_coach,
    (value) => {
        bodybuilding.value = value;
    },
);
async function save() {
    busy.value = true;
    error.value = '';
    success.value = '';
    try {
        const result = await api<{ data: TrackerUser }>(
            '/api/user/coaching-type',
            'PATCH',
            {
                is_bodybuilding_coach: bodybuilding.value,
            },
        );
        emit('updated', result.data);
        success.value = 'Coaching discipline saved.';
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <form class="panel" @submit.prevent="save">
        <div class="section-bar"><h2>Your coaching discipline</h2></div>
        <p class="form-help">
            Bodybuilding coaching includes private check-in photos and posing
            reviews. Your clients’ photo tools follow their active coaching
            assignments.
        </p>
        <p v-if="error" class="notice" role="alert">{{ error }}</p>
        <p v-if="success" class="notice" role="status">{{ success }}</p>
        <label class="discipline-field">
            Coaching discipline
            <select v-model="bodybuilding" :disabled="busy">
                <option :value="true">Bodybuilding</option>
                <option :value="false">Powerlifting</option>
            </select>
        </label>
        <div class="form-actions">
            <button
                class="button button-small"
                :disabled="busy || bodybuilding === user.is_bodybuilding_coach"
            >
                {{ busy ? 'Saving…' : 'Save discipline' }}
            </button>
        </div>
    </form>
</template>
<style scoped>
.discipline-field {
    max-width: 320px;
    margin-top: 20px;
}
</style>
