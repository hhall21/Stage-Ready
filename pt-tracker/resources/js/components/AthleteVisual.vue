<script setup lang="ts">
import { ref, watch } from 'vue';
const props = defineProps<{ src?: string | null; compact?: boolean }>();
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
