<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import CheckInSection from '@/components/CheckInSection.vue';
import ClientManagement from '@/components/ClientManagement.vue';
import CoachingReviews from '@/components/CoachingReviews.vue';
import CoachingHandovers from '@/components/CoachingHandovers.vue';
import CoachDiscipline from '@/components/CoachDiscipline.vue';
import CompetitionsSection from '@/components/CompetitionsSection.vue';
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
    | 'handover'
    | 'clients'
    | 'competitions';
const tab = ref<DashboardTab>('overview');
const navigation = computed<{ id: DashboardTab; label: string }[]>(() => [
    { id: 'overview', label: 'Overview' },
    { id: 'checkins', label: 'Daily check-ins' },
    { id: 'workouts', label: 'Workouts' },
    {
        id: 'progress',
        label:
            user.value?.role === 'coach'
                ? 'Training analytics'
                : 'Exercise progress',
    },
    { id: 'health', label: 'Weekly health' },
    { id: 'nutrition', label: 'Meal plans' },
    { id: 'practice', label: 'Cardio and practice' },
    { id: 'reviews', label: 'Coach reviews' },
    ...(user.value?.role === 'client'
        ? [{ id: 'handover' as const, label: 'Coaching handovers' }]
        : []),
    ...(user.value?.role === 'coach'
        ? [
              { id: 'clients' as const, label: 'Clients' },
              {
                  id: 'competitions' as const,
                  label: 'Competitions',
              },
          ]
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
        overview: coach ? 'Client overview' : 'Your daily overview',
        checkins: coach ? 'Client check-ins' : 'Your daily check-in',
        workouts: coach ? 'Training plans and logs' : 'Your workouts',
        progress: coach ? 'Training analytics' : 'Your exercise progress',
        health: coach ? 'Client health readings' : 'Your health readings',
        nutrition: coach ? 'Client meal plans' : 'Your meal plans',
        practice: 'Cardio and practice',
        reviews: coach ? 'Client reviews' : 'Your coach’s feedback',
        handover: 'Your coaching handovers',
        clients: 'Your clients',
        competitions: 'Client competitions',
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
        if (
            user.value.role === 'client' &&
            !user.value.has_active_coaching &&
            user.value.has_coaching_handovers
        )
            tab.value = 'handover';
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
    clients.value = await allPages<TrackerUser>('/api/clients');
    if (!clients.value.some((client) => client.id === selected.value))
        selected.value = clients.value[0]?.id ?? null;
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
    <SiteHeader dashboard skip-target="#main-content"
        ><span class="account-role">{{
            user?.role === 'coach' ? 'COACH SPACE' : 'MEMBER SPACE'
        }}</span
        ><button class="text-link" :disabled="saving" @click="logout">
            {{ saving ? 'Signing out…' : 'Sign out' }}
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
                    {{ item.label }}
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
        <main
            id="main-content"
            class="dashboard-main"
            :data-section="tab"
            tabindex="-1"
        >
            <div class="workspace-heading">
                <div>
                    <p class="eyebrow">
                        {{
                            user?.role === 'coach'
                                ? 'COACH DASHBOARD'
                                : 'CLIENT DASHBOARD'
                        }}
                    </p>
                    <h1 :key="tab" class="workspace-title" aria-live="polite">
                        {{ heading }}
                    </h1>
                    <p>
                        {{
                            user
                                ? 'Welcome back, ' +
                                  user.name.split(' ')[0] +
                                  '.'
                                : 'Loading your workspace…'
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
                v-if="
                    user?.role === 'coach' &&
                    tab !== 'clients' &&
                    tab !== 'competitions'
                "
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
            <template v-if="user?.role === 'coach' && tab === 'clients'">
                <CoachDiscipline :user="user" />
                <ClientManagement
                    data-section="clients"
                    :clients="clients"
                    :refresh-clients="refreshClients"
                />
            </template>
            <p v-else-if="loading" class="loading-message" role="status">
                Loading your records…
            </p>
            <CompetitionsSection
                v-else-if="user?.role === 'coach' && tab === 'competitions'"
                :clients="clients"
            />
            <CoachingHandovers
                v-else-if="user?.role === 'client' && tab === 'handover'"
            />
            <div v-else-if="!selected" class="empty-state">
                <h2>
                    {{
                        user?.role === 'coach'
                            ? 'No clients yet'
                            : 'No client selected'
                    }}
                </h2>
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
                    Manage clients
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
