<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { useCoachPricing } from '@/composables/useCoachPricing';
import { api, ApiError, message } from '@/lib/tracker-api';
import type { CoachPlan } from '@/types/billing';

type Billing = CoachPlan & {
    has_access: boolean;
    has_subscription: boolean;
    subscription_management_configured: boolean;
    status: string;
    paid_through: string | null;
    retention_expires_at: string | null;
    data_purged_at: string | null;
    cancel_at_period_end: boolean;
};
const billing = ref<Billing | null>(null);
const page = usePage();
const { coachPlan } = useCoachPricing();
const plan = computed(() => billing.value ?? coachPlan.value);
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const notice = ref('');
const ended = computed(() =>
    ['canceled', 'incomplete_expired'].includes(billing.value?.status ?? ''),
);
const scheduled = computed(
    () => billing.value?.cancel_at_period_end === true && !ended.value,
);
const canCancel = computed(
    () => billing.value?.has_subscription && !ended.value && !scheduled.value,
);

function timestamp(value: string) {
    return new Intl.DateTimeFormat('en-GB', {
        dateStyle: 'long',
        timeStyle: 'short',
    }).format(new Date(value));
}
function handleError(cause: unknown) {
    error.value = message(cause);
    if (cause instanceof ApiError && cause.status === 401)
        router.visit('/login');
}
async function load() {
    loading.value = true;
    error.value = '';
    try {
        billing.value = (await api<{ data: Billing }>('/api/billing')).data;
    } catch (cause) {
        handleError(cause);
    } finally {
        loading.value = false;
    }
}
async function cancel() {
    saving.value = true;
    error.value = '';
    notice.value = '';
    try {
        const response = await api<{ data: Billing; message: string }>(
            '/api/billing/cancel',
            'POST',
            { confirmed: true },
        );
        billing.value = response.data;
        notice.value = response.message;
    } catch (cause) {
        handleError(cause);
    } finally {
        saving.value = false;
    }
}
onMounted(load);
</script>

<template>
    <Head title="Cancel subscription"
        ><meta name="robots" content="noindex, nofollow"
    /></Head>
    <SiteHeader dashboard skip-target="#main-content">
        <Link href="/payment">Billing</Link>
        <Link
            v-if="page.props.auth.navigation.workspace_url"
            :href="page.props.auth.navigation.workspace_url"
            >{{ page.props.auth.navigation.label }}</Link
        >
    </SiteHeader>
    <main id="main-content" class="cancellation-page" tabindex="-1">
        <Link href="/payment" class="text-link cancellation-back"
            ><span aria-hidden="true">&larr;</span> Back to billing</Link
        >
        <header class="cancellation-heading">
            <p class="eyebrow">STAGE-READY MEMBERSHIP</p>
            <h1>Cancel your subscription.</h1>
            <p>
                Stop automatic renewal of your {{ plan?.price_label }} monthly
                {{ plan?.label.toLowerCase() }} coach membership.
            </p>
        </header>
        <p v-if="error" class="notice" role="alert">{{ error }}</p>
        <p v-if="notice" class="notice" role="status">{{ notice }}</p>
        <p v-if="loading" class="cancellation-loading" role="status">
            Loading your subscription…
        </p>
        <div v-else-if="!billing" class="notice">
            <p>Your subscription details could not be loaded.</p>
            <button class="text-link" @click="load">Try again</button>
        </div>
        <template v-else>
            <div v-if="!billing.has_subscription" class="cancellation-empty">
                <h2>No subscription to cancel</h2>
                <p>
                    This account has no recorded Stripe subscription. You can
                    return to billing to see your access options.
                </p>
                <Link href="/payment" class="button button-small"
                    >Return to billing</Link
                >
            </div>
            <div v-else class="cancellation-layout">
                <section
                    class="cancellation-details"
                    aria-labelledby="details-title"
                >
                    <h2 id="details-title">What happens next</h2>
                    <ol class="cancellation-timeline">
                        <li>
                            <h3>Your paid access continues</h3>
                            <p
                                v-if="
                                    billing.has_access && billing.paid_through
                                "
                            >
                                You and your clients keep coaching features
                                until {{ timestamp(billing.paid_through) }}.
                            </p>
                            <p v-else>
                                Your paid access has already ended or has not
                                started. Cancellation does not start another
                                paid period.
                            </p>
                        </li>
                        <li>
                            <h3>Coaching features pause at expiry</h3>
                            <p>
                                You and affected clients lose coaching feature
                                access when the paid period ends. Login, billing
                                and available handovers remain accessible.
                            </p>
                        </li>
                        <li>
                            <h3>Data is kept for seven extra days</h3>
                            <p
                                v-if="
                                    billing.retention_expires_at &&
                                    !billing.data_purged_at
                                "
                            >
                                Your retention deadline is
                                {{ timestamp(billing.retention_expires_at) }}.
                                If still unpaid, eligible coaching data is
                                deleted after client handovers are prepared.
                            </p>
                            <p v-else-if="billing.data_purged_at">
                                The retention period has ended and eligible
                                coaching data has already been removed.
                            </p>
                            <p v-else>
                                Once a recorded paid period ends, coaching data
                                is retained for seven days. If still unpaid,
                                eligible records are deleted after client
                                handovers are prepared.
                            </p>
                            <p>
                                Client accounts and records needed by another
                                active coach are protected.
                            </p>
                        </li>
                        <li>
                            <h3>Clients can save their handovers</h3>
                            <p>
                                Billing-expiry handovers remain until each
                                client deliberately opens their report. The
                                stored report is then removed. Clients can
                                download their copy during that visit.
                            </p>
                        </li>
                    </ol>
                </section>
                <aside
                    class="cancellation-summary"
                    aria-labelledby="summary-title"
                >
                    <p class="eyebrow">YOUR SUBSCRIPTION</p>
                    <h2 id="summary-title">
                        Stage-Ready {{ billing.label }} coach access
                    </h2>
                    <p class="cancellation-price">
                        {{ billing.price_label }} <span>per month</span>
                    </p>
                    <dl class="cancellation-dates">
                        <div>
                            <dt>Paid access ends</dt>
                            <dd>
                                {{
                                    billing.paid_through
                                        ? timestamp(billing.paid_through)
                                        : 'No recorded paid period'
                                }}
                            </dd>
                        </div>
                        <div v-if="billing.retention_expires_at">
                            <dt>Data retention ends</dt>
                            <dd>
                                {{ timestamp(billing.retention_expires_at) }}
                            </dd>
                        </div>
                    </dl>
                    <p v-if="ended" class="notice" role="status">
                        Your subscription has ended. There are no further
                        renewals for this subscription.
                    </p>
                    <p v-else-if="scheduled" class="notice" role="status">
                        Cancellation is scheduled. This subscription will not
                        renew after the current billing period.
                    </p>
                    <template v-else-if="canCancel">
                        <p class="cancellation-explanation">
                            Confirm below to cancel automatic renewal. Your
                            existing paid-through date and data-retention
                            deadline stay the same.
                        </p>
                        <p
                            v-if="!billing.subscription_management_configured"
                            class="notice"
                            role="status"
                        >
                            Subscription changes are unavailable until Stripe is
                            configured. Please try again later.
                        </p>
                        <button
                            type="button"
                            class="button cancellation-confirm"
                            :disabled="
                                saving ||
                                !billing.subscription_management_configured
                            "
                            @click="cancel"
                        >
                            {{
                                saving
                                    ? 'Confirming cancellation…'
                                    : 'Confirm cancellation'
                            }}
                        </button>
                    </template>
                    <Link
                        href="/payment"
                        class="text-link cancellation-return"
                        >{{
                            scheduled || ended
                                ? 'Back to billing'
                                : 'Keep my subscription'
                        }}</Link
                    >
                    <p class="form-help">
                        Existing unpaid invoices are handled separately in
                        billing. Cancelling renewal does not issue an automatic
                        refund.
                    </p>
                </aside>
            </div>
        </template>
    </main>
