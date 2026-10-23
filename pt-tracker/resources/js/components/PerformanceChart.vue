<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { ChartReading } from '@/lib/training-analytics';

const props = defineProps<{
    title: string;
    unit: string;
    xLabel: string;
    timeAxis: boolean;
    readings: ChartReading[];
    selectedId: number | null;
    colour: string;
}>();
const emit = defineEmits<{ select: [id: number] }>();
const container = ref<HTMLElement | null>(null);
const width = ref(560);
let observer: ResizeObserver | undefined;
onMounted(() => {
    if (!container.value) return;
    const resize = () => {
        width.value = Math.max(
            260,
            Math.round(container.value?.clientWidth ?? 560),
        );
    };
    resize();
    observer = new ResizeObserver(resize);
    observer.observe(container.value);
});
onBeforeUnmount(() => observer?.disconnect());

const valid = computed(() =>
    props.readings.filter(
        (reading): reading is ChartReading & { x: number; y: number } =>
            reading.x !== null &&
            reading.y !== null &&
            Number.isFinite(reading.x) &&
            Number.isFinite(reading.y),
    ),
);
function bounds(values: number[], time = false) {
    const low = Math.min(...values),
        high = Math.max(...values);
    const padding =
        high === low
            ? time
                ? 43_200_000
                : Math.max(Math.abs(low) * 0.05, 1)
            : (high - low) * 0.08;
    return {
        low: time ? low - padding : Math.max(0, low - padding),
        high: high + padding,
    };
}
const xScale = computed(() =>
    bounds(
        valid.value.map((reading) => reading.x),
        props.timeAxis,
    ),
);
const yScale = computed(() => bounds(valid.value.map((reading) => reading.y)));
const xAt = (value: number) =>
    54 +
    ((value - xScale.value.low) / (xScale.value.high - xScale.value.low)) *
        (width.value - 82);
const yAt = (value: number) =>
    222 -
    ((value - yScale.value.low) / (yScale.value.high - yScale.value.low)) * 192;
const coordinates = computed(() =>
    valid.value.map((reading) => ({
        ...reading,
        cx: xAt(reading.x),
        cy: yAt(reading.y),
    })),
);
const segments = computed(() => {
    const paths: string[] = [];
    let path = '';
    for (const reading of props.readings) {
        if (reading.x === null || reading.y === null) {
            if (path) paths.push(path);
            path = '';
        } else {
            path += `${path ? ' L ' : 'M '}${xAt(reading.x)} ${yAt(reading.y)}`;
        }
    }
    if (path) paths.push(path);
    return paths;
});
const ticks = [0, 0.5, 1];
const singleDate = computed(
    () =>
        props.timeAxis &&
        new Set(valid.value.map((reading) => reading.x)).size === 1,
);
const number = (value: number) =>
    new Intl.NumberFormat('en-GB', { maximumFractionDigits: 2 }).format(value);
function xTick(fraction: number) {
    const value =
        xScale.value.low + (xScale.value.high - xScale.value.low) * fraction;
    return props.timeAxis
        ? new Intl.DateTimeFormat('en-GB', {
              day: 'numeric',
              month: 'short',
              year:
                  xScale.value.high - xScale.value.low > 15_552_000_000
                      ? '2-digit'
                      : undefined,
              timeZone: 'UTC',
          }).format(new Date(value))
        : number(value);
}
</script>

<template>
    <article
        class="panel performance-chart"
        :style="{ '--chart-accent': colour }"
    >
        <div class="section-bar">
            <h3>{{ title }}</h3>
            <span class="eyebrow">{{ unit }}</span>
        </div>
        <p class="form-help">
            {{ valid.length }} plotted / {{ readings.length }} sessions
        </p>
        <div ref="container" class="performance-plot">
            <svg
                v-if="valid.length"
                :viewBox="`0 0 ${width} 264`"
                role="group"
                :aria-label="`${title} against ${xLabel}. Focus or select a point to inspect the session.`"
            >
                <g v-for="fraction in ticks" :key="fraction">
                    <line
                        x1="54"
                        :x2="width - 28"
                        :y1="222 - fraction * 192"
                        :y2="222 - fraction * 192"
                        stroke="#333"
                    />
                    <text x="44" :y="226 - fraction * 192" text-anchor="end">
                        {{
                            number(
                                yScale.low +
                                    (yScale.high - yScale.low) * fraction,
                            )
                        }}
                    </text>
                    <text
                        v-if="!singleDate || fraction === 0.5"
                        :x="54 + fraction * (width - 82)"
                        y="250"
                        :text-anchor="
                            fraction === 0
                                ? 'start'
                                : fraction === 1
                                  ? 'end'
                                  : 'middle'
                        "
                    >
                        {{ xTick(fraction) }}
                    </text>
                </g>
                <template v-if="timeAxis">
                    <path
                        v-for="(path, index) in segments"
                        :key="index"
                        :d="path"
                        fill="none"
                        stroke="var(--chart-accent)"
                        stroke-width="2"
                    />
                </template>
                <g v-for="reading in coordinates" :key="reading.id">
                    <circle
                        :cx="reading.cx"
                        :cy="reading.cy"
                        r="15"
                        fill="transparent"
                        tabindex="0"
                        role="button"
                        :aria-label="reading.description"
                        :aria-pressed="selectedId === reading.id"
                        @pointerenter="emit('select', reading.id)"
                        @focus="emit('select', reading.id)"
                        @click="emit('select', reading.id)"
                        @keydown.enter="emit('select', reading.id)"
                        @keydown.space.prevent="emit('select', reading.id)"
                    >
                        <title>{{ reading.description }}</title>
                    </circle>
                    <circle
                        :cx="reading.cx"
                        :cy="reading.cy"
                        :r="selectedId === reading.id ? 6 : 4"
                        fill="var(--chart-accent)"
                        :stroke="selectedId === reading.id ? 'white' : 'none'"
                        stroke-width="1.5"
                        pointer-events="none"
                    />
                </g>
            </svg>
            <p v-else class="analytics-empty">
                No matching readings for this comparison. Sessions need both a
                recorded {{ unit === 'kg' ? 'weight' : 'rep count' }} and
                {{ xLabel.toLowerCase() }}.
            </p>
        </div>
        <p class="performance-axis">{{ xLabel }}</p>
    </article>
</template>
