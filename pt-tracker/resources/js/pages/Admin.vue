<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import DropdownSelect from '@/components/DropdownSelect.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { useCoachPricing } from '@/composables/useCoachPricing';
import { api, ApiError, displayDate, message } from '@/lib/tracker-api';
import type { Page } from '@/lib/tracker-api';
const { plans } = useCoachPricing();

type Account = {
    id: number;
    name: string;
    email: string;
    role: 'coach' | 'client';
    is_active: boolean;
    is_admin: boolean;
    billing_exempt: boolean;
    coach_membership: {
        last_paid_at: string | null;
        paid_through: string | null;
        retention_expires_at: string | null;
    } | null;
    is_bodybuilding_coach: boolean;
    active_clients_count: number;
    created_at: string;
};
type AccountsResponse = {
    users: Page<Account>;
    summary: {
        active_coaches: number;
        active_clients: number;
        inactive_accounts: number;
        pending_invitations: number;
    };
};
type SetupResponse = {
    data: { name: string; email: string };
    setup_url: string;
    expires_in_minutes: number;
    delivery: 'sent' | 'local' | 'failed';
};

const results = ref<AccountsResponse | null>(null);
const loading = ref(true);
const busy = ref(false);
const error = ref('');
const notice = ref('');
const search = ref('');
const role = ref('');
const status = ref('');
const name = ref('');
const email = ref('');
const discipline = ref('bodybuilding');
const setup = ref<SetupResponse | null>(null);
const setupInput = ref<HTMLInputElement | null>(null);
const pendingAccess = ref<Account | null>(null);
const accounts = computed(() => results.value?.users.data ?? []);
const stats = computed(() => [
    { label: 'Active coaches', value: results.value?.summary.active_coaches },
    { label: 'Active clients', value: results.value?.summary.active_clients },
    {
        label: 'Inactive accounts',
        value: results.value?.summary.inactive_accounts,
    },
    {
        label: 'Pending client invitations',
        value: results.value?.summary.pending_invitations,
    },
]);

function handleError(cause: unknown) {
    error.value = message(cause);
    if (cause instanceof ApiError && cause.status === 401)
        router.visit('/login');
}
async function loadAccounts(page = 1) {
    loading.value = true;
    error.value = '';
    pendingAccess.value = null;
    const query = new URLSearchParams({ page: String(page) });
    if (search.value.trim()) query.set('search', search.value.trim());
    if (role.value) query.set('role', role.value);
    if (status.value) query.set('status', status.value);
    try {
        results.value = await api<AccountsResponse>(
            '/api/admin/users?' + query.toString(),
        );
    } catch (cause) {
        handleError(cause);
    } finally {
        loading.value = false;
    }
}
async function createCoach() {
    busy.value = true;
    error.value = '';
    notice.value = '';
    setup.value = null;
    try {
        setup.value = await api<SetupResponse>('/api/admin/coaches', 'POST', {
            name: name.value,
            email: email.value,
            is_bodybuilding_coach: discipline.value === 'bodybuilding',
        });
        name.value = '';
        email.value = '';
        notice.value = 'Coach account created. Their password link is below.';
        await loadAccounts();
    } catch (cause) {
        handleError(cause);
    } finally {
        busy.value = false;
    }
}
async function sendSetupLink(account: Account) {
    busy.value = true;
    error.value = '';
    notice.value = '';
    setup.value = null;
    try {
        setup.value = await api<SetupResponse>(
            `/api/admin/coaches/${account.id}/setup-link`,
            'POST',
        );
        notice.value =
            'A fresh password link is ready. Previous password links for this account no longer work.';
    } catch (cause) {
        handleError(cause);
    } finally {
        busy.value = false;
    }
}
async function updateAccess() {
    const account = pendingAccess.value;
    if (!account) return;
    busy.value = true;
    error.value = '';
    notice.value = '';
    try {
        const response = await api<{ message: string }>(
            `/api/admin/users/${account.id}/access`,
            'PATCH',
            {
                is_active: !account.is_active,
            },
        );
        notice.value = response.message;
        setup.value = null;
        await loadAccounts();
    } catch (cause) {
        handleError(cause);
    } finally {
        busy.value = false;
    }
}
async function copySetupLink() {
    if (!setup.value) return;
    try {
        await navigator.clipboard.writeText(setup.value.setup_url);
        notice.value =
            'Password link copied. Share it privately with this coach.';
    } catch {
        setupInput.value?.select();
        notice.value =
            'Select and copy the password link, then share it privately with this coach.';
    }
}
async function logout() {
    busy.value = true;
    try {
        await api('/api/logout', 'POST');
        router.visit('/login');
    } catch (cause) {
        handleError(cause);
        busy.value = false;
    }
}
onMounted(() => loadAccounts());
</script>

