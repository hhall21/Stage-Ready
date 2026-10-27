<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import DropdownDetails from '@/components/DropdownDetails.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { motionScrollBehavior } from '@/lib/motion';

const page = usePage();
const account = computed(() => page.props.auth.user);
const backHref = computed(() => (account.value ? '/dashboard' : '/'));
const checkoutPreview = ref(false);
const previewPanel = ref<HTMLElement | null>(null);
const checkoutButton = ref<HTMLButtonElement | null>(null);

const features = [
    {
        title: 'Training with a plan',
        description:
            'Prescribed sessions, workout logs and progress in one place.',
    },
    {
        title: 'Nutrition and daily practice',
        description: 'Meal plans, cardio, posing and vacuum practice.',
    },
    {
        title: 'Check-ins that keep the detail',
        description: 'Daily readings, optional photos and bodyweight trends.',
    },
    {
        title: 'Feedback beside the work',
        description:
            'Coach reviews and adjustments alongside recorded progress.',
    },
];

async function previewCheckout() {
    checkoutPreview.value = true;
    await nextTick();
    previewPanel.value?.focus({ preventScroll: true });
    previewPanel.value?.scrollIntoView({
        behavior: motionScrollBehavior(),
        block: 'nearest',
    });
}

async function returnToSummary() {
    checkoutPreview.value = false;
    await nextTick();
    checkoutButton.value?.focus();
}
</script>

<template>
    <Head title="Payment">
        <meta name="robots" content="noindex, nofollow" />
        <meta
            name="description"
            content="Preview the Stage-Ready payment summary and Stripe Checkout flow."
        />
    </Head>
    <SiteHeader skip-target="#main-content" />
    <main id="main-content" class="payment-page" tabindex="-1">
        <div class="payment-topline">
            <Link :href="backHref" class="text-link payment-back">
                <span aria-hidden="true">&larr;</span>
                {{ account ? 'Back to workspace' : 'Back to Stage-Ready' }}
            </Link>
            <span class="payment-preview-label">Design preview</span>
        </div>

        <header class="payment-heading">
            <p class="eyebrow">STAGE-READY PAYMENTS</p>
            <h1>Review your payment.</h1>
            <p>
                Your plan and payment details, together. Stripe handles the next
                step when you are ready to pay.
            </p>
        </header>

        <ol class="payment-steps" aria-label="Payment steps">
            <li :aria-current="!checkoutPreview ? 'step' : undefined">
                <span class="payment-step-number" aria-hidden="true">1</span>
                <span>Review</span>
            </li>
            <li :aria-current="checkoutPreview ? 'step' : undefined">
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
                            >Will this preview charge me?</template
                        >
                        <p>
                            No. This page is a design preview. Prices and
                            billing periods are not set, and checkout does not
                            take a payment.
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
                        <dd>Stage-Ready</dd>
                    </div>
                    <div>
                        <dt>Billing period</dt>
                        <dd>To be confirmed</dd>
                    </div>
                    <div>
                        <dt>Price</dt>
                        <dd>To be confirmed</dd>
                    </div>
                </dl>
                <div class="payment-total">
                    <p>Amount due</p>
                    <strong>To be confirmed</strong>
                    <p class="payment-total-note">
                        Your final amount and billing schedule will be shown
                        before you confirm payment.
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

                <button
                    v-if="!checkoutPreview"
                    ref="checkoutButton"
                    type="button"
                    class="button payment-continue"
                    @click="previewCheckout"
                >
                    Preview Stripe Checkout
                    <span aria-hidden="true">&rarr;</span>
                </button>
                <div
                    v-else
                    ref="previewPanel"
                    class="payment-checkout-preview"
                    role="region"
                    aria-labelledby="checkout-preview-title"
                    tabindex="-1"
                >
                    <p class="eyebrow">CHECKOUT PREVIEW</p>
                    <h3 id="checkout-preview-title">
                        The next step is Stripe.
                    </h3>
                    <p>
                        Live checkout will show your confirmed price, billing
                        schedule and available payment methods. No payment has
                        been taken in this preview.
                    </p>
                    <button
                        type="button"
                        class="text-link"
                        @click="returnToSummary"
                    >
                        Back to payment summary
                    </button>
                </div>
                <p class="payment-preview-note">
                    Preview only. No payment will be taken.
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
