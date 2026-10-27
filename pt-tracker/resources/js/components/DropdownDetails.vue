<script setup lang="ts">
import { onMounted, ref } from 'vue';
import DropdownArrow from '@/components/DropdownArrow.vue';
import { unveilContent } from '@/lib/motion';

defineProps<{ contentClass?: string; glint?: boolean }>();
const details = ref<HTMLDetailsElement | null>(null);
const content = ref<HTMLElement | null>(null);
const expanded = ref(false);

onMounted(() => {
    expanded.value = details.value?.open ?? false;
});
function toggle() {
    expanded.value = details.value?.open ?? false;
    if (!content.value) return;
    if (expanded.value) unveilContent(content.value);
    else
        content.value
            .getAnimations()
            .forEach((animation) => animation.cancel());
}
</script>

<template>
    <details ref="details" class="dropdown-details" @toggle="toggle">
        <summary class="dropdown-summary">
            <DropdownArrow :expanded="expanded" :glint="glint" />
            <span class="dropdown-label"><slot name="summary" /></span>
        </summary>
        <div ref="content" class="dropdown-reveal">
            <div class="dropdown-content" :class="contentClass"><slot /></div>
        </div>
    </details>
</template>
