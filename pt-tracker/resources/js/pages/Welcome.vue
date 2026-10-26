<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AthleteVisual from '@/components/AthleteVisual.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { athleteImages } from '@/lib/athlete-images';
import { vReveal } from '@/lib/motion';

const description =
    'Stage-Ready brings training plans, nutrition, client check-ins and progress tracking together for bodybuilding and powerlifting coaches.';
const features = [
    {
        label: 'TRAINING & SPLITS',
        title: 'Programme with control.',
        text: 'Build repeating splits with training and rest days. Prescribe sets, reps, load and RIR, then see what your client actually completed. Adjust upcoming sessions while keeping logged training intact.',
    },
    {
        label: 'MEALS & NUTRITION',
        title: 'Give nutrition a clear structure.',
        text: 'Create separate training-day and rest-day meal plans, each with its own meal times. Review hunger, appetite and digestion alongside your prescriptions, and schedule new plans in advance.',
    },
    {
        label: 'CHECK-INS & HEALTH',
        title: 'Get the context behind the numbers.',
        text: 'Bring bodyweight, sleep, energy, stress, steps and daily notes into the same view. Enable weekly blood pressure and resting heart rate readings for clients who need them.',
    },
    {
        label: 'PROGRESS & ANALYTICS',
        title: 'See the trend. Inform the next step.',
        text: 'Follow rolling seven-day bodyweight averages and exercise progress. Compare training weight and reps with calories and bodyweight, with reading counts that show how much information you have.',
    },
    {
        label: 'BODYBUILDING POSING & FEEDBACK',
        title: 'Make posing part of the process.',
        text: 'Bodybuilding coaches can review up to thirteen private pose photos with ratings and written feedback. During prep, add separate flatness and fullness ratings to give your client more specific direction.',
    },
    {
        label: 'COACH REVIEWS',
        title: 'Turn check-ins into a clear direction.',
        text: 'Keep written feedback and next steps together, with weekly reviews in prep and fortnightly reviews in the off-season. Published plans and reviews remain available for your client to revisit.',
    },
];
const steps = [
    {
        title: 'Invite your client.',
        text: 'Send an invitation from your dashboard. Coaching starts as soon as they accept, with a personal workspace for their plans and logs.',
    },
    {
        title: 'Set their direction.',
        text: 'Prescribe their training, meals, cardio and practice. Your client sees what to do and records how it went against the plan you set.',
    },
    {
        title: 'Review. Adjust. Progress.',
        text: 'Use check-ins and performance trends to guide your feedback, with private posing photos available in bodybuilding coaching. Update upcoming plans as their needs change.',
    },
];
const questions = [
    {
        title: 'Is Stage-Ready only for competition prep?',
        text: 'It supports both off-season coaching and competition preparation. Training, nutrition and progress tracking stay together throughout, with weekly written reviews in prep and fortnightly reviews in the off-season.',
    },
    {
        title: 'Can clients rewrite the workouts I prescribe?',
        text: 'You control the training plan. Clients log their performance against prescribed sessions. When circumstances change, they can choose another prescribed split day or take extra rest without rewriting the workout.',
    },
    {
        title: 'Does every check-in field have to be completed?',
        text: 'All check-in measurements, notes and pose photos are optional. Clients can save or submit partial check-ins, leaving you to follow up on what matters for their coaching.',
    },
    {
        title: 'What happens to records when coaching ends?',
        text: 'Your client keeps their account, logged history, published plans and reviews. Ending coaching removes your access to their records while preserving their progress.',
    },
];
</script>

