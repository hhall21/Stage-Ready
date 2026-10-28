<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DropdownSelect from '@/components/DropdownSelect.vue';
import PublicPolicyLinks from '@/components/PublicPolicyLinks.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { useCoachPricing } from '@/composables/useCoachPricing';
import { api, message } from '@/lib/tracker-api';

const page = usePage();
const navigation = computed(() => page.props.auth.navigation);
const name = ref('');
const email = ref('');
const password = ref('');
const confirmation = ref('');
const discipline = ref('bodybuilding');
const { plans } = useCoachPricing();
const plan = computed(
    () =>
        plans.value[
            discipline.value === 'bodybuilding'
                ? 'bodybuilding'
                : 'powerlifting'
        ],
);
const busy = ref(false);
const error = ref('');
async function register() {
    busy.value = true;
    error.value = '';
    try {
        await api('/api/register', 'POST', {
            name: name.value,
            email: email.value,
            password: password.value,
            password_confirmation: confirmation.value,
            is_bodybuilding_coach: discipline.value === 'bodybuilding',
        });
        password.value = '';
        confirmation.value = '';
        router.visit('/email/verify');
    } catch (cause) {
        error.value = message(cause);
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <Head title="Create your coach account" />
    <SiteHeader skip-target="#main-content" />
    <main id="main-content" class="register-page" tabindex="-1">
        <section class="register-intro">
            <p class="eyebrow">STAGE-READY FOR COACHES</p>
            <h1>Your clients’ success starts here.</h1>
            <p>Training, nutrition, check-ins and progress in one workspace.</p>
            <p class="register-price">
                {{ plan.price_label }} <span>per month · {{ plan.label }}</span>
            </p>
            <p>
                {{ plans.powerlifting.label }}
                {{ plans.powerlifting.price_label }}/month.
                {{ plans.bodybuilding.label }}
                {{ plans.bodybuilding.price_label }}/month.
            </p>
            <p>
                No invitation or approval needed. Create your account, verify
                your email and subscribe through Stripe.
            </p>
            <p class="form-help">
                Renews automatically each month. Cancel through your billing
                page. Coaching access pauses when the paid period ends. Data is
                kept for seven extra days, then eligible coaching records are
                deleted after client handovers are prepared. Billing-expiry
                handovers are removed after clients open them.
            </p>
            <PublicPolicyLinks />
        </section>
        <section class="register-form-panel" aria-label="Coach account details">
            <div
                v-if="page.props.auth.user"
                class="signed-in-card"
                aria-labelledby="signed-in-title"
            >
                <div class="signed-in-heading">
                    <p class="eyebrow signed-in-status">
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" />
                            <path d="m8 12 2.5 2.5L16 9" />
                        </svg>
                        SIGNED IN
                    </p>
                    <h2 id="signed-in-title">
                        {{
                            navigation.workspace_url
                                ? 'Welcome back.'
                                : 'Finish your account setup.'
                        }}
                    </h2>
                    <p
                        v-if="navigation.workspace_url"
                        class="signed-in-description"
                    >
                        Your Stage-Ready account is ready. Pick up where you
                        left off in your workspace.
                    </p>
                    <p
                        v-else-if="!page.props.auth.user.email_verified_at"
                        class="signed-in-description"
                    >
                        Verify your email, then subscribe to unlock your
                        coaching workspace.
                    </p>
                    <p v-else class="signed-in-description">
                        Your email is verified. Subscribe to unlock your
                        coaching workspace.
                    </p>
                </div>
                <div class="signed-in-account">
                    <span class="signed-in-avatar" aria-hidden="true">{{
                        page.props.auth.user.name.trim().charAt(0).toUpperCase()
                    }}</span>
                    <div>
                        <p class="signed-in-name">
                            {{ page.props.auth.user.name }}
                        </p>
                        <p class="signed-in-email">
                            {{ page.props.auth.user.email }}
                        </p>
                    </div>
                </div>
                <div class="signed-in-actions">
                    <Link
                        :href="navigation.href"
                        class="button signed-in-primary"
                    >
                        {{ navigation.label }}
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M5 12h14m-6-6 6 6-6 6" />
                        </svg>
                    </Link>
                    <Link
                        v-if="
                            page.props.auth.user.role === 'coach' &&
                            navigation.workspace_url &&
                            page.props.auth.user.email_verified_at
                        "
                        href="/payment"
                        class="button button-secondary"
                        >Manage coach billing</Link
                    >
                </div>
            </div>
            <form v-else class="register-form" @submit.prevent="register">
                <h2>Create your account</h2>
                <p v-if="error" class="notice" role="alert">{{ error }}</p>
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
                    >Coaching discipline<DropdownSelect
                        v-model="discipline"
                        required
                        ><option value="bodybuilding">
                            Bodybuilding —
                            {{ plans.bodybuilding.price_label }}/month
                        </option>
                        <option value="powerlifting">
                            Powerlifting —
                            {{ plans.powerlifting.price_label }}/month
                        </option></DropdownSelect
                    ></label
                >
                <p class="form-help">
                    Your discipline is fixed when your account is created.
                    Bodybuilding includes posing photos and reviews.
                </p>
                <label
                    >Password<input
                        v-model="password"
                        type="password"
                        autocomplete="new-password"
                        minlength="12"
                        maxlength="72"
                        required
                        aria-describedby="password-help"
                /></label>
                <p id="password-help" class="form-help">
                    Use at least 12 characters, with uppercase and lowercase
                    letters and a number. Maximum 72 bytes.
                </p>
                <label
                    >Confirm password<input
                        v-model="confirmation"
                        type="password"
                        autocomplete="new-password"
                        minlength="12"
                        maxlength="72"
                        required
                /></label>
                <button class="button" :disabled="busy">
                    {{ busy ? 'Creating account…' : 'Create coach account' }}
                </button>
                <p class="form-help">
                    Already a member?
                    <Link href="/login" class="text-link">Sign in</Link>.
                    Clients join through an invitation from their coach.
                </p>
            </form>
        </section>
    </main>
</template>
<style scoped>
.register-page {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 64px;
    max-width: 1200px;
    margin: 0 auto;
    padding: 64px 6vw 80px;
}
.register-intro h1 {
    margin: 24px 0;
    font-size: clamp(36px, 5vw, 58px);
    font-weight: 500;
    line-height: 1.12;
    letter-spacing: -0.035em;
}
.register-intro p {
    line-height: 1.85;
}
.register-price {
    font-size: 52px;
    margin: 24px 0;
}
.register-price span {
    font-size: 18px;
    color: #bbb;
}
.register-intro .form-help {
    margin-top: 28px;
}
.register-form-panel {
    min-width: 0;
}
.signed-in-card {
    display: grid;
    gap: 24px;
    padding: clamp(24px, 3vw, 36px);
    border: 1px solid #333;
    border-radius: 12px;
    background: linear-gradient(145deg, #171717, #0c0c0c);
    overflow-wrap: anywhere;
}
.signed-in-status {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
    color: #bdbdbd;
}
.signed-in-status svg,
.signed-in-actions svg {
    width: 20px;
    height: 20px;
    flex-shrink: 0;
    stroke: currentColor;
    stroke-width: 1.5;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.signed-in-heading h2 {
    margin: 20px 0 12px;
    font-size: 28px;
    font-weight: 500;
    line-height: 1.25;
    letter-spacing: -0.025em;
}
.signed-in-description {
    max-width: 36ch;
    margin: 0;
    color: #b5b5b5;
    font-size: 14px;
    line-height: 1.7;
}
.signed-in-account {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 20px 0;
    border-block: 1px solid var(--line);
}
.signed-in-account > div {
    min-width: 0;
}
.signed-in-avatar {
    display: grid;
    place-items: center;
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    border: 1px solid #3a3a3a;
    border-radius: 50%;
    background: #1c1c1c;
    font-size: 16px;
}
.signed-in-name {
    margin: 0;
    font-size: 14px;
    font-weight: 500;
    line-height: 1.5;
}
.signed-in-email {
    margin: 4px 0 0;
    color: #a3a3a3;
    font-size: 13px;
    line-height: 1.5;
}
.signed-in-actions {
    display: grid;
    gap: 12px;
}
.signed-in-actions .button {
    justify-content: center;
    gap: 12px;
    width: 100%;
    border-radius: 6px;
    font-size: 13px;
    line-height: 1.5;
}
.signed-in-actions .signed-in-primary {
    justify-content: space-between;
    border-color: #f5f5f5;
    background: #f5f5f5;
    color: #111;
}
.signed-in-actions .signed-in-primary:hover {
    border-color: #ddd;
    background: #ddd;
}
.signed-in-actions .button-secondary {
    border-color: #3c3c3c;
}
.signed-in-actions .button-secondary:hover {
    border-color: #777;
    background: #1b1b1b;
}
.register-form {
    display: grid;
    gap: 24px;
}
.register-form h2 {
    font-size: 26px;
    font-weight: 500;
    margin: 0 0 8px;
}
.register-form label {
    font-size: 15px;
}
.register-form .form-help {
    margin: -8px 0 0;
}
@media (max-width: 720px) {
    .register-page {
        grid-template-columns: minmax(0, 1fr);
        gap: 40px;
        padding-top: 32px;
    }
}
</style>
