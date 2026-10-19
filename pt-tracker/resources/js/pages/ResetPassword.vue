<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthShell from '@/components/AuthShell.vue';
import { api, message } from '@/lib/tracker-api';
const props = defineProps<{ token: string; email: string }>();
const email = ref(props.email),
    password = ref(''),
    confirmation = ref(''),
    error = ref('');
const busy = ref(false);
async function resetPassword() {
    busy.value = true;
    error.value = '';
    try {
        await api('/api/reset-password', 'POST', {
            token: props.token,
            email: email.value,
            password: password.value,
            password_confirmation: confirmation.value,
        });
        password.value = '';
        confirmation.value = '';
        router.visit('/login?reset=1');
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <AuthShell title="Reset password" heading="CHOOSE A&#10;NEW PASSWORD.">
        <form class="auth-fields" @submit.prevent="resetPassword">
            <p v-if="error" class="notice" role="alert">
                {{ error }}
                <Link href="/forgot-password" class="text-link"
                    >Request a new link</Link
                >
            </p>
            <label
                >Email address<input
                    v-model="email"
                    type="email"
                    autocomplete="email"
                    maxlength="255"
                    required
            /></label>
            <label
                >New password<input
                    v-model="password"
                    type="password"
                    autocomplete="new-password"
                    minlength="12"
                    maxlength="72"
                    required
                    aria-describedby="password-help"
            /></label>
            <p id="password-help" class="form-help">
                Use at least 12 characters, with uppercase and lowercase letters
                and a number. Maximum 72 bytes; accented characters and emoji
                use more than one byte.
            </p>
            <label
                >Confirm password<input
                    v-model="confirmation"
                    type="password"
                    autocomplete="new-password"
                    minlength="12"
                    maxlength="72"
                    required
            /></label>
            <button class="button" :disabled="busy">
                {{ busy ? 'Updating…' : 'Update password' }}
                <span aria-hidden="true">↗</span>
            </button>
        </form>
    </AuthShell>
</template>