<template>
    <Head title="Bodybuilding and powerlifting coaching software">
        <meta name="description" :content="description" />
        <meta
            property="og:title"
            content="Stage-Ready | Your coaching. Every detail. One place."
        />
        <meta property="og:description" :content="description" />
        <meta property="og:type" content="website" />
    </Head>
    <div class="public-page coach-home">
        <SiteHeader />
        <main>
            <section class="hero" aria-labelledby="home-title">
                <div class="hero-copy">
                    <p class="eyebrow">
                        <span class="status-dot" aria-hidden="true"></span>
                        BUILT FOR BODYBUILDING AND POWERLIFTING COACHES
                    </p>
                    <h1 id="home-title">
                        YOUR COACHING.<br />EVERY DETAIL.<br /><span
                            class="outline-text"
                            >ONE PLACE.</span
                        >
                    </h1>
                    <p class="intro">
                        Bring training, nutrition, check-ins and competition
                        prep into one connected workspace. Give your clients a
                        clear plan and make your next coaching decision with the
                        full picture.
                    </p>
                    <div class="hero-actions">
                        <a class="button" href="#features">
                            Explore coach features
                            <span aria-hidden="true">↓</span>
                        </a>
                        <Link class="text-link" href="/login">
                            Coach sign in <span aria-hidden="true">↗</span>
                        </Link>
                    </div>
                    <div class="hero-footnote">
                        <span>OFF-SEASON TO SHOW DAY</span>
                        <span>YOUR PLAN. THEIR PROGRESS.</span>
                    </div>
                </div>
                <AthleteVisual :src="athleteImages.hero" />
            </section>

            <div class="capability-strip" aria-label="Platform features">
                <span>TRAINING</span>
                <span>NUTRITION</span>
                <span>CHECK-INS</span>
                <span>POSING</span>
                <span>PROGRESS</span>
                <span>COMPETITIONS</span>
            </div>

            <section
                id="features"
                class="approach"
                aria-labelledby="features-title"
            >
                <div v-reveal class="section-heading">
                    <p class="eyebrow">YOUR COACHING WORKSPACE</p>
                    <h2 id="features-title">
                        Every detail.<br />A clearer decision.
                    </h2>
                    <p>
                        Stage-Ready keeps the work you prescribe beside the
                        progress your clients record, so your next adjustment
                        starts with context.
                    </p>
                </div>
                <div class="approach-grid feature-grid">
                    <article
                        v-for="(feature, index) in features"
                        :key="feature.label"
                        v-reveal
                    >
                        <p class="eyebrow feature-label">
                            <span>{{
                                String(index + 1).padStart(2, '0')
                            }}</span>
                            {{ feature.label }}
                        </p>
                        <h3>{{ feature.title }}</h3>
                        <p>{{ feature.text }}</p>
                    </article>
                </div>
            </section>

            <section class="prep-spotlight" aria-labelledby="prep-title">
                <AthleteVisual :src="athleteImages.detail" />
                <div v-reveal class="prep-copy">
                    <p class="eyebrow">BUILT FOR THE ROAD TO THE STAGE</p>
                    <h2 id="prep-title">Keep the whole<br />prep in view.</h2>
                    <p class="section-intro">
                        Training is one part of the picture. Keep the work
                        between sessions and the next show date in the same
                        coaching platform.
                    </p>
                    <ul class="prep-details">
                        <li>
                            <h3>Prescribe the daily work.</h3>
                            <p>
                                Set cardio, mandatory posing rounds and vacuum
                                sets and holds. Schedule future changes as prep
                                progresses.
                            </p>
                        </li>
                        <li>
                            <h3>Keep competition dates close.</h3>
                            <p>
                                View shows by client and filter upcoming or past
                                competitions. Record placing and show notes once
                                the show date has passed.
                            </p>
                        </li>
                        <li>
                            <h3>Give each client a clear plan.</h3>
                            <p>
                                Keep prescribed meals, training and practice
                                available in their workspace, with feedback they
                                can return to between check-ins.
                            </p>
                        </li>
                    </ul>
                </div>
            </section>

            <section
                id="how-it-works"
                class="approach workflow-section"
                aria-labelledby="workflow-title"
            >
                <div v-reveal class="section-heading">
                    <p class="eyebrow">HOW IT WORKS</p>
                    <h2 id="workflow-title">Your process.<br />Connected.</h2>
                    <p>
                        From the first invitation to the next review, give your
                        coaching a consistent home.
                    </p>
                </div>
                <ol class="approach-grid coaching-steps">
                    <li
                        v-for="(step, index) in steps"
                        :key="step.title"
                        v-reveal
                    >
                        <span class="step-number" aria-hidden="true">
                            {{ String(index + 1).padStart(2, '0') }}
                        </span>
                        <h3>{{ step.title }}</h3>
                        <p>{{ step.text }}</p>
                    </li>
                </ol>
            </section>

            <section class="home-questions" aria-labelledby="questions-title">
                <div v-reveal>
                    <p class="eyebrow">BEFORE YOUR NEXT CHECK-IN</p>
                    <h2 id="questions-title">
                        Made for the way<br />you coach.
                    </h2>
                </div>
                <div class="question-list">
                    <details
                        v-for="question in questions"
                        :key="question.title"
                    >
                        <summary>{{ question.title }}</summary>
                        <p>{{ question.text }}</p>
                    </details>
                </div>
            </section>

            <section
                v-reveal
                class="closing coach-closing"
                aria-labelledby="closing-title"
            >
                <p class="eyebrow">STAGE-READY</p>
                <h2 id="closing-title">
                    Your expertise.<br />A workspace to match.
                </h2>
                <p>
                    Bring your plans, your client feedback and your next
                    coaching decision together.
                </p>
                <div class="hero-actions">
                    <Link href="/login" class="button">
                        Sign in to your workspace
                        <span aria-hidden="true">↗</span>
                    </Link>
                    <a href="#features" class="text-link"
                        >Explore the features ↑</a
                    >
                </div>
            </section>
        </main>
        <footer class="site-footer">
            <span>STAGE-READY</span>
            <span
                >BUILT FOR BODYBUILDING AND POWERLIFTING COACHES AND THEIR
                CLIENTS.</span
            >
            <Link href="/login">Member login ↗</Link>
        </footer>
    </div>