</template>

<style scoped>
.cancellation-page {
    max-width: 1260px;
    margin: 0 auto;
    padding: 40px 6vw 80px;
    font-size: 16px;
}
.cancellation-back {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    min-height: 44px;
}
.cancellation-heading {
    margin: 36px 0 40px;
}
.cancellation-heading h1 {
    margin: 20px 0;
    font-size: clamp(34px, 4.5vw, 54px);
    line-height: 1.15;
    letter-spacing: -0.035em;
    font-weight: 500;
}
.cancellation-heading > p:last-child {
    color: #bbb;
    line-height: 1.8;
}
.cancellation-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(300px, 1fr);
    gap: 48px;
    align-items: start;
    margin-top: 32px;
}
.cancellation-layout > * {
    min-width: 0;
}
.cancellation-page h2 {
    margin: 0 0 24px;
    font-size: 25px;
    font-weight: 500;
    line-height: 1.4;
}
.cancellation-timeline {
    margin: 0;
    padding: 0 0 0 24px;
}
.cancellation-timeline li {
    padding: 0 0 28px 12px;
    margin-bottom: 24px;
    border-bottom: 1px solid var(--line);
}
.cancellation-timeline li:last-child {
    margin-bottom: 0;
}
.cancellation-timeline h3 {
    margin: 0 0 12px;
    font-weight: 500;
    font-size: 18px;
    line-height: 1.6;
}
.cancellation-timeline p {
    color: #bbb;
    margin: 12px 0 0;
    line-height: 1.85;
    overflow-wrap: anywhere;
}
.cancellation-summary {
    position: sticky;
    top: 120px;
    border: 1px solid var(--line);
    background: #0b0b0b;
    padding: 28px;
}
.cancellation-summary h2 {
    margin-top: 16px;
}
.cancellation-price {
    font-size: 44px;
    margin: 24px 0;
}
.cancellation-price span {
    font-size: 16px;
    color: #bbb;
}
.cancellation-dates {
    margin: 24px 0;
    border-top: 1px solid var(--line);
}
.cancellation-dates > div {
    padding: 20px 0;
    border-bottom: 1px solid var(--line);
}
.cancellation-dates dt {
    color: #aaa;
    font-size: 14px;
    margin-bottom: 8px;
}
.cancellation-dates dd {
    margin: 0;
    font-size: 16px;
    line-height: 1.8;
}
.cancellation-explanation {
    line-height: 1.85;
    margin: 24px 0;
}
.cancellation-confirm {
    width: 100%;
    justify-content: center;
    min-height: 48px;
}
.cancellation-return {
    display: flex;
    align-items: center;
    width: fit-content;
    min-height: 44px;
    margin: 20px 0;
}
.cancellation-summary .form-help {
    color: #aaa;
    font-size: 14px;
    line-height: 1.85;
}
.cancellation-loading,
.cancellation-empty p {
    line-height: 1.85;
    color: #bbb;
}
.cancellation-empty {
    padding: 32px 0;
    border-block: 1px solid var(--line);
}
.cancellation-empty .button {
    margin-top: 24px;
}
@media (max-width: 850px) {
    .cancellation-layout {
        grid-template-columns: minmax(0, 1fr);
        gap: 32px;
    }
    .cancellation-summary {
        position: static;
    }
}
@media (max-width: 540px) {
    .cancellation-page {
        padding-top: 24px;
    }
    .cancellation-heading {
        margin-top: 24px;
    }
    .cancellation-summary {
        padding: 24px;
    }
}
</style>
