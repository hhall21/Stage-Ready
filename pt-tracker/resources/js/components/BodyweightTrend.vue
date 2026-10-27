<script setup lang="ts">
import DropdownDetails from '@/components/DropdownDetails.vue';
import { computed } from 'vue';
import { displayDate } from '@/lib/tracker-api';
import type { WeightPoint } from '@/lib/tracker-api';
const props = defineProps<{ points: WeightPoint[] }>();
const current = computed(() => props.points.at(-1));
const observed = computed(() =>
    props.points.filter((point) => point.average_kg !== null),
);
const range = computed(() => {
    const values = observed.value.map((point) => point.average_kg as number);
    const min = Math.min(...values),
        max = Math.max(...values);
    return { min: min - 0.25, max: max + 0.25 };
});
const segments = computed(() => {
    const paths: string[] = [];
    let path = '';
    props.points.forEach((point, index) => {
        if (point.average_kg === null) {
            if (path) paths.push(path);
            path = '';
            return;
        }
        const x = 35 + (index / Math.max(1, props.points.length - 1)) * 630;
        const y =
            160 -
            ((point.average_kg - range.value.min) /
                (range.value.max - range.value.min)) *
                125;
        path += (path ? ' L ' : 'M ') + x.toFixed(2) + ' ' + y.toFixed(2);
    });
    if (path) paths.push(path);
    return paths;
});
const delta = computed(() => current.value?.change_from_7_days_ago_kg);
const trend = computed(() =>
    delta.value === null || delta.value === undefined
        ? 'More readings needed for comparison'
        : delta.value < 0
          ? 'Down from seven days ago'
          : delta.value > 0
            ? 'Up from seven days ago'
            : 'Unchanged from seven days ago',
);
</script>
<template>
    <section class="panel weight-trend">
        <div class="section-bar">
            <h2>Bodyweight trend</h2>
            <span class="eyebrow">ROLLING 7-DAY AVERAGE</span>
        </div>
        <div class="trend-stats">
            <div>
                <strong
                    >{{ current?.average_kg?.toFixed(2) ?? '—' }}
                    <small>kg</small></strong
                >
                <p>
                    {{ current?.readings ?? 0 }} recorded weigh-ins in the last
                    7 days
                </p>
            </div>
            <div>
                <strong
                    >{{
                        delta === null || delta === undefined
                            ? '—'
                            : (delta > 0 ? '+' : '') + delta.toFixed(2)
                    }}
                    <small v-if="delta !== null && delta !== undefined"
                        >kg</small
                    ></strong
                >
                <p>{{ trend }}</p>
            </div>
        </div>
        <svg
            v-if="observed.length"
            class="trend-chart"
            viewBox="0 0 700 205"
            role="img"
            :aria-label="
                'Rolling seven-day bodyweight trend. Current average: ' +
                (current?.average_kg ?? 'no readings') +
                ' kilograms. ' +
                trend
            "
        >
            <path
                d="M35 160H665M35 97H665M35 35H665"
                fill="none"
                stroke="#292929"
            />
            <path
                v-for="(path, index) in segments"
                :key="index"
                :d="path"
                stroke="white"
                stroke-width="2.5"
                fill="none"
            />
            <text x="35" y="192" fill="white" font-size="11">
                {{ displayDate(points[0].date) }}
            </text>
            <text x="665" y="192" fill="white" text-anchor="end" font-size="11">
                {{ displayDate(points[points.length - 1].date) }}
            </text>
            <text x="35" y="20" fill="white" font-size="10">
                {{ range.max.toFixed(1) }} kg
            </text>
            <text x="35" y="176" fill="white" font-size="10">
                {{ range.min.toFixed(1) }} kg
            </text>
        </svg>
        <p v-else class="form-help">
            Recorded bodyweights will appear here. Add a bodyweight to a daily
            check-in to start the trend.
        </p>
        <p class="form-help">
            Each point averages the recorded weigh-ins from that day and the six
            days before it. Missing weigh-ins are excluded. Gaps show periods
            with no readings.
        </p>
        <DropdownDetails v-if="observed.length" class="trend-history">
            <template #summary>View daily averages and reading counts</template>
            <div class="trend-table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>7-day average</th>
                            <th>Readings</th>
                            <th>Change vs 7 days earlier</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="point in [...points].reverse()"
                            :key="point.date"
                        >
                            <td>{{ displayDate(point.date) }}</td>
                            <td>{{ point.average_kg?.toFixed(2) ?? '—' }}</td>
                            <td>{{ point.readings }}</td>
                            <td>
                                {{
                                    point.change_from_7_days_ago_kg?.toFixed(
                                        2,
                                    ) ?? '—'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </DropdownDetails>
    </section>
</template>
