<script setup lang="ts">
import { ref } from 'vue';
import AuthShell from '@/components/AuthShell.vue';
import { api, message } from '@/lib/tracker-api';
const email = ref(''),
    error = ref(''),
    success = ref('');
const busy = ref(false);
async function sendLink() {
    busy.value = true;
    error.value = '';
    success.value = '';
    try {
        const result = await api<{ message: string }>(
            '/api/forgot-password',
            'POST',
            { email: email.value },
        );
        success.value = result.message;
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <AuthShell title="Forgot password" heading="LET’S GET YOU&#10;BACK IN.">
        <p class="form-help">
            Enter your account email and we’ll send you a link to choose a new
            password.
        </p>
        <p v-if="success" class="notice" role="status">{{ success }}</p>
        <form class="auth-fields" @submit.prevent="sendLink">
            <p v-if="error" class="notice" role="alert">{{ error }}</p>
            <label
                >Email address<input
                    v-model="email"
                    type="email"
                    autocomplete="email"
                    maxlength="255"
                    required
            /></label>
            <button class="button" :disabled="busy">
                {{ busy ? 'Sending…' : 'Send reset link' }}
                <span aria-hidden="true">↗</span>
            </button>
        </form>
    </AuthShell>
</template>
