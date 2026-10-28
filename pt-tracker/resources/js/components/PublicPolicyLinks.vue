<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const business = computed(() => page.props.business);
</script>

<template>
    <nav
        v-if="
            business.support_email || business.privacy_url || business.terms_url
        "
        class="policy-links"
        aria-label="Policies and support"
    >
        <a v-if="business.privacy_url" :href="business.privacy_url">Privacy</a>
        <a v-if="business.terms_url" :href="business.terms_url"
            >Terms and cancellation</a
        >
        <a
            v-if="business.support_email"
            :href="`mailto:${business.support_email}`"
            >Contact support</a
        >
    </nav>
</template>

<style scoped>
.policy-links {
    display: flex;
    flex-wrap: wrap;
    gap: 12px 24px;
    color: #bdbdbd;
    font-size: 13px;
    line-height: 1.6;
}
.policy-links a {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    text-decoration: underline;
    text-underline-offset: 4px;
}
.policy-links a:hover {
    color: #fff;
}
</style>
