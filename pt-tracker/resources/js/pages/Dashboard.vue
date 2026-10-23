<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import CheckInSection from '@/components/CheckInSection.vue';
import ClientManagement from '@/components/ClientManagement.vue';
import CoachingReviews from '@/components/CoachingReviews.vue';
import ExerciseProgress from '@/components/ExerciseProgress.vue';
import JordanPetersQuote from '@/components/JordanPetersQuote.vue';
import NutritionPlans from '@/components/NutritionPlans.vue';
import OverviewSection from '@/components/OverviewSection.vue';
import PracticePlans from '@/components/PracticePlans.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import TrainingAnalytics from '@/components/TrainingAnalytics.vue';
import WeeklyHealth from '@/components/WeeklyHealth.vue';
import WorkoutsSection from '@/components/WorkoutsSection.vue';
import { jordanPetersQuotes } from '@/lib/jordan-peters-quotes';
import {
    allPages,
    api,
    ApiError,
    displayDate,
    localDate,
    message,
} from '@/lib/tracker-api';
import type { TrackerUser } from '@/lib/tracker-api';
const user = ref<TrackerUser | null>(null),
    clients = ref<TrackerUser[]>([]),
    selected = ref<number | null>(null);
type DashboardTab =
    | 'overview'
    | 'checkins'
    | 'workouts'
    | 'progress'
    | 'health'
    | 'nutrition'
    | 'practice'
    | 'reviews'
    | 'clients';
const tab = ref<DashboardTab>('overview');
const navigation = computed<
    { id: DashboardTab; label: string; number: string }[]
>(() => [
    { id: 'overview', label: 'Overview', number: '01' },
    { id: 'checkins', label: 'Daily check-ins', number: '02' },
    { id: 'workouts', label: 'Workouts', number: '03' },
    {
        id: 'progress',
        label:
            user.value?.role === 'coach'
                ? 'Training analytics'
                : 'Exercise progress',
        number: '04',
    },
    { id: 'health', label: 'Weekly health', number: '05' },
    { id: 'nutrition', label: 'Meal plans', number: '06' },
    { id: 'practice', label: 'Cardio and practice', number: '07' },
    { id: 'reviews', label: 'Coach reviews', number: '08' },
    ...(user.value?.role === 'coach'
        ? [{ id: 'clients' as const, label: 'Clients', number: '09' }]
        : []),
]);

const loading = ref(true),
    saving = ref(false),
    error = ref('');
