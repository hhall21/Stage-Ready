<script setup lang="ts" generic="T">
import { ref, useAttrs } from 'vue';
import DropdownArrow from '@/components/DropdownArrow.vue';

defineOptions({ inheritAttrs: false });
const model = defineModel<T>();
const attrs = useAttrs();
const focused = ref(false);
</script>

<template>
    <span class="dropdown-select">
        <select
            v-model="model"
            v-bind="attrs"
            @focus="focused = true"
            @blur="focused = false"
        >
            <slot />
        </select>
        <DropdownArrow :expanded="focused" />
    </span>
</template>
