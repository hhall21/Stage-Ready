<script setup lang="ts">
import { ref, watch } from 'vue';
import { unveilContent } from '@/lib/motion';

const props = defineProps<{ open: boolean }>();
const panel = ref<HTMLElement | null>(null);
watch(
    () => props.open,
    (open) => {
        if (!panel.value) return;
        if (open) unveilContent(panel.value);
        else
            panel.value
                .getAnimations()
                .forEach((animation) => animation.cancel());
    },
    { flush: 'post' },
);
</script>

<template>
    <div
        ref="panel"
        v-show="open"
        class="slide-panel"
        :class="{ 'is-open': open }"
        :inert="!open"
        :aria-hidden="!open"
    >
        <div class="dropdown-content"><slot /></div>
    </div>
</template>
