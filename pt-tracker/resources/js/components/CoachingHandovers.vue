<script setup lang="ts">
import DropdownSelect from '@/components/DropdownSelect.vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { api, displayDate, message } from '@/lib/tracker-api';
import type { Page } from '@/lib/tracker-api';

type Handover = {
    id: number;
    coach_name: string;
    discipline: string;
    started_on: string;
    ended_at: string;
    pdf_url: string;
    delete_after_view: boolean;
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
const busy = ref(false);
const opened = ref<{
    id: number;
    coach: string;
    url: string;
    token: string;
    removed: boolean;
} | null>(null);

async function openTemporary(handover: Handover) {
    busy.value = true;
    error.value = '';
    try {
        const data = await api<{ pdf_base64: string; view_token: string }>(
            `/api/coaching-handovers/${handover.id}/open`,
            'POST',
        );
        const bytes = Uint8Array.from(atob(data.pdf_base64), (character) =>
            character.charCodeAt(0),
        );
        const url = URL.createObjectURL(
            new Blob([bytes], { type: 'application/pdf' }),
        );
        if (opened.value) URL.revokeObjectURL(opened.value.url);
        opened.value = {
            id: handover.id,
            coach: handover.coach_name,
            url,
            token: data.view_token,
            removed: false,
        };
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
async function acknowledgeView() {
    const document = opened.value;
    if (!document || document.removed) return;
    try {
        await api(`/api/coaching-handovers/${document.id}/viewed`, 'POST', {
            view_token: document.token,
        });
        document.removed = true;
        handovers.value = handovers.value.filter(
            (item) => item.id !== document.id,
        );
        if (selectedId.value === document.id)
            selectedId.value = handovers.value[0]?.id ?? null;
        error.value = '';
    } catch (cause) {
        error.value = message(cause);
    }
}
onBeforeUnmount(() => {
    if (opened.value) URL.revokeObjectURL(opened.value.url);
});

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
            Your handovers are available here when coaching ends. Handovers
            issued after unpaid membership expiry are removed after you open
            them. Without an active coach, this is your only dashboard section.
            Your account and saved coaching history are preserved.
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
                    <DropdownSelect v-model="selectedId">
                        <option
                            v-for="handover in handovers"
                            :key="handover.id"
                            :value="handover.id"
                        >
                            {{ handover.coach_name }} /
                            {{ handover.discipline }} /
                            {{ displayDate(handover.ended_at) }}
                        </option>
                    </DropdownSelect>
                </label>
                <article v-if="selected" class="panel handover-document">
                    <h2>Your handover from {{ selected.coach_name }}</h2>
                    <p>
                        {{ displayDate(selected.started_on) }} to
                        {{ displayDate(selected.ended_at) }}
                    </p>
                    <div v-if="selected.delete_after_view" class="notice">
                        <p>
                            This handover will be removed from your account
                            after it opens. Download a copy during this visit if
                            you want to keep it.
                        </p>
                        <button
                            class="button button-small"
                            :disabled="busy"
                            @click="openTemporary(selected)"
                        >
                            {{ busy ? 'Opening…' : 'View handover' }}
                        </button>
                    </div>
                    <template v-else>
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
                            If the preview is unavailable on your device, use
                            Open PDF or Download PDF above.
                        </p>
                    </template>
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
        <article v-if="opened" class="panel handover-document">
            <h2>Your handover from {{ opened.coach }}</h2>
            <p v-if="opened.removed" class="notice" role="status">
                This handover has been removed from your account. You can still
                view and download this copy until you leave this page.
            </p>
            <div class="handover-actions">
                <a
                    :href="opened.url"
                    :download="`stage-ready-handover-${opened.id}.pdf`"
                    class="button button-small"
                    >Download your copy</a
                >
                <button
                    v-if="error && !opened.removed"
                    class="text-link"
                    @click="acknowledgeView"
                >
                    Confirm handover opened
                </button>
            </div>
            <iframe
                :key="opened.url"
                :src="opened.url"
                title="Your temporary Stage-Ready handover PDF"
                class="handover-preview"
                @load="acknowledgeView"
            />
        </article>
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
