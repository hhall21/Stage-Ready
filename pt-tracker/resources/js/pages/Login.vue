<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AthleteVisual from '@/components/AthleteVisual.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { athleteImages } from '@/lib/athlete-images';
import { api, message } from '@/lib/tracker-api';
const props = withDefaults(
    defineProps<{ next?: string; passwordReset?: boolean }>(),
    { next: '/dashboard', passwordReset: false },
);
const email = ref('');
const password = ref('');
const busy = ref(false);
const error = ref('');
async function login() {
    busy.value = true;
    error.value = '';
    try {
        await api('/api/login', 'POST', {
            email: email.value,
            password: password.value,
        });
        password.value = '';
        router.visit(props.next);
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <Head title="Member login" />
    <SiteHeader skip-target="#main-content" />
    <main id="main-content" class="login-layout" tabindex="-1">
        <section class="login-copy" data-section="overview">
            <p class="eyebrow">STAGE-READY</p>
            <h1 class="sign-in-title">Sign in to your workspace</h1>
            <p class="sign-in-intro">
                Access your training plans, check-ins and coaching records.
            </p>
            <form class="login-form" @submit.prevent="login">
                <p v-if="passwordReset" class="notice" role="status">
                    Password updated. Sign in with your new password.
                </p>
                <p v-if="error" class="notice" role="alert">{{ error }}</p>
                <label
                    >Email address<input
                        v-model="email"
                        type="email"
                        autocomplete="username"
                        required
                        placeholder="you@example.com"
                /></label>
                <label
                    >Password<input
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        placeholder="Enter your password"
                /></label>
                <button class="button" :disabled="busy">
                    {{ busy ? 'Signing in…' : 'Sign in' }}
                </button>
                <p class="form-help">
                    <Link href="/forgot-password" class="text-link"
                        >Forgot your password?</Link
                    >
                </p>
                <p class="form-help">
                    Clients: use the invitation from your coach to join.
                </p>
            </form>
        </section>
        <AthleteVisual :src="athleteImages.login" />
    </main>
</template>

<style scoped>
.login-copy .sign-in-title {
    max-width: 14ch;
    margin-block: 24px;
    font-size: clamp(36px, 4vw, 56px);
    font-weight: 600;
    line-height: 1.1;
    letter-spacing: -0.035em;
}
.sign-in-intro {
    max-width: 36ch;
    font-size: 16px;
    line-height: 1.75;
}
</style>