</template>

<style scoped>
.coach-home {
    --section-accent: #fff;
}
.hero h1 {
    font-size: clamp(44px, 5.4vw, 88px);
    line-height: 1;
}
.hero .intro {
    max-width: 450px;
    padding-right: 20px;
}
.capability-strip {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 20px;
    padding: 28px 5.5%;
    border-top: 1px solid var(--line);
    font-size: 10px;
    letter-spacing: 0.12em;
    text-align: center;
}
#features,
#how-it-works {
    scroll-margin-top: 25px;
}
.feature-grid {
    row-gap: 46px;
}
.feature-label {
    display: flex;
    align-items: baseline;
    gap: 14px;
    margin-bottom: 0;
    font-size: 9px;
}
.feature-grid h3 {
    margin-top: 24px;
}
.feature-grid p:last-child {
    max-width: 340px;
}
.prep-spotlight {
    display: grid;
    grid-template-columns: 1fr 1fr;
    border-top: 1px solid var(--line);
}
.prep-spotlight :deep(.athlete-visual) {
    min-height: 700px;
}
.prep-spotlight :deep(.athlete-caption) {
    display: none;
}
.prep-copy {
    padding: 80px 11% 80px 5%;
}
.prep-copy h2,
.home-questions h2 {
    margin: 0;
    font-size: clamp(34px, 4vw, 55px);
    font-weight: 600;
    line-height: 1.05;
    letter-spacing: -0.045em;
}
.section-intro,
.prep-details p,
.question-list p,
.coach-closing > p:not(.eyebrow) {
    font-size: 13px;
    line-height: 1.9;
}
.section-intro {
    margin: 24px 0 32px;
    max-width: 430px;
}
.prep-details,
.coaching-steps {
    list-style: none;
    padding: 0;
}
.prep-details {
    margin: 0;
}
.prep-details li {
    padding: 22px 0;
    border-top: 1px solid var(--line);
}
.prep-details h3 {
    margin: 0 0 8px;
    font-size: 17px;
    font-weight: 500;
}
.prep-details p {
    margin: 0;
    max-width: 430px;
}
.coaching-steps {
    margin-bottom: 0;
}
.coaching-steps li {
    min-width: 0;
    border-top: 1px solid #444;
    padding-top: 25px;
}
.step-number {
    font-size: 36px;
    font-weight: 500;
    letter-spacing: -0.04em;
}
.home-questions {
    display: grid;
    grid-template-columns: 1fr 1.3fr;
    gap: 70px;
    padding: 80px 5.5%;
    border-top: 1px solid var(--line);
}
.question-list details {
    border-top: 1px solid var(--line);
}
.question-list details:last-child {
    border-bottom: 1px solid var(--line);
}
.question-list summary {
    padding: 24px 0;
    cursor: pointer;
    font-size: 15px;
    line-height: 1.6;
}
.question-list summary:focus-visible {
    outline: 2px solid #fff;
    outline-offset: 4px;
}
.question-list p {
    margin: 0 0 24px;
    max-width: 560px;
}
.coach-closing {
    border-top: 1px solid var(--line);
    padding-top: 80px;
    padding-bottom: 80px;
    background: linear-gradient(115deg, #141414, var(--ink) 75%);
}
.coach-closing > p:not(.eyebrow) {
    max-width: 450px;
    margin: 0;
}
.coach-closing .button {
    margin-top: 0;
}
.site-footer {
    border-top: 1px solid var(--line);
    align-items: center;
    line-height: 1.8;
}
@media (max-width: 1000px) {
    .feature-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 35px;
    }
    .prep-copy {
        padding: 65px 11% 65px 0;
    }
    .home-questions {
        gap: 35px;
    }
}
@media (max-width: 720px) {
    .hero h1 {
        font-size: clamp(36px, 8.8vw, 64px);
    }
    .hero .intro {
        padding-right: 0;
    }
    .capability-strip {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 22px 12px;
        padding: 28px 6%;
        font-size: 9px;
    }
    .feature-grid {
        grid-template-columns: 1fr;
        gap: 30px;
    }
    .feature-grid p:last-child {
        max-width: none;
    }
    .prep-spotlight {
        grid-template-columns: 1fr;
    }
    .prep-spotlight :deep(.athlete-visual) {
        min-height: 280px;
    }
    .prep-copy {
        padding: 25px 6% 55px;
    }
    .home-questions {
        grid-template-columns: 1fr;
        gap: 35px;
        padding: 55px 6%;
    }
    .coach-closing {
        padding-top: 55px;
        padding-bottom: 55px;
    }
}
</style>
