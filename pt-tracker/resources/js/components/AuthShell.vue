<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AthleteVisual from '@/components/AthleteVisual.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { athleteImages } from '@/lib/athlete-images';
defineProps<{ title: string; heading: string }>();
</script>
<template>
    <Head :title="title"><meta name="referrer" content="no-referrer" /></Head>
    <SiteHeader />
    <main class="login-layout">
        <section class="login-copy" data-section="overview">
            <p class="eyebrow">YOUR STAGE-READY WORKSPACE</p>
            <h1 class="auth-title">{{ heading }}</h1>
            <div class="login-form"><slot /></div>
            <p class="form-help auth-back">
                <Link href="/login" class="text-link">Back to sign in ↗</Link>
            </p>
        </section>
        <AthleteVisual :src="athleteImages.login" />
    </main>
</template>
<style scoped>
.auth-title {
    font-size: clamp(40px, 5vw, 72px);
    white-space: pre-line;
}
.auth-back {
    margin-top: 25px;
}
</style>
