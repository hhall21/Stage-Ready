<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { api, displayDate, message } from '@/lib/tracker-api';
import type { Page } from '@/lib/tracker-api';

type Handover = {
    id: number;
    coach_name: string;
    discipline: string;
    started_on: string;
    ended_at: string;
    pdf_url: string;
};
const handovers = ref<Handover[]>([]);
const selectedId = ref<number | null>(null);
const selected = computed(() =>
    handovers.value.find((handover) => handover.id === selectedId.value),
);
const loading = ref(true),
    error = ref('');
const page = ref(1),
    lastPage = ref(1);

async function load(next = page.value) {
    loading.value = true;
    error.value = '';
    try {
        const result = await api<Page<Handover>>(
            '/api/coaching-handovers?page=' + next,
        );
        handovers.value = result.data;
        page.value = result.current_page;
        lastPage.value = result.last_page;
        selectedId.value = result.data[0]?.id ?? null;
    } catch (cause) {
        error.value = message(cause);
    } finally {
        loading.value = false;
    }
}
onMounted(() => {
    void load();
});
</script>
<template>
    <section aria-label="Coaching handovers" :aria-busy="loading">
        <p class="form-help">
            Your handovers remain available when coaching ends. Without an active
            coach, this is your only dashboard section. Your account and saved
            coaching history are preserved.
        </p>
        <p v-if="loading" class="loading-message" role="status">
            Loading your handovers…
        </p>
        <p v-else-if="error" class="notice" role="alert">
            {{ error }} <button class="text-link" @click="load()">Retry</button>
        </p>
        <template v-else>
            <p v-if="!handovers.length" class="empty-state">
                No coaching handovers yet. Your coach’s handover will appear
                here when coaching ends.
            </p>
            <template v-else>
                <label class="handover-picker">
                    Coaching period
                    <select v-model="selectedId">
                        <option
                            v-for="handover in handovers"
                            :key="handover.id"
                            :value="handover.id"
                        >
                            {{ handover.coach_name }} /
                            {{ handover.discipline }} /
                            {{ displayDate(handover.ended_at) }}
                        </option>
                    </select>
                </label>
                <article v-if="selected" class="panel handover-document">
                    <h2>Your handover from {{ selected.coach_name }}</h2>
                    <p>
                        {{ displayDate(selected.started_on) }} to
                        {{ displayDate(selected.ended_at) }}
                    </p>
                    <div class="handover-actions">
                        <a
                            class="button button-small"
                            :href="selected.pdf_url + '?download=1'"
                            >Download PDF</a
                        >
                        <a
                            class="text-link"
                            :href="selected.pdf_url"
                            target="_blank"
                            rel="noopener"
                            >Open PDF in a new tab</a
                        >
                    </div>
                    <iframe
                        :key="selected.id"
                        :src="selected.pdf_url"
                        title="Your Stage-Ready coaching handover PDF"
                        class="handover-preview"
                    />
                    <p class="form-help">
                        If the preview is unavailable on your device, use Open
                        PDF or Download PDF above.
                    </p>
                </article>
            </template>
            <div v-if="lastPage > 1" class="pagination">
                <button
                    :disabled="loading || page <= 1"
                    @click="load(page - 1)"
                >
                    Previous
                </button>
                <span>Page {{ page }} of {{ lastPage }}</span>
                <button
                    :disabled="loading || page >= lastPage"
                    @click="load(page + 1)"
                >
                    Next
                </button>
            </div>
        </template>
    </section>
</template>
<style scoped>
.handover-picker {
    max-width: 640px;
    margin-block: 24px;
}
.handover-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 16px 24px;
    margin-block: 24px;
}
.handover-document {
    min-width: 0;
}
.handover-document h2 {
    overflow-wrap: anywhere;
}
.handover-preview {
    width: 100%;
    height: 75vh;
    min-height: 420px;
    border: 1px solid var(--line);
    background: var(--ink);
}
</style>
