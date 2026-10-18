<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthShell from '@/components/AuthShell.vue';
import { api, message } from '@/lib/tracker-api';
const props = defineProps<{
    token: string;
    invitation: { name: string; email: string; coach: string } | null;
    existingAccount: boolean;
    signedInAsInvitee: boolean;
}>();
const page = usePage();
const password = ref(''),
    confirmation = ref(''),
    error = ref('');
const busy = ref(false);
async function accept() {
    busy.value = true;
    error.value = '';
    try {
        await api(
            '/api/invitations/' + props.token + '/accept',
            'POST',
            props.existingAccount
                ? {}
                : {
                      password: password.value,
                      password_confirmation: confirmation.value,
                  },
        );
        password.value = '';
        confirmation.value = '';
        router.visit('/dashboard');
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
async function signOut() {
    busy.value = true;
    error.value = '';
    try {
        await api('/api/logout', 'POST');
        router.reload();
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <AuthShell title="Join your coach" heading="YOUR COACHING&#10;STARTS HERE.">
        <template v-if="invitation">
            <p class="form-help">
                {{ invitation.coach }} has invited {{ invitation.name }} to
                Stage-Ready. Your workspace is available immediately after you
                join.
            </p>
            <p class="invited-email">{{ invitation.email }}</p>
            <p v-if="error" class="notice" role="alert">{{ error }}</p>
            <template v-if="existingAccount && !signedInAsInvitee">
                <p class="form-help">
                    Sign in to this email’s existing account to accept. Your
                    password and training history stay with your account.
                </p>
                <button
                    v-if="page.props.auth.user"
                    class="button"
                    :disabled="busy"
                    @click="signOut"
                >
                    Sign out of the current account
                </button>
                <Link
                    v-else
                    class="button"
                    :href="
                        '/login?next=' + encodeURIComponent('/join/' + token)
                    "
                    >Sign in to accept ↗</Link
                >
            </template>
            <template v-else-if="!existingAccount && page.props.auth.user">
                <p class="form-help">
                    You’re signed in to a different account. Sign out to set up
                    the invited account.
                </p>
                <button class="button" :disabled="busy" @click="signOut">
                    Sign out
                </button>
            </template>
            <form v-else class="auth-fields" @submit.prevent="accept">
                <template v-if="!existingAccount">
                    <label
                        >Create password<input
                            v-model="password"
                            type="password"
                            autocomplete="new-password"
                            minlength="12"
                            maxlength="128"
                            required
                            aria-describedby="join-password-help"
                    /></label>
                    <p id="join-password-help" class="form-help">
                        Use at least 12 characters, with uppercase and lowercase
                        letters and a number.
                    </p>
                    <label
                        >Confirm password<input
                            v-model="confirmation"
                            type="password"
                            autocomplete="new-password"
                            minlength="12"
                            maxlength="128"
                            required
                    /></label>
                </template>
                <button class="button" :disabled="busy">
                    {{ busy ? 'Joining…' : 'Accept invitation' }}
                    <span aria-hidden="true">↗</span>
                </button>
                <p class="form-help">
                    Accepting gives {{ invitation.coach }} access to your
                    Stage-Ready training records and check-ins while your
                    coaching is active.
                </p>
            </form>
        </template>
        <div v-else class="notice" role="status">
            This invitation has expired, been cancelled, or already been
            accepted. Ask your coach for a new invitation, or sign in if you
            have already joined.
        </div>
    </AuthShell>
</template>
