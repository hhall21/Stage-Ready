<script setup lang="ts">
import equipmentMarkup from '../../artwork/training-equipment.svg?raw';
</script>

<template>
    <div class="athlete-visual dumbbell-visual" aria-hidden="true">
        <!-- Only the bundled SVG source is inserted here, never user content. -->
        <div class="dumbbell-artwork" v-html="equipmentMarkup"></div>
    </div>
</template>

<style scoped>
.dumbbell-visual {
    --dumbbell-progress: 0;
    pointer-events: none;
}
.dumbbell-artwork {
    position: absolute;
    inset: 0;
    mix-blend-mode: lighten;
}
.dumbbell-artwork :deep(svg) {
    display: block;
    width: 100%;
    height: 100%;
}
.dumbbell-artwork :deep(.dumbbell-plate) {
    transform-box: fill-box;
    transform-origin: center;
    transform: translate(
            calc(
                var(--dumbbell-progress) * var(--plate-direction) *
                    var(--plate-distance)
            ),
            calc(var(--dumbbell-progress) * var(--plate-lift))
        )
        rotate(
            calc(
                var(--dumbbell-progress) * var(--plate-direction) *
                    var(--plate-turn)
            )
        );
}
.dumbbell-artwork :deep(.dumbbell-plate-left) {
    --plate-direction: -1;
}
.dumbbell-artwork :deep(.dumbbell-plate-right) {
    --plate-direction: 1;
}
.dumbbell-artwork :deep(.dumbbell-plate-inner) {
    --plate-distance: 60px;
    --plate-lift: -20px;
    --plate-turn: 4deg;
}
.dumbbell-artwork :deep(.dumbbell-plate-middle) {
    --plate-distance: 100px;
    --plate-lift: -38px;
    --plate-turn: 7deg;
}
.dumbbell-artwork :deep(.dumbbell-plate-outer) {
    --plate-distance: 140px;
    --plate-lift: -56px;
    --plate-turn: 10deg;
}
@media (prefers-reduced-motion: reduce) {
    .dumbbell-artwork :deep(.dumbbell-plate) {
        transform: none;
    }
}
</style>
