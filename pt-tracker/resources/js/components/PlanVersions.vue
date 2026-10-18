<script setup lang="ts">
import { displayDate, localDate } from '@/lib/tracker-api';
import type { Page } from '@/lib/tracker-api';
import type { PlanVersion } from '@/lib/coaching';
defineProps<{
    versions: Page<PlanVersion>;
    coachId: number | null;
    busy: boolean;
}>();
defineEmits<{
    copy: [id: number];
    cancel: [id: number];
    page: [page: number];
}>();
</script>
<template>
    <details v-if="versions.total" class="panel">
        <summary>
            Scheduled plans and version history ({{ versions.total }})
        </summary>
        <p class="form-help">
            Plans take effect on their start date. When versions share a start
            date, the most recently published version applies.
        </p>
        <article
            v-for="version in versions.data"
            :key="version.id"
            class="workout-summary"
        >
            <div>
                <strong>From {{ displayDate(version.effective_from) }}</strong>
                <p class="form-help">
                    Version {{ version.id }} ·
                    {{
                        version.cancelled_at
                            ? 'Cancelled'
                            : version.effective_from.slice(0, 10) > localDate()
                              ? 'Scheduled'
                              : 'Published'
                    }}
                </p>
            </div>
            <div class="row-actions">
                <button
                    class="text-link"
                    :disabled="busy"
                    @click="$emit('copy', version.id)"
                >
                    {{ coachId ? 'Use as starting point' : 'View plan' }}
                </button>
                <button
                    v-if="
                        coachId !== null &&
                        coachId === version.coach_id &&
                        !version.cancelled_at &&
                        version.effective_from.slice(0, 10) > localDate()
                    "
                    class="text-link"
                    :disabled="busy"
                    @click="$emit('cancel', version.id)"
                >
                    Cancel scheduled plan
                </button>
            </div>
        </article>
        <div v-if="versions.last_page > 1" class="pagination">
            <button
                :disabled="busy || versions.current_page <= 1"
                @click="$emit('page', versions.current_page - 1)"
            >
                Previous
            </button>
            <span>{{ versions.current_page }} / {{ versions.last_page }}</span>
            <button
                :disabled="busy || versions.current_page >= versions.last_page"
                @click="$emit('page', versions.current_page + 1)"
            >
                Next
            </button>
        </div>
    </details>
</template>
