<script setup lang="ts">
defineProps<{ open: boolean }>();
</script>

<template>
    <div
        class="slide-panel"
        :class="{ 'is-open': open }"
        :inert="!open"
        :aria-hidden="!open"
    >
        <div class="slide-panel-inner"><slot /></div>
    </div>
</template>
