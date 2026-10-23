<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AthleteVisual from '@/components/AthleteVisual.vue';
import BodyweightTrend from '@/components/BodyweightTrend.vue';
import CoachingReviews from '@/components/CoachingReviews.vue';
import JordanPetersQuote from '@/components/JordanPetersQuote.vue';
import TrainingDayPanel from '@/components/TrainingDayPanel.vue';
import WeeklyHealth from '@/components/WeeklyHealth.vue';
import { athleteImages } from '@/lib/athlete-images';
import { jordanPetersQuotes } from '@/lib/jordan-peters-quotes';
import { vReveal } from '@/lib/motion';
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
            api<Page<CheckIn>>(
                '/api/clients/' + client.id + '/daily-check-ins',
            ),
            api<Page<Workout>>('/api/clients/' + client.id + '/workouts'),
            api<{ data: WeightPoint[] }>(
                '/api/clients/' + client.id + '/bodyweight-trend',
            ),
        ]);
        if (request !== version) return;
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
    <section aria-label="Client overview">
        <p v-if="error" class="notice" role="alert">
            {{ error }}
            <button class="text-link" :disabled="loading" @click="loadRecords">
                Retry
            </button>
        </p>
        <p v-if="loading" class="loading-message" role="status">
            Loading records…
        </p>
        <TrainingDayPanel
            data-section="workouts"
            ref="trainingDayPanel"
            v-if="selected"
            :key="'today-' + selected"
            :client-id="selected"
            :can-log="canCheckIn"
            @changed="loadRecords"
        />
        <div class="dashboard-banner">
            <div>
                <p class="eyebrow">{{ client.name }} / THE NEXT STEP</p>
                <JordanPetersQuote
                    :quote="jordanPetersQuotes.progress"
                    variant="display"
                />
                <button
                    class="button"
                    @click="emit('open', canCheckIn ? 'checkins' : 'workouts')"
                >
                    {{
                        canCheckIn
                            ? 'Upload your daily poses'
                            : 'Plan client training'
                    }}
                    ↗
                </button>
            </div>
            <AthleteVisual compact :src="athleteImages.dashboard" />
        </div>
        <BodyweightTrend data-section="progress" :points="weightPoints" />
        <div class="stats-grid">
            <article v-reveal data-section="checkins">
                <p class="eyebrow">POSING CHECK-INS</p>
                <strong>{{ checkTotal }}</strong
                ><span>Daily entries, including drafts</span>
            </article>
            <article v-reveal data-section="workouts">
                <p class="eyebrow">WORKOUTS</p>
                <strong>{{ workoutTotal }}</strong
                ><span>{{
                    canCheckIn
                        ? 'Sessions in your training log'
                        : 'Sessions in the client training log'
                }}</span>
            </article>
            <article v-reveal data-section="progress">
                <p class="eyebrow">LATEST BODYWEIGHT</p>
                <strong
                    >{{ latest?.bodyweight ?? '—'
                    }}<small v-if="latest?.bodyweight"> kg</small></strong
                ><span>{{
                    latest
                        ? displayDate(latest.check_in_date)
                        : 'Add a weigh-in'
                }}</span>
            </article>
        </div>
        <div class="section-bar" data-section="workouts">
            <h2>Scheduled training</h2>
            <button class="text-link" @click="emit('open', 'workouts')">
                View workouts ↗
            </button>
        </div>
        <div v-if="!workouts.length" class="empty-state">
            <p>
                {{
                    canCheckIn
                        ? 'No workouts yet. Your next session starts here.'
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
    </section>
</template>