const activeClient = computed(() =>
    user.value?.role === 'client'
        ? user.value
        : clients.value.find((client) => client.id === selected.value),
);
const heading = computed(() => {
    const coach = user.value?.role === 'coach';
    const headings: Record<DashboardTab, string> = {
        overview: coach ? 'Your coaching overview.' : 'Let’s get to work.',
        checkins: coach
            ? 'Review client check-ins.'
            : 'Check in. Move forward.',
        workouts: coach ? 'Plan and review training.' : 'Train with intent.',
        progress: coach ? 'Explore client performance.' : 'See your progress.',
        health: coach ? 'Monitor client health.' : 'Your health readings.',
        nutrition: coach ? 'Build client meal plans.' : 'Fuel your progress.',
        practice: coach
            ? 'Prescribe cardio and practice.'
            : 'Your daily prescriptions.',
        reviews: coach ? 'Review. Adjust. Progress.' : 'Your coach’s feedback.',
        clients: 'Your coaching roster.',
    };
    return headings[tab.value];
});
function handleError(cause: unknown) {
    error.value = message(cause);
    if (cause instanceof ApiError && cause.status === 401)
        router.visit('/login');
}
async function initialize() {
    loading.value = true;
    error.value = '';
    try {
        user.value = (await api<{ data: TrackerUser }>('/api/user')).data;
        if (user.value.role === 'coach')
            clients.value = await allPages<TrackerUser>('/api/clients');
        selected.value =
            user.value.role === 'client'
                ? user.value.id
                : (clients.value[0]?.id ?? null);
    } catch (cause) {
        handleError(cause);
    } finally {
        loading.value = false;
    }
}
async function refreshClients() {
    error.value = '';
    try {
        clients.value = await allPages<TrackerUser>('/api/clients');
        if (!clients.value.some((client) => client.id === selected.value))
            selected.value = clients.value[0]?.id ?? null;
    } catch (cause) {
        handleError(cause);
    }
}
function selectTab(next: DashboardTab) {
    tab.value = next;
}
async function logout() {
    saving.value = true;
    try {
        await api('/api/logout', 'POST');
        router.visit('/login');
    } catch (cause) {
        handleError(cause);
        saving.value = false;
    }
}
onMounted(initialize);
</script>
<template>
    <Head
        :title="
            user?.role === 'coach' ? 'Coach workspace' : 'Your training space'
        "
    />
    <SiteHeader dashboard
        ><span class="account-role">{{
            user?.role === 'coach' ? 'COACH SPACE' : 'MEMBER SPACE'
        }}</span
        ><button class="text-link" :disabled="saving" @click="logout">
            Sign out ↗
        </button></SiteHeader
    >
    <div class="dashboard-layout">
        <aside class="sidebar">
            <p class="eyebrow">YOUR WORKSPACE</p>
            <nav aria-label="Dashboard navigation">
                <button
                    v-for="item in navigation"
                    :key="item.id"
                    :data-section="item.id"
                    :class="{ active: tab === item.id }"
                    :aria-current="tab === item.id ? 'page' : undefined"
                    @click="selectTab(item.id)"
                >
                    <span>{{ item.number }}</span
                    >{{ item.label }}<span aria-hidden="true">↗</span>
                </button>
            </nav>
            <div class="sidebar-bottom">
                <p class="eyebrow">THE DAILY STANDARD</p>
                <JordanPetersQuote
                    :quote="jordanPetersQuotes.recovery"
                    variant="compact"
                />
            </div>
        </aside>
        <main class="dashboard-main" :data-section="tab">
            <div class="workspace-heading">
                <div>
                    <p class="eyebrow">
                        {{
                            user?.role === 'coach'
                                ? 'COACH DASHBOARD'
                                : 'YOUR DAILY STANDARD'
                        }}
                    </p>
                    <h1 :key="tab" class="workspace-title">{{ heading }}</h1>
                    <p>
                        {{
                            user
                                ? 'Welcome back, ' +
                                  user.name.split(' ')[0] +
                                  '.'
                                : 'Loading your training space…'
                        }}
                    </p>
                </div>
                <span class="today">{{ displayDate(localDate()) }}</span>
            </div>
            <p v-if="error" class="notice" role="alert">
                {{ error }}
                <button
                    class="text-link"
                    :disabled="loading"
                    @click="initialize"
                >
                    Retry
                </button>
            </p>
            <div
                v-if="user?.role === 'coach' && tab !== 'clients'"
                class="client-picker"
            >
                <label for="client-select">Assigned client</label
                ><select
                    id="client-select"
                    v-model="selected"
                    :disabled="loading || saving || !clients.length"
                >
                    <option v-if="!clients.length" :value="null">
                        No active clients assigned
                    </option>
                    <option
                        v-for="client in clients"
                        :key="client.id"
                        :value="client.id"
                    >
                        {{ client.name }}
                    </option></select
                ><span
                    >{{ clients.length }} active
                    {{ clients.length === 1 ? 'client' : 'clients' }}</span
                >
            </div>
            <ClientManagement
                data-section="clients"
                v-if="user?.role === 'coach' && tab === 'clients'"
                :clients="clients"
                @changed="refreshClients"
            />
            <p v-else-if="loading" class="loading-message" role="status">
                Loading your records…
            </p>
            <div v-else-if="!selected" class="empty-state">
                <h2>Your workspace is ready.</h2>
                <p>
                    {{
                        user?.role === 'coach'
                            ? 'Invite your first client to start coaching. Their access begins as soon as they join.'
                            : 'Sign in to access your training records.'
                    }}
                </p>
                <button
                    v-if="user?.role === 'coach'"
                    class="button button-small"
                    @click="selectTab('clients')"
                >
                    Manage clients ↗
                </button>
            </div>
            <template v-else-if="user && activeClient">
                <div :key="selected ?? 0">
                    <OverviewSection
                        v-if="tab === 'overview'"
                        :client="activeClient"
                        :user="user"
                        @open="selectTab"
                    />
                    <CheckInSection
                        v-else-if="tab === 'checkins'"
                        :client-id="activeClient.id"
                        :user="user"
                    />
                    <WorkoutsSection
                        v-else-if="tab === 'workouts'"
                        :client-id="activeClient.id"
                        :user="user"
                    />
                    <TrainingAnalytics
                        v-else-if="tab === 'progress' && user.role === 'coach'"
                        :client-id="activeClient.id"
                    />
                    <ExerciseProgress
                        v-else-if="tab === 'progress'"
                        :client-id="activeClient.id"
                    />
                    <WeeklyHealth
                        v-else-if="tab === 'health'"
                        :client-id="activeClient.id"
                        :is-coach="user.role === 'coach'"
                    />
                    <NutritionPlans
                        v-else-if="tab === 'nutrition'"
                        :client-id="activeClient.id"
                        :coach-id="user.role === 'coach' ? user.id : null"
                    />
                    <PracticePlans
                        v-else-if="tab === 'practice'"
                        :client-id="activeClient.id"
                        :coach-id="user.role === 'coach' ? user.id : null"
                    />
                    <CoachingReviews
                        v-else-if="tab === 'reviews'"
                        :client-id="activeClient.id"
                        :coach-id="user.role === 'coach' ? user.id : null"
                    /></div
            ></template>
        </main>
    </div>
</template>
