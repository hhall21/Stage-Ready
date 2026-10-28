<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AuthShell from '@/components/AuthShell.vue';
import { useCoachPricing } from '@/composables/useCoachPricing';
import { api, message } from '@/lib/tracker-api';
const { coachPlan } = useCoachPricing();
const page = usePage();
const verified = computed(() =>
    Boolean(page.props.auth.user?.email_verified_at),
);
const busy = ref(false);
const notice = ref('');
const error = ref('');
let refreshing = false;
function refreshVerification() {
    if (
        document.visibilityState !== 'visible' ||
        verified.value ||
        busy.value ||
        refreshing
    )
        return;
    refreshing = true;
    router.reload({
        only: ['auth'],
        onFinish: () => {
            refreshing = false;
        },
    });
}
onMounted(() => {
    window.addEventListener('focus', refreshVerification);
    document.addEventListener('visibilitychange', refreshVerification);
});
onUnmounted(() => {
    window.removeEventListener('focus', refreshVerification);
    document.removeEventListener('visibilitychange', refreshVerification);
});
async function resend() {
    busy.value = true;
    error.value = '';
    try {
        const result = await api<{ message: string }>(
            '/api/email/verification-notification',
            'POST',
        );
        notice.value = result.message;
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
async function logout() {
    busy.value = true;
    try {
        await api('/api/logout', 'POST');
        router.visit('/login');
    } catch (cause) {
        error.value = message(cause);
        busy.value = false;
    }
}
</script>
<template>
    <AuthShell
        :title="verified ? 'Email verified' : 'Verify your email'"
        :heading="verified ? 'EMAIL VERIFIED.' : 'VERIFY YOUR EMAIL.'"
    >
        <div class="verify-actions">
            <p v-if="verified">
                Your email is verified. Continue to your
                {{ coachPlan?.price_label }} monthly
                {{ coachPlan?.label.toLowerCase() }} coach membership.
            </p>
            <p v-else>
                Open the verification link sent to your email, then continue to
                your {{ coachPlan?.price_label }} monthly
                {{ coachPlan?.label.toLowerCase() }} coach membership.
            </p>
            <p v-if="error" class="notice" role="alert">{{ error }}</p>
            <p v-if="notice" class="notice" role="status">{{ notice }}</p>
            <button
                v-if="!verified"
                class="button"
                :disabled="busy"
                @click="resend"
            >
                Resend verification email
            </button>
            <Link v-if="verified" class="text-link" href="/payment"
                >Continue to billing</Link
            >
            <button class="text-link" :disabled="busy" @click="logout">
                Sign out
            </button>
        </div>
    </AuthShell>
</template>
<style scoped>
.verify-actions {
    display: grid;
    gap: 24px;
    line-height: 1.8;
}
</style>
