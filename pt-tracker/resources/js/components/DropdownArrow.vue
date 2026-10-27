<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = withDefaults(
    defineProps<{ expanded?: boolean; glint?: boolean }>(),
    { expanded: false, glint: false },
);
const arrow = ref<HTMLElement | null>(null);
const glinting = ref(false);
let observer: IntersectionObserver | undefined;

onMounted(() => {
    if (
        !props.glint ||
        !arrow.value ||
        typeof IntersectionObserver === 'undefined'
    )
        return;
    observer = new IntersectionObserver(
        ([entry]) => {
            glinting.value = Boolean(
                entry?.isIntersecting && entry.intersectionRatio >= 0.75,
            );
        },
        { threshold: 0.75 },
    );
    observer.observe(arrow.value);
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <span
        ref="arrow"
        class="dropdown-indicator"
        :class="{
            'has-steady-glow': !glint,
            'is-glinting': glinting,
            'is-expanded': expanded,
        }"
        aria-hidden="true"
    >
        <svg viewBox="0 0 20 20" fill="none">
            <path
                d="m7 5 5 5-5 5"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        </svg>
    </span>
</template>
