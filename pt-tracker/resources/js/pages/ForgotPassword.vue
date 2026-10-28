<script setup lang="ts">
import { onUnmounted, ref } from 'vue';
import AuthShell from '@/components/AuthShell.vue';
import { api, ApiError, message } from '@/lib/tracker-api';
const email = ref(''),
    error = ref(''),
    success = ref('');
const busy = ref(false);
const retryIn = ref(0);
let retryTimer: ReturnType<typeof setInterval> | undefined;
function waitForRetry(seconds: number) {
    const deadline = Date.now() + seconds * 1000;
    retryIn.value = seconds;
    clearInterval(retryTimer);
    retryTimer = setInterval(() => {
        retryIn.value = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
        if (retryIn.value === 0) {
            clearInterval(retryTimer);
            error.value = '';
        }
    }, 1000);
}
onUnmounted(() => clearInterval(retryTimer));
async function sendLink() {
    if (busy.value || retryIn.value > 0) return;
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
        if (cause instanceof ApiError && cause.status === 429)
            waitForRetry(cause.retryAfter ?? 60);
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
            <button class="button" :disabled="busy || retryIn > 0">
                {{
                    busy
                        ? 'Sending…'
                        : retryIn > 0
                          ? `Try again in ${retryIn}s`
                          : 'Send reset link'
                }}
                <span aria-hidden="true">↗</span>
            </button>
        </form>
    </AuthShell>
</template>