<template>
    <Head title="Admin portal">
        <meta name="robots" content="noindex, nofollow" />
    </Head>
    <SiteHeader dashboard skip-target="#main-content">
        <Link href="/dashboard">Coach workspace</Link>
        <button
            type="button"
            class="text-link"
            :disabled="busy"
            @click="logout"
        >
            Sign out
        </button>
    </SiteHeader>
    <main id="main-content" class="admin-page" tabindex="-1">
        <header class="admin-heading">
            <div>
                <p class="eyebrow">STAGE-READY ADMINISTRATION</p>
                <h1>People. Access. Ready to coach.</h1>
                <p>Create coach accounts and manage access to Stage-Ready.</p>
            </div>
            <a href="#create-coach" class="button button-small"
                >Add a coach <span aria-hidden="true">+</span></a
            >
        </header>

        <p v-if="error" class="notice" role="alert">{{ error }}</p>
        <p v-if="notice" class="notice" role="status">{{ notice }}</p>

        <dl class="admin-stats" aria-label="Platform overview">
            <div v-for="stat in stats" :key="stat.label">
                <dt>{{ stat.label }}</dt>
                <dd>{{ stat.value ?? '—' }}</dd>
            </div>
        </dl>

        <div class="admin-layout">
            <section
                class="admin-accounts"
                aria-labelledby="accounts-title"
                :aria-busy="loading"
            >
                <div class="admin-section-heading">
                    <div>
                        <p class="eyebrow">MEMBER DIRECTORY</p>
                        <h2 id="accounts-title">Accounts</h2>
                    </div>
                    <span v-if="results" class="admin-count"
                        >{{ results.users.total }}
                        {{
                            results.users.total === 1 ? 'account' : 'accounts'
                        }}</span
                    >
                </div>
                <form class="admin-filters" @submit.prevent="loadAccounts()">
                    <label class="admin-search"
                        >Search accounts
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Name or email address"
                            maxlength="255"
                        />
                    </label>
                    <label
                        >Account type
                        <DropdownSelect v-model="role">
                            <option value="">All types</option>
                            <option value="coach">Coaches</option>
                            <option value="client">Clients</option>
                            <option value="admin">Administrators</option>
                        </DropdownSelect>
                    </label>
                    <label
                        >Access
                        <DropdownSelect v-model="status">
                            <option value="">All accounts</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </DropdownSelect>
                    </label>
                    <button
                        class="button button-small"
                        :disabled="loading || busy"
                    >
                        Apply filters
                    </button>
                </form>

                <p v-if="loading" class="admin-empty" role="status">
                    Loading accounts…
                </p>
                <p v-else-if="!results" class="admin-empty">
                    Accounts could not be loaded. Apply filters to try again.
                </p>
                <p v-else-if="accounts.length === 0" class="admin-empty">
                    No accounts match these filters.
                </p>
                <ul v-else class="admin-account-list">
                    <li
                        v-for="account in accounts"
                        :key="account.id"
                        class="admin-account"
                    >
                        <div class="admin-account-top">
                            <div class="admin-account-name">
                                <h3>{{ account.name }}</h3>
                                <p>{{ account.email }}</p>
                            </div>
                            <span
                                class="admin-status"
                                :class="{ inactive: !account.is_active }"
                                >{{
                                    account.is_active ? 'Active' : 'Inactive'
                                }}</span
                            >
                        </div>
                        <div class="admin-account-details">
                            <span v-if="account.is_admin" class="admin-badge"
                                >Administrator</span
                            >
                            <span>{{
                                account.role === 'coach'
                                    ? account.is_bodybuilding_coach
                                        ? 'Bodybuilding coach'
                                        : 'Powerlifting coach'
                                    : 'Client'
                            }}</span>
                            <span v-if="account.role === 'coach'"
                                >{{ account.active_clients_count }} active
                                {{
                                    account.active_clients_count === 1
                                        ? 'client'
                                        : 'clients'
                                }}</span
                            >
                            <span
                                >Joined
                                {{ displayDate(account.created_at) }}</span
                            >
                            <span v-if="account.coach_membership?.last_paid_at"
                                >Last payment
                                {{
                                    displayDate(
                                        account.coach_membership.last_paid_at,
                                    )
                                }}</span
                            >
                            <span v-if="account.coach_membership?.paid_through"
                                >Paid through
                                {{
                                    displayDate(
                                        account.coach_membership.paid_through,
                                    )
                                }}</span
                            >
                            <span
                                v-if="
                                    account.role === 'coach' &&
                                    !account.is_admin &&
                                    !account.billing_exempt &&
                                    !account.coach_membership?.paid_through
                                "
                                >Awaiting first payment</span
                            >
                        </div>
                        <div
                            v-if="!account.is_admin"
                            class="admin-account-actions"
                        >
                            <button
                                v-if="
                                    account.role === 'coach' &&
                                    account.is_active
                                "
                                type="button"
                                class="text-link"
                                :disabled="busy"
                                @click="sendSetupLink(account)"
                            >
                                Send password link
                            </button>
                            <button
                                type="button"
                                class="text-link"
                                :disabled="busy"
                                :aria-label="`${account.is_active ? 'Deactivate' : 'Activate'} ${account.name}`"
                                @click="pendingAccess = account"
                            >
                                {{
                                    account.is_active
                                        ? 'Deactivate account'
                                        : 'Activate account'
                                }}
                            </button>
                        </div>
                        <div
                            v-if="pendingAccess?.id === account.id"
                            class="admin-confirm"
                            role="group"
                            :aria-label="`Confirm access change for ${account.name}`"
                        >
                            <p>
                                {{
                                    account.is_active
                                        ? `Deactivate ${account.name}? Sign-in and coaching access will be blocked. Their records and assignments will stay saved.`
                                        : `Activate ${account.name}? Sign-in and existing coaching access will resume.`
                                }}
                            </p>
                            <div class="admin-confirm-actions">
                                <button
                                    type="button"
                                    class="button button-small"
                                    :disabled="busy"
                                    @click="updateAccess"
                                >
                                    {{
                                        busy
                                            ? 'Saving…'
                                            : account.is_active
                                              ? 'Confirm deactivation'
                                              : 'Confirm activation'
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="text-link"
                                    :disabled="busy"
                                    @click="pendingAccess = null"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
                <nav
                    v-if="results && results.users.last_page > 1"
                    class="admin-pagination"
                    aria-label="Account pages"
                >
                    <button
                        type="button"
                        class="text-link"
                        :disabled="
                            loading || busy || results.users.current_page <= 1
                        "
                        @click="loadAccounts(results.users.current_page - 1)"
                    >
                        Previous
                    </button>
                    <span
                        >Page {{ results.users.current_page }} of
                        {{ results.users.last_page }}</span
                    >
                    <button
                        type="button"
                        class="text-link"
                        :disabled="
                            loading ||
                            busy ||
                            results.users.current_page >=
                                results.users.last_page
                        "
                        @click="loadAccounts(results.users.current_page + 1)"
                    >
                        Next
                    </button>
                </nav>
                <p class="form-help admin-access-note">
                    Deactivation suspends access. It does not end coaching or
                    remove history. Administrator access is managed separately.
                </p>
            </section>

            <aside class="admin-sidebar">
                <section
                    id="create-coach"
                    class="admin-card"
                    aria-labelledby="create-title"
                >
                    <p class="eyebrow">START COACHING</p>
                    <h2 id="create-title">Add a coach</h2>
                    <p class="form-help">
                        Create their account and send a link to choose a
                        password.
                    </p>
                    <form
                        class="admin-create-form"
                        @submit.prevent="createCoach"
                    >
                        <label
                            >Full name<input
                                v-model="name"
                                autocomplete="name"
                                maxlength="100"
                                required
                        /></label>
                        <label
                            >Email address<input
                                v-model="email"
                                type="email"
                                autocomplete="email"
                                maxlength="255"
                                required
                        /></label>
                        <label
                            >Coaching discipline
                            <DropdownSelect
                                v-model="discipline"
                                required
                                aria-describedby="discipline-help"
                            >
                                <option value="bodybuilding">
                                    Bodybuilding
                                </option>
                                <option value="powerlifting">
                                    Powerlifting
                                </option>
                            </DropdownSelect>
                        </label>
                        <p id="discipline-help" class="form-help">
                            Discipline is fixed when the account is created.
                            Bodybuilding includes posing photos and reviews.
                        </p>
                        <button class="button" :disabled="busy">
                            {{ busy ? 'Please wait…' : 'Create coach account' }}
                        </button>
                    </form>
                </section>

                <section
                    v-if="setup"
                    class="admin-card admin-setup"
                    aria-labelledby="setup-title"
                >
                    <p class="eyebrow">PASSWORD SETUP</p>
                    <h2 id="setup-title">{{ setup.data.name }}</h2>
                    <p class="form-help" role="status">
                        {{
                            setup.delivery === 'sent'
                                ? `A password link was sent to ${setup.data.email}.`
                                : setup.delivery === 'local'
                                  ? 'Email delivery is in local mode. Copy the link below for this coach.'
                                  : 'The account is ready, but email delivery failed. Copy the link below or send a fresh link later.'
                        }}
                    </p>
                    <label
                        >Private password link<input
                            ref="setupInput"
                            :value="setup.setup_url"
                            readonly
                            spellcheck="false"
                    /></label>
                    <button
                        type="button"
                        class="button button-small"
                        @click="copySetupLink"
                    >
                        Copy password link
                    </button>
                    <p class="form-help">
                        Expires in {{ setup.expires_in_minutes }} minutes from
                        creation. The coach chooses their own password. Share
                        this link privately.
                    </p>
                    <button
                        type="button"
                        class="text-link"
                        @click="setup = null"
                    >
                        Dismiss link
                    </button>
                </section>

                <section
                    class="admin-card admin-onboarding"
                    aria-labelledby="onboarding-title"
                >
                    <p class="eyebrow">FROM ACCOUNT TO CLIENT</p>
                    <h2 id="onboarding-title">How onboarding works</h2>
                    <ol>
                        <li>
                            <strong>Coaches join themselves.</strong>
                            <p>
                                They create an account, choose their discipline,
                                verify their email and subscribe for
                                {{ plans.powerlifting.price_label }} per month
                                for powerlifting or
                                {{ plans.bodybuilding.price_label }} per month
                                for bodybuilding. No invitation or approval is
                                needed.
                            </p>
                        </li>
                        <li>
                            <strong>The coach invites a client.</strong>
                            <p>
                                In Clients, they enter a name and email. The
                                invitation lasts seven days.
                            </p>
                        </li>
                        <li>
                            <strong>The client accepts.</strong>
                            <p>
                                New clients choose a password. Existing clients
                                sign in. Coaching starts immediately.
                            </p>
                        </li>
                    </ol>
                </section>
            </aside>
        </div>
    </main>
</template>

<style scoped>
.admin-page {
    max-width: 1520px;
    margin: 0 auto;
    padding: 56px 6vw 80px;
    font-size: 16px;
}
.admin-heading,
.admin-section-heading,
.admin-account-top,
.admin-pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
}
.admin-heading {
    align-items: flex-start;
    margin-bottom: 36px;
}
.admin-heading h1 {
    max-width: 22ch;
    margin: 20px 0 16px;
    font-size: clamp(32px, 4vw, 52px);
    line-height: 1.15;
    letter-spacing: -0.035em;
    font-weight: 500;
}
.admin-heading > div > p:last-child {
    color: #b8b8b8;
    line-height: 1.75;
}
.admin-heading > .button {
    flex-shrink: 0;
    margin-top: 32px;
}
.admin-stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    margin: 32px 0 48px;
    border-block: 1px solid var(--line);
}
.admin-stats > div {
    padding: 28px 24px;
    border-right: 1px solid var(--line);
}
.admin-stats > div:first-child {
    padding-left: 0;
}
.admin-stats > div:last-child {
    border-right: 0;
}
.admin-stats dt {
    color: #b8b8b8;
    font-size: 14px;
    line-height: 1.6;
}
.admin-stats dd {
    margin: 12px 0 0;
    font-size: 38px;
    font-weight: 500;
    line-height: 1.2;
}
.admin-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(300px, 1fr);
    gap: 40px;
    align-items: start;
}
.admin-layout > *,
.admin-account-name {
    min-width: 0;
}
.admin-section-heading {
    margin-bottom: 24px;
}
.admin-page h2 {
    margin: 10px 0 0;
    font-size: 26px;
    line-height: 1.3;
    font-weight: 500;
    letter-spacing: -0.025em;
}
.admin-count {
    color: #b8b8b8;
    font-size: 14px;
    white-space: nowrap;
}
.admin-filters {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    align-items: end;
    margin: 0 0 24px;
}
.admin-search {
    grid-column: 1 / -1;
}
.admin-filters > button {
    grid-column: 1 / -1;
    justify-self: start;
}
.admin-page label {
    min-width: 0;
    font-size: 15px;
    line-height: 1.6;
}
.admin-page input,
.admin-page :deep(select) {
    min-height: 48px;
    font-size: 16px;
}
.admin-page .form-help {
    font-size: 14px;
    line-height: 1.8;
    color: #b8b8b8;
}
.admin-account-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.admin-account {
    padding: 26px 0;
    border-top: 1px solid var(--line);
}
.admin-account-name h3 {
    margin: 0 0 8px;
    font-size: 19px;
    line-height: 1.4;
    font-weight: 500;
}
.admin-account-name p {
    margin: 0;
    color: #b8b8b8;
    line-height: 1.6;
    overflow-wrap: anywhere;
}
.admin-account-top {
    align-items: flex-start;
}
.admin-status {
    flex-shrink: 0;
    border: 1px solid rgb(var(--dropdown-blue) / 50%);
    border-radius: 999px;
    padding: 5px 12px;
    font-size: 13px;
    line-height: 1.5;
    color: #acd1ff;
    background: rgb(var(--dropdown-blue) / 8%);
}
.admin-status.inactive {
    border-color: #555;
    color: #bbb;
    background: #151515;
}
.admin-account-details {
    display: flex;
    gap: 10px 20px;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 18px;
    color: #aaa;
    font-size: 14px;
    line-height: 1.7;
}
.admin-badge {
    color: #fff;
    border: 1px solid #555;
    padding: 1px 8px;
}
.admin-account-actions,
.admin-confirm-actions {
    display: flex;
    gap: 16px 28px;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 18px;
}
.admin-account-actions .text-link,
.admin-confirm-actions .text-link {
    min-height: 44px;
    font-size: 14px;
}
.admin-confirm {
    margin-top: 20px;
    padding: 20px;
    border: 1px solid rgb(var(--dropdown-blue) / 50%);
    background: rgb(var(--dropdown-blue) / 5%);
}
.admin-confirm p {
    margin: 0;
    line-height: 1.8;
}
.admin-pagination {
    padding: 24px 0;
    border-block: 1px solid var(--line);
    font-size: 14px;
}
.admin-pagination button {
    min-height: 44px;
}
.admin-empty {
    padding: 32px 0;
    border-block: 1px solid var(--line);
    color: #b8b8b8;
    line-height: 1.8;
}
.admin-access-note {
    padding-top: 20px;
    border-top: 1px solid var(--line);
}
.admin-sidebar {
    display: grid;
    gap: 28px;
}
.admin-card {
    padding: 28px;
    background: #0b0b0b;
    border: 1px solid var(--line);
    scroll-margin-top: 120px;
}
.admin-card > .form-help {
    margin-block: 16px 24px;
}
.admin-create-form {
    display: grid;
    gap: 24px;
    margin-top: 28px;
}
.admin-create-form .form-help {
    margin: -8px 0 0;
}
.admin-create-form > .button {
    width: 100%;
    justify-content: center;
}
.admin-setup {
    border-color: rgb(var(--dropdown-blue) / 65%);
}
.admin-setup h2 {
    overflow-wrap: anywhere;
}
.admin-setup label {
    margin: 20px 0;
}
.admin-setup .form-help {
    margin-block: 20px;
}
.admin-setup .text-link {
    min-height: 44px;
}
.admin-onboarding ol {
    margin: 28px 0 0;
    padding-left: 22px;
}
.admin-onboarding li {
    padding-left: 8px;
    margin-bottom: 24px;
    line-height: 1.7;
}
.admin-onboarding li:last-child {
    margin-bottom: 0;
}
.admin-onboarding strong {
    font-weight: 500;
}
.admin-onboarding li p {
    margin: 8px 0 0;
    color: #aaa;
    font-size: 14px;
    line-height: 1.8;
}
@media (max-width: 1000px) {
    .admin-layout {
        grid-template-columns: minmax(0, 1fr);
        gap: 36px;
    }
    .admin-sidebar {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        align-items: start;
    }
    .admin-setup {
        grid-column: 1 / -1;
        grid-row: 1;
    }
}
@media (max-width: 640px) {
    .admin-page {
        padding: 32px 6vw 64px;
    }
    .admin-heading {
        flex-direction: column;
        gap: 0;
    }
    .admin-heading > .button {
        margin-top: 20px;
    }
    .admin-stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        margin-bottom: 36px;
    }
    .admin-stats > div {
        padding: 20px 16px;
    }
    .admin-stats > div:nth-child(odd) {
        padding-left: 0;
    }
    .admin-stats > div:nth-child(even) {
        border-right: 0;
    }
    .admin-stats > div:nth-child(-n + 2) {
        border-bottom: 1px solid var(--line);
    }
    .admin-stats dd {
        font-size: 32px;
    }
    .admin-sidebar {
        grid-template-columns: minmax(0, 1fr);
    }
    .admin-card {
        padding: 24px;
    }
    .admin-account-top {
        gap: 12px;
    }
    .admin-pagination {
        gap: 12px;
    }
}
</style>
