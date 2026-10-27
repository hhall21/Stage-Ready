<script setup lang="ts">
import { ref, watch } from 'vue';
const props = defineProps<{
    src?: string | null;
    compact?: boolean;
    split?: boolean;
}>();
const failed = ref(false);
watch(
    () => props.src,
    () => {
        failed.value = false;
    },
);
</script>
<template>
    <div class="athlete-visual" :class="{ compact }" aria-hidden="true">
        <span v-if="!src || failed" class="athlete-word">DISCIPLINE</span>
        <img
            v-if="src && !failed"
            :src="src"
            :class="{ 'athlete-split-left': split }"
            alt=""
            decoding="async"
            @error="failed = true"
        />
        <img
            v-if="src && !failed && split"
            :src="src"
            class="athlete-split-right"
            alt=""
            decoding="async"
            @error="failed = true"
        />
        <div class="athlete-fade"></div>
        <div class="athlete-caption">
            <span>BUILT DAY BY DAY</span><span>01 / THE PROCESS</span>
        </div>
    </div>
</template>

<style scoped>
.athlete-split-left {
    clip-path: inset(0 50% 0 0);
    transform: translateY(calc(-1 * var(--hero-split-offset, 0px)));
}
.athlete-split-right {
    clip-path: inset(0 0 0 50%);
    transform: translateY(var(--hero-split-offset, 0px));
}
@media (prefers-reduced-motion: reduce) {
    .athlete-split-left,
    .athlete-split-right {
        transform: none;
    }
}
</style>
