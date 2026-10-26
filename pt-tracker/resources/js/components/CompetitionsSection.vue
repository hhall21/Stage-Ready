<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { api, displayDate, message } from '@/lib/tracker-api';
import type { Page, TrackerUser } from '@/lib/tracker-api';

const props = defineProps<{ clients: TrackerUser[] }>();
type Competition = {
    id: number;
    competition_name: string;
    competition_date: string;
    location: string | null;
    category: string | null;
    period: 'past' | 'upcoming';
    placing?: string | null;
    notes?: string | null;
};
type CompetitionClient = {
    id: number;
    name: string;
    competitions: Competition[];
};
const groups = ref<CompetitionClient[]>([]);
const period = ref<'all' | 'upcoming' | 'past'>('all');
const clientFilter = ref<number | null>(null);
const clientOptions = ref<Pick<TrackerUser, 'id' | 'name'>[]>([]);
const serverToday = ref('');
const page = ref(1),
    lastPage = ref(1),
    total = ref(0);
const loading = ref(true),
    busy = ref(false),
    loaded = ref(false),
    error = ref(''),
    success = ref('');
const editing = ref<number | null>(null);
const resultFieldsLoaded = ref(true);
const removing = ref<{
    client: CompetitionClient;
    competition: Competition;
} | null>(null);
const nameInput = ref<HTMLInputElement | null>(null);
const form = reactive({
    client_id: props.clients[0]?.id ?? null,
    competition_name: '',
    competition_date: '',
    location: '',
    category: '',
    placing: '',
    notes: '',
});
const canRecordResults = computed(
    () =>
        !!serverToday.value &&
        !!form.competition_date &&
        form.competition_date < serverToday.value,
);

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const query = new URLSearchParams({
            page: String(page.value),
            period: period.value,
        });
        if (clientFilter.value !== null)
            query.set('client_id', String(clientFilter.value));
        const result = await api<
            Page<CompetitionClient> & {
                today: string;
                client_options: Pick<TrackerUser, 'id' | 'name'>[];
            }
        >('/api/competitions?' + query.toString());
        clientOptions.value = result.client_options;
        if (
            clientFilter.value !== null &&
            !clientOptions.value.some(
                (client) => client.id === clientFilter.value,
            )
        ) {
            clientFilter.value = null;
            page.value = 1;
            await load();
            return;
        }
        serverToday.value = result.today;
        groups.value = result.data;
        lastPage.value = result.last_page;
        total.value = result.total;
        loaded.value = true;
    } catch (cause) {
        error.value = message(cause);
        loaded.value = false;
    } finally {
        loading.value = false;
    }
}
function reset() {
    editing.value = null;
    resultFieldsLoaded.value = true;
    Object.assign(form, {
        competition_name: '',
        competition_date: '',
        location: '',
        category: '',
        placing: '',
        notes: '',
    });
}
function edit(client: CompetitionClient, competition: Competition) {
    error.value = '';
    success.value = '';
    removing.value = null;
    editing.value = competition.id;
    resultFieldsLoaded.value = competition.period === 'past';
    Object.assign(form, {
        client_id: client.id,
        competition_name: competition.competition_name,
        competition_date: competition.competition_date,
        location: competition.location ?? '',
        category: competition.category ?? '',
        placing: competition.placing ?? '',
        notes: competition.notes ?? '',
    });
    nameInput.value?.focus();
}
async function save() {
    if (!form.client_id || busy.value || loading.value) return;
    busy.value = true;
    error.value = '';
    success.value = '';
    removing.value = null;
    try {
        const { client_id, placing, notes, ...data } = form;
        // Preserve hidden results if an upcoming show is moved back into the past.
        const results = canRecordResults.value
            ? {
                  ...(resultFieldsLoaded.value || placing !== ''
                      ? { placing }
                      : {}),
                  ...(resultFieldsLoaded.value || notes !== ''
                      ? { notes }
                      : {}),
              }
            : {};
        const wasEditing = editing.value !== null;
        await api(
            '/api/clients/' +
                client_id +
                '/competitions' +
                (wasEditing ? '/' + editing.value : ''),
            wasEditing ? 'PUT' : 'POST',
            { ...data, ...results },
        );
        reset();
        page.value = 1;
        await load();
        success.value = wasEditing
            ? 'Competition updated.'
            : 'Competition added.';
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
async function remove() {
    if (!removing.value || busy.value) return;
    busy.value = true;
    error.value = '';
    success.value = '';
    const { client, competition } = removing.value;
    try {
        await api(
            '/api/clients/' + client.id + '/competitions/' + competition.id,
            'DELETE',
        );
        removing.value = null;
        if (editing.value === competition.id) reset();
        await load();
        if (loaded.value && !groups.value.length && page.value > 1) {
            page.value--;
            await load();
        }
        success.value = 'Competition removed.';
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
async function paginate(next: number) {
    page.value = next;
    removing.value = null;
    await load();
}
async function changeFilters() {
    page.value = 1;
    removing.value = null;
    await load();
}
function status(competition: Competition): string {
    return competition.period === 'past'
        ? 'Past competition'
        : competition.competition_date === serverToday.value
          ? 'Today'
          : 'Upcoming';
}
onMounted(load);
</script>

<template>
    <section aria-label="Client competitions">
        <p v-if="error" class="notice" role="alert">
            {{ error }}
            <button
                v-if="!loaded"
                class="text-link"
                :disabled="busy || loading"
                @click="load"
            >
                Retry
            </button>
        </p>
        <p v-if="success" class="notice" role="status">{{ success }}</p>
        <form class="panel" @submit.prevent="save">
            <div class="section-bar">
                <h2>
                    {{ editing ? 'Edit competition' : 'Add a competition' }}
                </h2>
            </div>
            <p class="form-help">
                Add a show date for an active client. Competition name and date
                are required; other details are optional. Results can be added
                after the show date.
            </p>
            <p v-if="!clients.length" class="form-help">
                Invite a client from the Clients tab to add their competitions.
            </p>
            <fieldset
                class="form-grid competition-fields"
                :disabled="busy || loading || !clients.length"
            >
                <legend class="sr-only">Competition details</legend>
                <label class="full-width">
                    Client
                    <select
                        v-model="form.client_id"
                        required
                        :disabled="editing !== null"
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
                        </option>
                    </select>
                </label>
                <label>
                    Competition name
                    <input
                        ref="nameInput"
                        v-model="form.competition_name"
                        required
                        maxlength="200"
                    />
                </label>
                <label>
                    Competition date
                    <input
                        v-model="form.competition_date"
                        type="date"
                        required
                    />
                </label>
                <label>
                    Location (optional)
                    <input v-model="form.location" maxlength="200" />
                </label>
                <label>
                    Category (optional)
                    <input v-model="form.category" maxlength="150" />
                </label>
                <template v-if="canRecordResults">
                    <label>
                        Placing (optional)
                        <input v-model="form.placing" maxlength="50" />
                    </label>
                    <label class="full-width">
                        Show notes (optional)
                        <textarea
                            v-model="form.notes"
                            rows="3"
                            maxlength="5000"
                        />
                    </label>
                </template>
            </fieldset>
            <div class="form-actions">
                <button
                    class="button button-small"
                    :disabled="busy || loading || !clients.length"
                >
                    {{
                        busy
                            ? 'Saving…'
                            : editing
                              ? 'Save changes'
                              : 'Add competition'
                    }}
                </button>
                <button
                    v-if="editing"
                    type="button"
                    class="text-link"
                    :disabled="busy"
                    @click="reset"
                >
                    Cancel editing
                </button>
            </div>
        </form>
        <div class="section-bar">
            <h2>Competitions by client</h2>
            <button class="text-link" :disabled="busy || loading" @click="load">
                Refresh
            </button>
        </div>
        <div class="competition-filters">
            <label class="competition-filter">
                Show period
                <select
                    v-model="period"
                    :disabled="busy || loading"
                    @change="changeFilters"
                >
                    <option value="all">All shows</option>
                    <option value="upcoming">Upcoming shows</option>
                    <option value="past">Past shows</option>
                </select>
            </label>
            <label class="competition-filter">
                Filter by client
                <select
                    v-model="clientFilter"
                    :disabled="busy || loading || !clientOptions.length"
                    @change="changeFilters"
                >
                    <option :value="null">All clients with competitions</option>
                    <option
                        v-for="client in clientOptions"
                        :key="client.id"
                        :value="client.id"
                    >
                        {{ client.name }}
                    </option>
                </select>
            </label>
        </div>
        <p class="form-help">
            Shows are grouped by client. Upcoming includes today; past shows
            have a date before today.
        </p>
        <p v-if="loading" class="loading-message" role="status">
            Loading competitions…
        </p>
        <template v-else-if="loaded">
            <p v-if="!total" class="empty-state">
                {{
                    clientFilter !== null
                        ? 'No shows match this client and show period.'
                        : period === 'all'
                          ? 'No client competitions recorded yet. Add the first one above.'
                          : period === 'upcoming'
                            ? 'No upcoming shows for your active clients.'
                            : 'No past shows for your active clients.'
                }}
            </p>
            <p v-else class="form-help">
                {{ total }} {{ total === 1 ? 'client has' : 'clients have' }}
                shows matching these filters.
            </p>
            <article v-for="client in groups" :key="client.id" class="panel">
                <div class="section-bar competition-heading">
                    <h2>{{ client.name }}</h2>
                    <span class="eyebrow">
                        {{ client.competitions.length }}
                        {{
                            client.competitions.length === 1
                                ? 'competition'
                                : 'competitions'
                        }}
                    </span>
                </div>
                <div
                    v-for="competition in client.competitions"
                    :key="competition.id"
                    class="competition-entry"
                >
                    <div class="section-bar competition-heading">
                        <h3>{{ competition.competition_name }}</h3>
                        <span class="status-pill">
                            {{ status(competition) }}
                        </span>
                    </div>
                    <p class="competition-date">
                        <time :datetime="competition.competition_date">
                            {{ displayDate(competition.competition_date) }}
                        </time>
                    </p>
                    <p v-if="competition.location" class="form-help">
                        Location: {{ competition.location }}
                    </p>
                    <p v-if="competition.category" class="form-help">
                        Category: {{ competition.category }}
                    </p>
                    <template v-if="competition.period === 'past'">
                        <p v-if="competition.placing" class="form-help">
                            Placing: {{ competition.placing }}
                        </p>
                        <p
                            v-if="competition.notes"
                            class="form-help competition-notes"
                        >
                            Show notes: {{ competition.notes }}
                        </p>
                        <p
                            v-if="!competition.placing && !competition.notes"
                            class="form-help"
                        >
                            No results recorded yet.
                        </p>
                    </template>
                    <div class="row-actions">
                        <button
                            class="text-link"
                            :disabled="busy"
                            :aria-label="
                                'Edit ' +
                                competition.competition_name +
                                ' for ' +
                                client.name
                            "
                            @click="edit(client, competition)"
                        >
                            {{
                                competition.period === 'past' &&
                                !competition.placing &&
                                !competition.notes
                                    ? 'Add results'
                                    : 'Edit'
                            }}
                        </button>
                        <button
                            class="text-link"
                            :disabled="busy"
                            :aria-label="
                                'Remove ' +
                                competition.competition_name +
                                ' for ' +
                                client.name
                            "
                            @click="removing = { client, competition }"
                        >
                            Remove
                        </button>
                    </div>
                    <div
                        v-if="removing?.competition.id === competition.id"
                        role="region"
                        aria-label="Confirm competition removal"
                    >
                        <p class="form-help">
                            Remove {{ competition.competition_name }} for
                            {{ client.name }}? Their other records will be kept.
                        </p>
                        <div class="form-actions">
                            <button
                                class="button button-small"
                                :disabled="busy"
                                @click="remove"
                            >
                                Remove competition
                            </button>
                            <button
                                class="text-link"
                                :disabled="busy"
                                @click="removing = null"
                            >
                                Keep competition
                            </button>
                        </div>
                    </div>
                </div>
            </article>
            <div v-if="lastPage > 1" class="pagination">
                <button
                    :disabled="busy || loading || page <= 1"
                    @click="paginate(page - 1)"
                >
                    Previous
                </button>
                <span>Page {{ page }} of {{ lastPage }}</span>
                <button
                    :disabled="busy || loading || page >= lastPage"
                    @click="paginate(page + 1)"
                >
                    Next
                </button>
            </div>
        </template>
    </section>
</template>

<style scoped>
.competition-fields {
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}
.competition-heading {
    flex-wrap: wrap;
    overflow-wrap: anywhere;
}
.competition-filters {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 320px));
    gap: 20px;
    margin-bottom: 16px;
}
.competition-filter {
    display: grid;
    min-width: 0;
    gap: 10px;
}
.competition-filter select {
    min-width: 0;
}
.competition-entry {
    padding: 24px 0;
    border-top: 1px solid var(--line);
}
.competition-entry:last-child {
    padding-bottom: 0;
}
.competition-date {
    font-size: 18px;
}
.competition-notes {
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}
@media (max-width: 720px) {
    .competition-filters {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
