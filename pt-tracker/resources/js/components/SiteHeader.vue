<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
withDefaults(defineProps<{ dashboard?: boolean }>(), { dashboard: false });
</script>
<template>
    <header class="site-header">
        <Link href="/" class="brand" aria-label="Stage-Ready home"
            ><span class="brand-mark">SR</span
            ><span
                >STAGE-READY<span class="brand-sub"
                    >TRAINING. WITH PURPOSE.</span
                ></span
            ></Link
        >
        <nav aria-label="Main navigation">
            <template v-if="!dashboard"
                ><a href="/#approach">The approach</a
                ><Link class="button button-small" href="/login"
                    >Member login <span aria-hidden="true">↗</span></Link
                ></template
            >
            <slot v-else />
        </nav>
    </header>
</template>
