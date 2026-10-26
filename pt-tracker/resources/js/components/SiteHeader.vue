<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
withDefaults(defineProps<{ dashboard?: boolean }>(), { dashboard: false });
const page = usePage();
</script>
<template>
    <header class="site-header" :class="{ 'signed-in': page.props.auth.user }">
        <Link href="/" class="brand" aria-label="Stage-Ready home"
            ><span class="brand-mark">SR</span
            ><span
                >STAGE-READY<span class="brand-sub"
                    >TRAINING. WITH PURPOSE.</span
                ></span
            ></Link
        >
        <nav aria-label="Main navigation">
            <span v-if="page.props.auth.user" class="header-user">
                <span class="sr-only">Signed in as </span>
                {{ page.props.auth.user.name }}
            </span>
            <template v-if="!dashboard"
                ><a href="/#features">Features</a
                ><a href="/#how-it-works">How it works</a
                ><Link class="button button-small" href="/login"
                    >Member login <span aria-hidden="true">↗</span></Link
                ></template
            >
            <slot v-else />
        </nav>
    </header>
</template>

<style scoped>
.header-user {
    max-width: 28ch;
    overflow-wrap: anywhere;
    text-align: right;
    font-weight: 500;
    line-height: 1.5;
}
.signed-in nav {
    flex-wrap: wrap;
    justify-content: flex-end;
    min-width: 0;
}
@media (max-width: 720px) {
    .signed-in {
        flex-wrap: wrap;
        row-gap: 16px;
    }
    .signed-in nav {
        flex: 1 1 100%;
    }
    .header-user {
        max-width: min(28ch, 100%);
    }
}
</style>
