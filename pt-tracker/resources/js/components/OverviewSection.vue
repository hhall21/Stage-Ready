<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import BodyweightTrend from '@/components/BodyweightTrend.vue';
import CoachingReviews from '@/components/CoachingReviews.vue';
import TrainingDayPanel from '@/components/TrainingDayPanel.vue';
import WeeklyHealth from '@/components/WeeklyHealth.vue';
import { api, displayDate, message } from '@/lib/tracker-api';
import type {
    CheckIn,
    Page,
    TrackerUser,
    Workout,
    WeightPoint,
} from '@/lib/tracker-api';
const props = defineProps<{ client: TrackerUser; user: TrackerUser }>();
const emit = defineEmits<{
    open: [section: 'checkins' | 'workouts' | 'health' | 'reviews'];
}>();
const client = props.client,
    user = props.user,
    selected = client.id;
const canCheckIn = computed(() => user.role === 'client');
const posingEnabled = ref(false);
const checkins = ref<CheckIn[]>([]),
    workouts = ref<Workout[]>([]),
    weightPoints = ref<WeightPoint[]>([]);
const checkTotal = ref(0),
    workoutTotal = ref(0),
    loading = ref(true),
    error = ref('');
const latest = computed(() =>
    checkins.value.find((record) => record.bodyweight !== null),
);
let version = 0;
async function loadRecords() {
    const request = ++version;
    loading.value = true;
    error.value = '';
    try {
        const [checks, training, trend] = await Promise.all([
            api<Page<CheckIn> & { posing_enabled: boolean }>(
                '/api/clients/' + client.id + '/daily-check-ins',
            ),
            api<Page<Workout>>('/api/clients/' + client.id + '/workouts'),
            api<{ data: WeightPoint[] }>(
                '/api/clients/' + client.id + '/bodyweight-trend',
            ),
        ]);
        if (request !== version) return;
        posingEnabled.value = checks.posing_enabled;
        checkins.value = checks.data;
        workouts.value = training.data;
        weightPoints.value = trend.data;
        checkTotal.value = checks.total;
        workoutTotal.value = training.total;
    } catch (cause) {
        if (request === version) error.value = message(cause);
    } finally {
        if (request === version) loading.value = false;
    }
}
onMounted(loadRecords);
onBeforeUnmount(() => version++);
</script>
<template>
    <TrainingDayPanel
        data-section="workouts"
        v-if="selected"
        :key="'today-' + selected"
        :client-id="selected"
        :can-log="canCheckIn"
        @changed="loadRecords"
    />
    <CoachingReviews
        data-section="reviews"
        :client-id="client.id"
        :coach-id="user.role === 'coach' ? user.id : null"
        compact
        @open="emit('open', 'reviews')"
    /><WeeklyHealth
        data-section="health"
        :client-id="client.id"
        :is-coach="user.role === 'coach'"
        compact
        @open="emit('open', 'health')"
    />
    <section aria-label="Client overview" :aria-busy="loading">
        <p v-if="error" class="notice" role="alert">
            {{ error }}
            <button class="text-link" :disabled="loading" @click="loadRecords">
                Retry
            </button>
        </p>
        <p v-else-if="loading" class="loading-message" role="status">
            Loading records…
        </p>
        <template v-else>
            <div class="overview-action">
                <div>
                    <h2>
                        {{
                            canCheckIn
                                ? 'Your daily check-in'
                                : 'Client training'
                        }}
                    </h2>
                    <p>
                        {{
                            canCheckIn
                                ? 'Record today’s measurements and notes. Every field is optional.'
                                : 'Review prescribed sessions and update upcoming training.'
                        }}
                    </p>
                </div>
                <button
                    class="button button-secondary"
                    @click="emit('open', canCheckIn ? 'checkins' : 'workouts')"
                >
                    {{
                        canCheckIn
                            ? posingEnabled
                                ? 'Open check-in and poses'
                                : 'Record check-in'
                            : 'Plan client training'
                    }}
                </button>
            </div>
            <BodyweightTrend data-section="progress" :points="weightPoints" />
            <dl class="overview-records">
                <div>
                    <dt>Latest bodyweight</dt>
                    <dd>
                        {{ latest?.bodyweight ?? 'No reading'
                        }}<small
                            v-if="
                                latest?.bodyweight !== null &&
                                latest?.bodyweight !== undefined
                            "
                        >
                            kg</small
                        >
                    </dd>
                    <dd class="record-context">
                        {{
                            latest
                                ? displayDate(latest.check_in_date)
                                : 'Record a weigh-in in a daily check-in'
                        }}
                    </dd>
                </div>
                <div>
                    <dt>Training sessions</dt>
                    <dd>{{ workoutTotal }}</dd>
                    <dd class="record-context">
                        All sessions in the training log
                    </dd>
                </div>
                <div>
                    <dt>Daily check-ins</dt>
                    <dd>{{ checkTotal }}</dd>
                    <dd class="record-context">
                        All daily entries, including drafts
                    </dd>
                </div>
            </dl>
            <div class="section-bar" data-section="workouts">
                <h2>Recent training</h2>
                <button class="text-link" @click="emit('open', 'workouts')">
                    View workouts
                </button>
            </div>
            <div v-if="!workouts.length" class="empty-state">
                <p>
                    {{
                        canCheckIn
                            ? 'No workouts yet. Your coach’s prescribed sessions will appear here.'
                            : 'No workouts yet. Prescribe the client’s first session in Workouts.'
                    }}
                </p>
            </div>
            <article
                v-for="workout in workouts.slice(0, 3)"
                :key="workout.id"
                class="workout-summary"
            >
                <div>
                    <p class="eyebrow">
                        {{ displayDate(workout.workout_date) }}
                    </p>
                    <h3>{{ workout.workout_name }}</h3>
                    <p>{{ workout.workout_exercises.length }} exercises</p>
                </div>
                <span class="status-pill">{{
                    workout.completed ? 'Completed' : 'Planned'
                }}</span>
            </article>
        </template>
    </section>
</template>

<style scoped>
.overview-action {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 24px;
    margin: 32px 0;
    padding-block: 24px;
    border-top: 1px solid var(--line);
    border-bottom: 1px solid var(--line);
}
.overview-action h2 {
    margin: 0 0 8px;
    font-size: 21px;
    font-weight: 500;
}
.overview-action p {
    max-width: 52ch;
    margin: 0;
    font-size: 16px;
    line-height: 1.7;
}
.overview-records {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 24px;
    margin: 32px 0;
}
.overview-records > div {
    min-width: 0;
}
.overview-records dt,
.overview-records .record-context {
    font-size: 15px;
    line-height: 1.7;
}
.overview-records dd {
    margin: 8px 0 0;
    font-size: 28px;
    font-weight: 500;
}
.overview-records small {
    font-size: 14px;
}
@media (max-width: 720px) {
    .overview-records {
        grid-template-columns: 1fr;
    }
    .overview-records > div + div {
        padding-top: 16px;
        border-top: 1px solid var(--line);
    }
}
</style>
