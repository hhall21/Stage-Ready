<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicPolicyLinks from '@/components/PublicPolicyLinks.vue';
import SiteHeader from '@/components/SiteHeader.vue';

defineProps<{ title: string; content: string }>();
</script>

<template>
    <Head :title="title" />
    <SiteHeader skip-target="#main-content" />
    <main id="main-content" class="legal-page" tabindex="-1">
        <Link href="/" class="text-link">Back to Stage-Ready</Link>
        <p class="eyebrow">STAGE-READY</p>
        <h1>{{ title }}</h1>
        <!-- The server renders owner-supplied Markdown with raw HTML and unsafe links disabled. -->
        <article class="legal-content" v-html="content" />
        <PublicPolicyLinks />
    </main>
</template>

<style scoped>
.legal-page {
    max-width: 900px;
    margin: 0 auto;
    padding: 40px 6vw 80px;
}
.legal-page > .eyebrow {
    margin-top: 40px;
}
.legal-page h1 {
    margin: 16px 0 40px;
    font-size: clamp(32px, 5vw, 48px);
    font-weight: 500;
    line-height: 1.2;
}
.legal-content {
    overflow-wrap: anywhere;
    line-height: 1.85;
}
.legal-content :deep(h1),
.legal-content :deep(h2),
.legal-content :deep(h3) {
    margin: 32px 0 16px;
    font-size: 24px;
    font-weight: 500;
    line-height: 1.4;
}
.legal-content :deep(p),
.legal-content :deep(ul),
.legal-content :deep(ol) {
    margin: 16px 0;
}
.legal-content :deep(ul),
.legal-content :deep(ol) {
    padding-left: 24px;
}
.legal-content :deep(ul) {
    list-style: disc;
}
.legal-content :deep(ol) {
    list-style: decimal;
}
.legal-content :deep(a) {
    text-decoration: underline;
    text-underline-offset: 4px;
}
.legal-page > :last-child {
    margin-top: 40px;
    padding-top: 24px;
    border-top: 1px solid var(--line);
}
</style>
