<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AthleteVisual from '@/components/AthleteVisual.vue';
import JordanPetersQuote from '@/components/JordanPetersQuote.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { athleteImages } from '@/lib/athlete-images';
import { jordanPetersQuotes } from '@/lib/jordan-peters-quotes';
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
    <SiteHeader />
    <main class="login-layout">
        <section class="login-copy">
            <p class="eyebrow">YOUR TRAINING SPACE</p>
            <h1>WELCOME<br /><span class="outline-text">BACK.</span></h1>
            <JordanPetersQuote :quote="jordanPetersQuotes.patience" />
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
                    <span aria-hidden="true">↗</span>
                </button>
                <p class="form-help">
                    <Link href="/forgot-password" class="text-link"
                        >Forgot your password?</Link
                    >
                </p>
                <p class="form-help">
                    Need access or help with your account? Contact your coach.
                </p>
            </form>
        </section>
        <AthleteVisual :src="athleteImages.login" />
    </main>
</template>
