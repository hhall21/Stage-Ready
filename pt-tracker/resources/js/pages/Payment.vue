<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import DropdownDetails from '@/components/DropdownDetails.vue';
import PublicPolicyLinks from '@/components/PublicPolicyLinks.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { useCoachPricing } from '@/composables/useCoachPricing';
import { api, message } from '@/lib/tracker-api';
import type { CoachPlan } from '@/types/billing';

const page = usePage();
const account = computed(() => page.props.auth.user);
const navigation = computed(() => page.props.auth.navigation);
const backHref = computed(() => navigation.value.workspace_url ?? '/');
const backLabel = computed(() =>
    navigation.value.workspace_url
        ? account.value?.is_admin
            ? 'Back to admin portal'
            : 'Back to workspace'
        : 'Back to Stage-Ready',
);
type Billing = CoachPlan & {
    has_access: boolean;
    exempt: boolean;
    payments_configured: boolean;
    subscription_management_configured: boolean;
    status: string;
    last_paid_at: string | null;
    paid_through: string | null;
    retention_expires_at: string | null;
    data_purged_at: string | null;
    cancel_at_period_end: boolean;
    can_manage_subscription: boolean;
    has_subscription: boolean;
};
const billing = ref<Billing | null>(null);
const { plans, coachPlan } = useCoachPricing();
const plan = computed(() => billing.value ?? coachPlan.value);
const busy = ref(false);
const error = ref('');
const notice = ref('');
const isCoach = computed(() => account.value?.role === 'coach');
function timestamp(value: string) {
    return new Intl.DateTimeFormat('en-GB', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

const features = computed(() => [
    {
        title: 'Training with a plan',
        description:
            'Prescribed sessions, workout logs and progress in one place.',
    },
    {
        title: 'Nutrition and daily practice',
        description:
            plan.value?.discipline === 'powerlifting'
                ? 'Meal plans and daily cardio targets.'
                : 'Meal plans and cardio, with posing and vacuum practice for bodybuilding.',
    },
    {
        title: 'Check-ins that keep the detail',
        description:
            plan.value?.discipline === 'powerlifting'
                ? 'Daily readings and bodyweight trends.'
                : 'Daily readings and bodyweight trends, plus optional bodybuilding photos.',
    },
    {
        title: 'Feedback beside the work',
        description:
            'Coach reviews and adjustments alongside recorded progress.',
    },
]);

async function refreshBilling() {
    billing.value = (await api<{ data: Billing }>('/api/billing')).data;
}
async function checkout() {
    busy.value = true;
    error.value = '';
    try {
        const result = await api<{ url: string }>(
            '/api/billing/checkout',
            'POST',
        );
        window.location.assign(result.url);
    } catch (cause) {
        error.value = message(cause);
        busy.value = false;
    }
}
async function manageBilling() {
    busy.value = true;
    error.value = '';
    try {
        const result = await api<{ url: string }>(
            '/api/billing/portal',
            'POST',
        );
        window.location.assign(result.url);
    } catch (cause) {
        error.value = message(cause);
        busy.value = false;
    }
}
onMounted(async () => {
    if (!isCoach.value) return;
    busy.value = true;
    try {
        await refreshBilling();
        const session = new URLSearchParams(window.location.search).get(
            'session_id',
        );
        if (session) {
            billing.value = (
                await api<{ data: Billing }>('/api/billing/confirm', 'POST', {
                    session_id: session,
                })
            ).data;
            notice.value = billing.value.has_access
                ? 'Payment confirmed. Your coach workspace is ready.'
                : 'Payment is still being confirmed. Refresh this page to check again.';
        }
        if (
            billing.value?.has_access !==
            Boolean(navigation.value.workspace_url)
        ) {
            router.reload({ only: ['auth'] });
        }
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
});
</script>

<template>
    <Head title="Payment">
        <meta name="robots" content="noindex, nofollow" />
        <meta
            name="description"
            :content="`Subscribe to Stage-Ready: powerlifting ${plans.powerlifting.price_label}/month or bodybuilding ${plans.bodybuilding.price_label}/month through Stripe Checkout.`"
        />
    </Head>
    <SiteHeader skip-target="#main-content" />
    <main id="main-content" class="payment-page" tabindex="-1">
        <div class="payment-topline">
            <Link :href="backHref" class="text-link payment-back">
                <span aria-hidden="true">&larr;</span>
                {{ backLabel }}
            </Link>
            <span class="payment-preview-label">COACH MEMBERSHIP</span>
        </div>

        <header class="payment-heading">
            <p class="eyebrow">STAGE-READY PAYMENTS</p>
            <h1>Review your payment.</h1>
            <p>
                Your plan and payment details, together. Stripe handles the next
                step when you are ready to pay.
            </p>
        </header>

        <p v-if="error" class="notice" role="alert">{{ error }}</p>
        <p v-if="notice" class="notice" role="status">{{ notice }}</p>
        <ol class="payment-steps" aria-label="Payment steps">
            <li aria-current="step">
                <span class="payment-step-number" aria-hidden="true">1</span>
                <span>Review</span>
            </li>
            <li>
                <span class="payment-step-number" aria-hidden="true">2</span>
                <span>Stripe Checkout</span>
            </li>
            <li>
                <span class="payment-step-number" aria-hidden="true">3</span>
                <span>Confirmation</span>
            </li>
        </ol>

        <div class="payment-layout">
            <div class="payment-overview">
                <section class="payment-plan" aria-labelledby="plan-title">
                    <p class="eyebrow">YOUR PLAN</p>
                    <h2 id="plan-title">Your Stage-Ready workspace</h2>
                    <p class="payment-plan-intro">
                        Training, nutrition and coaching records. Everything
                        stays connected to the work you put in.
                    </p>
                    <ul class="payment-features">
                        <li v-for="feature in features" :key="feature.title">
                            <span
                                class="payment-feature-check"
                                aria-hidden="true"
                            >
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path
                                        d="m6 12 4 4 8-8"
                                        stroke="currentColor"
                                        stroke-width="1.6"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </span>
                            <div>
                                <h3>{{ feature.title }}</h3>
                                <p>{{ feature.description }}</p>
                            </div>
                        </li>
                    </ul>
                </section>

                <section
                    class="payment-account"
                    aria-labelledby="account-title"
                >
                    <span class="payment-account-mark" aria-hidden="true"
                        >SR</span
                    >
                    <div>
                        <h2 id="account-title">
                            {{
                                account ? account.name : 'Your payment details'
                            }}
                        </h2>
                        <p v-if="account">{{ account.email }}</p>
                        <p v-else>
                            Stripe will collect the details needed for your
                            payment and receipt at checkout.
                        </p>
                    </div>
                </section>

                <section
                    class="payment-questions"
                    aria-labelledby="questions-title"
                >
                    <h2 id="questions-title">Before you pay</h2>
                    <PublicPolicyLinks />
                    <DropdownDetails>
                        <template #summary
                            >Where do I enter my payment details?</template
                        >
                        <p>
                            Payment details will be entered on Stripe Checkout.
                            You will be able to review the final amount before
                            confirming your payment.
                        </p>
                    </DropdownDetails>
                    <DropdownDetails>
                        <template #summary
                            >How does monthly access work?</template
                        >
                        <p>
                            Powerlifting membership costs
                            {{ plans.powerlifting.price_label }} GBP per month.
                            Bodybuilding membership costs
                            {{ plans.bodybuilding.price_label }} GBP per month.
                            Both renew automatically. Cancel in your billing
                            settings. Access continues until the paid period
                            ends.
                        </p>
                    </DropdownDetails>
                </section>
            </div>

            <aside class="payment-summary" aria-labelledby="summary-title">
                <div class="payment-summary-heading">
                    <h2 id="summary-title">Payment summary</h2>
                    <span class="payment-summary-dot" aria-hidden="true"></span>
                </div>
                <dl class="payment-line-items">
                    <div>
                        <dt>Plan</dt>
                        <dd>Stage-Ready{{ plan ? ` ${plan.label}` : '' }}</dd>
                    </div>
                    <div>
                        <dt>Billing period</dt>
                        <dd>Every month</dd>
                    </div>
                    <div v-if="plan">
                        <dt>Price</dt>
                        <dd>{{ plan.price_label }} GBP</dd>
                    </div>
                    <template v-else>
                        <div v-for="option in plans" :key="option.discipline">
                            <dt>{{ option.label }}</dt>
                            <dd>{{ option.price_label }} GBP / month</dd>
                        </div>
                    </template>
                </dl>
                <div class="payment-total">
                    <p>{{ plan ? 'Monthly price' : 'Coach membership' }}</p>
                    <strong
                        >{{
                            plan
                                ? plan.price_label
                                : `From ${plans.powerlifting.price_label}`
                        }}
                        / month</strong
                    >
                    <p class="payment-total-note">
                        Renews automatically each month. Review the amount
                        before confirming payment on Stripe.
                    </p>
                </div>

                <div class="payment-provider">
                    <span class="payment-lock" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <rect
                                x="5"
                                y="10"
                                width="14"
                                height="11"
                                rx="2"
                                stroke="currentColor"
                                stroke-width="1.5"
                            />
                            <path
                                d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                            />
                        </svg>
                    </span>
                    <div>
                        <h3>Stripe Checkout</h3>
                        <p>Choose your payment method on Stripe.</p>
                    </div>
                    <span class="payment-stripe-name" aria-hidden="true"
                        >stripe</span
                    >
                </div>

                <Link
                    v-if="!account"
                    href="/register"
                    class="button payment-continue"
                    >Create coach account</Link
                >
                <Link
                    v-else-if="!isCoach"
                    href="/dashboard"
                    class="button payment-continue"
                    >Open your client workspace</Link
                >
                <Link
                    v-else-if="!account.email_verified_at && !billing?.exempt"
                    href="/email/verify"
                    class="button payment-continue"
                    >Verify your email to continue</Link
                >
                <button
                    v-else-if="
                        billing && !billing.exempt && !billing.has_access
                    "
                    type="button"
                    class="button payment-continue"
                    :disabled="busy || !billing.payments_configured"
                    @click="checkout"
                >
                    {{
                        busy
                            ? 'Please wait…'
                            : billing.can_manage_subscription
                              ? 'Restore paid access'
                              : `Subscribe for ${billing.price_label} / month`
                    }}
                </button>
                <Link
                    v-if="billing?.has_access"
                    :href="navigation.workspace_url ?? '/dashboard'"
                    class="button payment-continue"
                    >{{
                        account?.is_admin
                            ? 'Open admin portal'
                            : 'Open coach workspace'
                    }}</Link
                >
                <button
                    v-if="billing?.can_manage_subscription"
                    type="button"
                    class="text-link"
                    :disabled="
                        busy || !billing.subscription_management_configured
                    "
                    @click="manageBilling"
                >
                    Manage payments
                </button>
                <Link
                    v-if="billing?.has_subscription"
                    href="/subscription/cancel"
                    class="text-link payment-cancel-link"
                    >Cancel subscription</Link
                >
                <p
                    v-if="
                        billing &&
                        !billing.payments_configured &&
                        !billing.exempt
                    "
                    class="notice"
                    role="status"
                >
                    Payments are not available yet. Your account is saved;
                    please return when checkout is configured.
                </p>
                <div v-if="billing" class="payment-checkout-preview">
                    <p v-if="billing.exempt">
                        Your administrator or development account already has
                        access.
                    </p>
                    <p v-if="billing.last_paid_at">
                        Last payment: {{ timestamp(billing.last_paid_at) }}
                    </p>
                    <p v-if="billing.paid_through">
                        Paid access ends: {{ timestamp(billing.paid_through) }}
                    </p>
                    <p v-if="billing.cancel_at_period_end">
                        Cancellation is scheduled for the end of your paid
                        period.
                    </p>
                    <p
                        v-if="
                            !billing.has_access &&
                            billing.retention_expires_at &&
                            !billing.data_purged_at
                        "
                    >
                        Coaching data is retained until
                        {{ timestamp(billing.retention_expires_at) }}. Restore
                        payment before then to keep it.
                    </p>
                    <p v-if="billing.data_purged_at">
                        The retention period ended and eligible coaching data
                        was removed. A new payment starts access again; deleted
                        records cannot be restored.
                    </p>
                </div>
                <p class="payment-preview-note">
                    When paid access ends, coach and client features pause.
                    Coaching data is kept for seven extra days. If still unpaid,
                    eligible records are deleted after client handovers are
                    prepared. Clients must save their handover when opening it;
                    it is then removed from their account.
                </p>
            </aside>
        </div>
        <footer class="payment-footer">
            <p>Stage-Ready</p>
            <p>Training. Nutrition. Progress.</p>
        </footer>
    </main>
</template>

<style scoped>
.payment-cancel-link {
    display: flex;
    align-items: center;
    min-height: 44px;
    margin-top: 12px;
}
.payment-page {
    max-width: 1240px;
    margin: 0 auto;
    padding: 28px clamp(24px, 5vw, 64px) 0;
    font-size: 16px;
    line-height: 1.65;
}
.payment-topline {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px 24px;
    margin-bottom: 36px;
}
.payment-back {
    gap: 12px;
    font-size: 14px;
}
.payment-preview-label {
    padding: 6px 12px;
    border: 1px solid var(--line);
    border-radius: 999px;
    font-size: 12px;
}
.payment-heading {
    max-width: 660px;
}
.payment-page .eyebrow {
    margin-bottom: 14px;
    font-size: 12px;
}
.payment-heading h1 {
    margin: 0 0 20px;
    font-size: clamp(34px, 4.5vw, 54px);
    font-weight: 600;
    line-height: 1.12;
    letter-spacing: -0.04em;
    text-wrap: balance;
}
.payment-heading > p:last-child {
    max-width: 55ch;
    margin: 0;
}
.payment-steps {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
    padding: 0 0 28px;
    margin: 36px 0;
    border-bottom: 1px solid var(--line);
    list-style: none;
}
.payment-steps li {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 14px;
}
.payment-step-number {
    display: grid;
    place-items: center;
    flex: 0 0 32px;
    height: 32px;
    border: 1px solid #555;
    border-radius: 50%;
    font-size: 13px;
}
.payment-steps [aria-current='step'] .payment-step-number {
    border-color: rgb(var(--dropdown-blue) / calc(75% * var(--glow-strength)));
    background: rgb(var(--dropdown-blue) / calc(12% * var(--glow-strength)));
    box-shadow:
        var(--glow-core),
        0 0 16px rgb(var(--dropdown-blue) / calc(18% * var(--glow-strength)));
}
.payment-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
    align-items: start;
    gap: clamp(28px, 4vw, 56px);
}
.payment-layout > *,
.payment-features li > div,
.payment-account > div,
.payment-provider > div {
    min-width: 0;
    overflow-wrap: anywhere;
}
.payment-page h2 {
    margin: 0;
    font-size: 23px;
    font-weight: 500;
    line-height: 1.35;
    letter-spacing: -0.02em;
}
.payment-plan-intro {
    margin: 16px 0 0;
}
.payment-features {
    display: grid;
    gap: 24px;
    padding: 0;
    margin: 32px 0 0;
    list-style: none;
}
.payment-features li {
    display: flex;
    align-items: flex-start;
    gap: 16px;
}
.payment-feature-check {
    display: grid;
    place-items: center;
    flex: 0 0 32px;
    height: 32px;
    border: 1px solid
        rgb(var(--dropdown-blue) / calc(30% * var(--glow-strength)));
    border-radius: 8px;
    background: rgb(var(--dropdown-blue) / calc(8% * var(--glow-strength)));
    color: rgb(var(--dropdown-flash));
}
.payment-feature-check svg,
.payment-lock svg {
    width: 22px;
    height: 22px;
}
.payment-features h3,
.payment-provider h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 500;
    line-height: 1.5;
}
.payment-features p {
    margin: 6px 0 0;
    font-size: 15px;
}
.payment-account {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 24px 0;
    margin-top: 32px;
    border-top: 1px solid var(--line);
    border-bottom: 1px solid var(--line);
}
.payment-account-mark {
    display: grid;
    place-items: center;
    flex: 0 0 44px;
    height: 44px;
    border: 1px solid #555;
    font-size: 18px;
    font-weight: 600;
    letter-spacing: -0.08em;
}
.payment-account h2 {
    font-size: 16px;
    letter-spacing: normal;
}
.payment-account p {
    margin: 6px 0 0;
    font-size: 14px;
}
.payment-questions {
    margin-top: 36px;
}
.payment-questions > h2 {
    margin-bottom: 16px;
    font-size: 20px;
}
.payment-questions :deep(.dropdown-summary) {
    padding: 16px 8px;
    font-size: 15px;
}
.payment-questions :deep(.dropdown-content) {
    font-size: 15px;
}
.payment-summary {
    position: sticky;
    top: 24px;
    padding: clamp(24px, 3vw, 36px);
    border: 1px solid
        rgb(var(--dropdown-blue) / calc(35% * var(--glow-strength)));
    border-radius: 16px;
    background:
        radial-gradient(
            ellipse at top right,
            rgb(var(--dropdown-blue) / calc(9% * var(--glow-strength))),
            transparent 65%
        ),
        #0d0d0d;
    box-shadow:
        var(--glow-core),
        0 0 40px rgb(var(--dropdown-blue) / calc(6% * var(--glow-strength)));
}
.payment-summary-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}
.payment-summary-heading h2 {
    font-size: 21px;
}
.payment-summary-dot {
    flex: 0 0 7px;
    height: 7px;
    border-radius: 50%;
    background: rgb(var(--dropdown-blue));
    box-shadow:
        var(--glow-core),
        0 0 12px rgb(var(--dropdown-blue) / calc(70% * var(--glow-strength)));
}
.payment-line-items {
    display: grid;
    gap: 18px;
    padding: 0;
    margin: 30px 0;
}
.payment-line-items > div {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    font-size: 14px;
}
.payment-line-items dd {
    margin: 0;
    text-align: right;
}
.payment-total {
    padding: 24px 0;
    border-top: 1px solid var(--line);
    border-bottom: 1px solid var(--line);
}
.payment-total > p:first-child {
    margin: 0 0 8px;
    font-size: 14px;
}
.payment-total strong {
    font-size: clamp(22px, 2.5vw, 30px);
    font-weight: 500;
    line-height: 1.3;
    letter-spacing: -0.025em;
}
.payment-total-note {
    margin: 14px 0 0;
    font-size: 13px;
    line-height: 1.7;
}
.payment-provider {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 24px 0;
}
.payment-lock {
    display: grid;
    place-items: center;
    flex: 0 0 38px;
    height: 42px;
    border: 1px solid var(--line);
    border-radius: 8px;
    color: rgb(var(--dropdown-flash));
}
.payment-provider p {
    margin: 4px 0 0;
    font-size: 13px;
}
.payment-stripe-name {
    margin-left: auto;
    font-size: 23px;
    font-weight: 600;
    letter-spacing: -0.045em;
}
.payment-continue {
    width: 100%;
    gap: 16px;
    border-color: rgb(var(--dropdown-blue) / calc(65% * var(--glow-strength)));
    background: rgb(var(--dropdown-blue) / calc(15% * var(--glow-strength)));
    font-size: 15px;
    text-align: left;
}
.payment-continue > span {
    font-size: 22px;
}
.payment-preview-note {
    margin: 16px 0 0;
    font-size: 12px;
    text-align: center;
}
.payment-checkout-preview {
    padding: 20px;
    border: 1px solid
        rgb(var(--dropdown-blue) / calc(55% * var(--glow-strength)));
    border-radius: 8px;
    background: rgb(var(--dropdown-blue) / calc(7% * var(--glow-strength)));
}
.payment-checkout-preview:focus {
    outline: 2px solid rgb(var(--dropdown-blue));
    outline-offset: 4px;
}
.payment-checkout-preview h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 500;
    line-height: 1.4;
}
.payment-checkout-preview > p:not(.eyebrow) {
    margin: 16px 0 8px;
    font-size: 14px;
}
.payment-checkout-preview .text-link {
    font-size: 14px;
}
.payment-footer {
    display: flex;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px 24px;
    margin-top: 56px;
    padding: 24px 0;
    border-top: 1px solid var(--line);
    font-size: 12px;
}
.payment-footer p {
    margin: 0;
}
@media (max-width: 800px) {
    .payment-layout {
        grid-template-columns: minmax(0, 1fr);
    }
    .payment-summary {
        position: static;
        grid-row: 1;
    }
}
@media (max-width: 480px) {
    .payment-topline {
        margin-bottom: 28px;
    }
    .payment-steps {
        gap: 12px;
        margin: 28px 0;
    }
    .payment-steps li {
        align-items: flex-start;
        flex-direction: column;
        gap: 8px;
        font-size: 12px;
    }
    .payment-summary {
        padding: 24px 20px;
    }
    .payment-stripe-name {
        display: none;
    }
}
</style>
