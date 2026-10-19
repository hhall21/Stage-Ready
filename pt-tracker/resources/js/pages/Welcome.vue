<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AthleteVisual from '@/components/AthleteVisual.vue';
import JordanPetersQuote from '@/components/JordanPetersQuote.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import { athleteImages } from '@/lib/athlete-images';
import { jordanPetersQuotes } from '@/lib/jordan-peters-quotes';
</script>
<template>
    <Head title="Training with purpose" />
    <div class="public-page">
        <SiteHeader />
        <main>
            <section class="hero">
                <div class="hero-copy">
                    <p class="eyebrow">
                        <span class="status-dot"></span> PERSONAL TRAINING /
                        REAL PROGRESS
                    </p>
                    <h1>
                        BUILD YOUR<br />STRONGEST<br /><span
                            class="outline-text"
                            >SELF.</span
                        >
                    </h1>
                    <p class="intro">
                        A clear plan. Consistent effort. Coaching that keeps you
                        moving forward.
                    </p>
                    <div class="hero-actions">
                        <Link class="button" href="/login"
                            >Enter your training space
                            <span aria-hidden="true">↗</span></Link
                        ><a class="text-link" href="#approach"
                            >Explore the approach ↓</a
                        >
                    </div>
                    <div class="hero-footnote">
                        <span>YOUR TRAINING. YOUR PROGRESS.</span
                        ><span>ALL IN ONE PLACE.</span>
                    </div>
                </div>
                <AthleteVisual :src="athleteImages.hero" />
            </section>
            <section id="approach" class="approach">
                <div class="section-heading">
                    <p class="eyebrow">THE APPROACH</p>
                    <h2>Every detail.<br />One direction.</h2>
                    <p>
                        Keep your training and daily habits connected, with your
                        coach alongside you.
                    </p>
                </div>
                <div class="approach-grid">
                    <article>
                        <span class="number">01</span>
                        <h3>Train with intent.</h3>
                        <p>
                            Your workouts, exercises and sets in one focused
                            training space.
                        </p>
                    </article>
                    <article>
                        <span class="number">02</span>
                        <h3>Know your progress.</h3>
                        <p>
                            Log your weight, recovery and daily habits. See how
                            your consistency adds up.
                        </p>
                    </article>
                    <article>
                        <span class="number">03</span>
                        <h3>Stay connected.</h3>
                        <p>
                            Give your coach a clear view of your training and
                            check-ins.
                        </p>
                    </article>
                </div>
            </section>
            <section class="closing">
                <div class="athlete-gallery">
                    <AthleteVisual :src="athleteImages.detail" />
                    <AthleteVisual :src="athleteImages.front" />
                </div>
                <p class="eyebrow">TRAINING PERSPECTIVE</p>
                <JordanPetersQuote
                    :quote="jordanPetersQuotes.ownPath"
                    variant="display"
                />
                <Link href="/login" class="button"
                    >Let's get to work <span aria-hidden="true">↗</span></Link
                >
            </section>
        </main>
        <footer class="site-footer">
            <span>STAGE-READY</span><span>TRAINING. WITH PURPOSE.</span
            ><Link href="/login">Member login ↗</Link>
        </footer>
    </div>
</template>
