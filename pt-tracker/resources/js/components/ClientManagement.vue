<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { api, displayDate, message } from '@/lib/tracker-api';
import type { Page, TrackerUser } from '@/lib/tracker-api';
defineProps<{ clients: TrackerUser[] }>();
const emit = defineEmits<{ changed: [] }>();
type Invitation = {
    id: number;
    name: string;
    email: string;
    expires_at: string;
};
type Delivery = { accept_url: string; delivery: 'sent' | 'local' | 'failed' };
const form = reactive({ name: '', email: '' });
const invitations = ref<Invitation[]>([]);
const busy = ref(false),
    loading = ref(true),
    error = ref(''),
    success = ref(''),
    link = ref('');
const page = ref(1),
    lastPage = ref(1),
    ending = ref<TrackerUser | null>(null);
const linkInput = ref<HTMLInputElement | null>(null);
const canPage = computed(() => !busy.value && !loading.value);
async function loadInvitations() {
    loading.value = true;
    try {
        const result = await api<Page<Invitation>>(
            '/api/client-invitations?page=' + page.value,
        );
        invitations.value = result.data;
        lastPage.value = result.last_page;
    } catch (cause) {
        error.value = message(cause);
    } finally {
        loading.value = false;
    }
}
function showDelivery(result: Delivery) {
    link.value = result.accept_url;
    success.value =
        result.delivery === 'sent'
            ? 'Invitation emailed. You can also copy the link below.'
            : result.delivery === 'local'
              ? 'Invitation created. This installation saves emails to a local log; copy the link below to share it.'
              : 'Invitation created, but the email could not be sent. Copy the link below or check your mail settings and resend.';
}
async function sendInvitation() {
    busy.value = true;
    error.value = '';
    success.value = '';
    link.value = '';
    try {
        showDelivery(
            await api<Delivery>('/api/client-invitations', 'POST', form),
        );
        form.name = '';
        form.email = '';
        page.value = 1;
        await loadInvitations();
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
async function changeInvitation(invitation: Invitation, resend: boolean) {
    busy.value = true;
    error.value = '';
    success.value = '';
    link.value = '';
    try {
        const url = '/api/client-invitations/' + invitation.id;
        if (resend) showDelivery(await api<Delivery>(url + '/resend', 'POST'));
        else {
            await api(url, 'DELETE');
            success.value =
                'Invitation cancelled. Its link can no longer be used.';
        }
        await loadInvitations();
        if (!invitations.value.length && page.value > 1) {
            page.value--;
            await loadInvitations();
        }
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
async function endCoaching() {
    if (!ending.value) return;
    busy.value = true;
    error.value = '';
    success.value = '';
    link.value = '';
    try {
        const result = await api<{ message: string }>(
            '/api/clients/' + ending.value.id + '/coaching',
            'DELETE',
        );
        ending.value = null;
        success.value = result.message;
        emit('changed');
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
async function copyLink() {
    try {
        await navigator.clipboard.writeText(link.value);
        success.value = 'Invitation link copied.';
    } catch {
        linkInput.value?.focus();
        linkInput.value?.select();
        success.value = 'Select and copy the invitation link below.';
    }
}
async function paginate(next: number) {
    page.value = next;
    error.value = '';
    await loadInvitations();
}
onMounted(() => {
    void loadInvitations();
});
</script>
<template>
    <section>
        <p v-if="error" class="notice" role="alert">
            {{ error }}
            <button
                class="text-link"
                :disabled="busy || loading"
                @click="loadInvitations"
            >
                Retry
            </button>
        </p>
        <p v-if="success" class="notice" role="status">{{ success }}</p>
        <div v-if="link" class="panel invitation-link">
            <label
                >Invitation link<input
                    ref="linkInput"
                    :value="link"
                    readonly
                    @focus="
                        ($event.target as HTMLInputElement).select()
                    " /></label
            ><button class="button button-small" @click="copyLink">
                Copy link
            </button>
        </div>
        <form class="panel" @submit.prevent="sendInvitation">
            <div class="section-bar">
                <h2>Invite a client</h2>
                <span class="eyebrow">READY WHEN THEY JOIN</span>
            </div>
            <p class="form-help">
                Send an invitation to set up their login. Coaching access starts
                as soon as they accept.
            </p>
            <div class="form-grid onboarding-fields">
                <label
                    >Client name<input
                        v-model="form.name"
                        autocomplete="name"
                        maxlength="100"
                        required
                /></label>
                <label
                    >Email address<input
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        maxlength="255"
                        required
                /></label>
            </div>
            <div class="form-actions">
                <button class="button" :disabled="busy">
                    {{ busy ? 'Saving…' : 'Send invitation' }} ↗
                </button>
            </div>
        </form>
        <div class="section-bar">
            <h2>Active clients</h2>
            <button class="text-link" :disabled="busy" @click="emit('changed')">
                Refresh clients ↻
            </button>
        </div>
        <p v-if="!clients.length" class="empty-state">
            No active clients yet. Invite your first client above.
        </p>
        <article
            v-for="client in clients"
            :key="client.id"
            class="workout-summary client-row"
        >
            <div>
                <h3>{{ client.name }}</h3>
                <p>{{ client.email }}</p>
            </div>
            <button class="text-link" :disabled="busy" @click="ending = client">
                End coaching
            </button>
        </article>
        <div
            v-if="ending"
            class="panel"
            role="region"
            aria-label="Confirm end coaching"
        >
            <h3>End coaching for {{ ending.name }}?</h3>
            <p class="form-help">
                You will lose access to their records. They keep their account,
                workouts, and check-in history.
            </p>
            <div class="form-actions">
                <button
                    class="button button-small"
                    :disabled="busy"
                    @click="endCoaching"
                >
                    End coaching</button
                ><button
                    class="text-link"
                    :disabled="busy"
                    @click="ending = null"
                >
                    Keep coaching
                </button>
            </div>
        </div>
        <div class="section-bar onboarding-fields">
            <h2>Invitations</h2>
            <span class="eyebrow">AWAITING ACCEPTANCE</span>
        </div>
        <p v-if="loading" class="loading-message" role="status">
            Loading invitations…
        </p>
        <p v-else-if="!invitations.length" class="empty-state">
            No invitations waiting for acceptance.
        </p>
        <article
            v-for="invitation in invitations"
            :key="invitation.id"
            class="workout-summary client-row"
        >
            <div>
                <h3>{{ invitation.name }}</h3>
                <p>{{ invitation.email }}</p>
                <p class="form-help">
                    {{
                        new Date(invitation.expires_at).getTime() <= Date.now()
                            ? 'Link expired'
                            : 'Link expires ' +
                              displayDate(invitation.expires_at)
                    }}
                </p>
            </div>
            <div class="row-actions">
                <button
                    class="text-link"
                    :disabled="busy"
                    @click="changeInvitation(invitation, true)"
                >
                    Resend</button
                ><button
                    class="text-link"
                    :disabled="busy"
                    @click="changeInvitation(invitation, false)"
                >
                    Cancel invitation
                </button>
            </div>
        </article>
        <div v-if="lastPage > 1" class="pagination">
            <button
                :disabled="!canPage || page <= 1"
                @click="paginate(page - 1)"
            >
                Previous</button
            ><span>Page {{ page }} of {{ lastPage }}</span
            ><button
                :disabled="!canPage || page >= lastPage"
                @click="paginate(page + 1)"
            >
                Next
            </button>
        </div>
    </section>
</template>
