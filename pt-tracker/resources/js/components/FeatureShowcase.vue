<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { vReveal } from '@/lib/motion';

const props = defineProps<{
    features: { category: string; title: string; text: string }[];
}>();
const section = ref<HTMLElement | null>(null);
const enhanced = ref(false);
const pinned = ref(false);
const completed = ref(0);
let cleanup: (() => void) | undefined;

onMounted(() => {
    const root = section.value;
    if (!root) return;

    const content = root.querySelector<HTMLElement>('.feature-content');
    const cards = Array.from(
        root.querySelectorAll<HTMLElement>('.feature-card'),
    );
    const header = root
        .closest('.coach-home')
        ?.querySelector<HTMLElement>('.site-header');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const clamp = (value: number) => Math.min(1, Math.max(0, value));
    const stepDistance = 240;
    const revealDistance = 180;
    const holdDistance = cards.length * stepDistance + 120;
    let frame = 0;

    const update = () => {
        frame = 0;
        const headerHeight = header?.offsetHeight || 106;
        const viewportSpace = Math.max(1, window.innerHeight - headerHeight);
        const contentHeight = content?.offsetHeight || 0;
        const bounds = root.getBoundingClientRect();
        const cardBounds = cards.map((card) => card.getBoundingClientRect());
        const canPin =
            !reducedMotion.matches &&
            window.innerWidth > 720 &&
            contentHeight > 0 &&
            contentHeight <= viewportSpace - 32;
        const pinOffset = headerHeight + 16 - bounds.top;
        let count = 0;

        pinned.value = canPin;
        enhanced.value = !reducedMotion.matches;
        root.style.setProperty(
            '--feature-sticky-top',
            `${headerHeight + 16}px`,
        );
        root.style.setProperty(
            '--feature-content-height',
            `${contentHeight}px`,
        );
        root.style.setProperty(
            '--feature-hold-distance',
            canPin ? `${holdDistance}px` : '0px',
        );

        cards.forEach((card, index) => {
            const progress = reducedMotion.matches
                ? 1
                : canPin
                  ? clamp((pinOffset - index * stepDistance) / revealDistance)
                  : clamp(
                        (window.innerHeight -
                            cardBounds[index].top -
                            viewportSpace * 0.08) /
                            Math.min(180, viewportSpace * 0.25),
                    );
            card.style.setProperty('--feature-reveal', progress.toFixed(3));
            if (progress >= 1) count++;
        });
        completed.value = count;
    };
    const scheduleUpdate = () => {
        if (!frame) frame = window.requestAnimationFrame(update);
    };

    update();
    window.addEventListener('scroll', scheduleUpdate, { passive: true });
    window.addEventListener('resize', scheduleUpdate);
    reducedMotion.addEventListener('change', scheduleUpdate);
    const observer =
        typeof ResizeObserver === 'undefined'
            ? undefined
            : new ResizeObserver(scheduleUpdate);
    if (content) observer?.observe(content);
    if (header) observer?.observe(header);
    cleanup = () => {
        window.cancelAnimationFrame(frame);
        window.removeEventListener('scroll', scheduleUpdate);
        window.removeEventListener('resize', scheduleUpdate);
        reducedMotion.removeEventListener('change', scheduleUpdate);
        observer?.disconnect();
    };
});

onUnmounted(() => cleanup?.());
</script>

<template>
    <section
        id="features"
        ref="section"
        class="feature-showcase"
        :class="{ 'features-pinned': pinned, 'features-enhanced': enhanced }"
        aria-labelledby="features-title"
    >
        <div class="feature-content">
            <div v-reveal class="feature-intro">
                <p class="feature-label">The coaching workspace</p>
                <h2 id="features-title">
                    Review the plan alongside the results.
                </h2>
                <p class="feature-description">
                    Training, nutrition and check-ins share the same client
                    record, so you can review them together.
                </p>
                <div class="feature-cue" aria-hidden="true">
                    <div class="feature-progress">
                        <span
                            v-for="(_, index) in features"
                            :key="index"
                            :class="{ 'is-complete': completed > index }"
                        ></span>
                    </div>
                    <p>
                        {{ completed }} / {{ props.features.length }}
                        <span>{{
                            completed < props.features.length
                                ? 'Scroll to explore'
                                : 'Coaching tools'
                        }}</span>
                    </p>
                </div>
            </div>
            <div class="feature-list">
                <article
                    v-for="(feature, index) in features"
                    :key="feature.category"
                    class="feature-card"
                    :style="{ '--feature-direction': index % 2 === 0 ? 1 : -1 }"
                >
                    <div>
                        <p class="feature-category">
                            <span>{{
                                String(index + 1).padStart(2, '0')
                            }}</span>
                            {{ feature.category }}
                        </p>
                        <h3>{{ feature.title }}</h3>
                    </div>
                    <p>{{ feature.text }}</p>
                </article>
            </div>
        </div>
    </section>
</template>

<style scoped>
.feature-showcase {
    --feature-reveal: 1;
    position: relative;
    border-top: 1px solid var(--line);
    scroll-margin-top: calc(var(--home-header-height, 106px) + 24px);
}
.features-pinned {
    min-height: calc(
        var(--feature-content-height) + var(--feature-hold-distance)
    );
}
.features-pinned .feature-content {
    position: sticky;
    top: var(--feature-sticky-top);
}
.feature-content {
    display: grid;
    grid-template-columns: minmax(0, 0.75fr) minmax(0, 1.45fr);
    align-items: start;
    gap: clamp(32px, 5vw, 80px);
    padding: 48px var(--home-gutter, 5.5vw);
}
.feature-label {
    margin: 0 0 16px;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.6;
}
.feature-intro h2 {
    max-width: 20ch;
    margin: 0;
    font-size: clamp(28px, 3vw, 44px);
    font-weight: 600;
    line-height: 1.15;
    letter-spacing: -0.035em;
    text-wrap: balance;
}
.feature-description {
    max-width: 36ch;
    margin: 24px 0 0;
    font-size: 16px;
    line-height: 1.75;
}
.feature-cue {
    margin-top: 36px;
    color: rgb(var(--dropdown-flash));
    font-size: 12px;
    visibility: hidden;
}
.features-pinned .feature-cue {
    visibility: visible;
}
.feature-progress {
    display: flex;
    gap: 8px;
    max-width: 180px;
}
.feature-progress span {
    flex: 1;
    height: 2px;
    background: rgb(var(--dropdown-blue) / 22%);
    transition: background-color 0.2s;
}
.feature-progress .is-complete {
    background: rgb(var(--dropdown-blue));
    box-shadow:
        var(--glow-core),
        0 0 12px rgb(var(--dropdown-blue) / calc(40% * var(--glow-strength)));
}
.feature-cue p {
    display: flex;
    gap: 16px;
    margin: 14px 0 0;
}
.feature-list {
    display: grid;
    gap: 14px;
    min-width: 0;
    margin: -22px;
    padding: 22px;
    overflow: clip;
}
.feature-card {
    position: relative;
    display: grid;
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    align-items: start;
    gap: 20px;
    min-width: 0;
    padding: 18px 20px;
    overflow: hidden;
    border: 1px solid
        rgb(var(--dropdown-blue) / calc(34% * var(--glow-strength)));
    border-radius: 12px;
    background: linear-gradient(
        135deg,
        rgb(var(--dropdown-blue) / calc(13% * var(--glow-strength))),
        rgb(var(--dropdown-blue) / calc(5% * var(--glow-strength)))
    );
    box-shadow:
        var(--glow-core),
        0 0 26px rgb(var(--dropdown-blue) / calc(13% * var(--glow-strength))),
        inset 0 0 22px
            rgb(var(--dropdown-blue) / calc(3% * var(--glow-strength)));
}
.features-enhanced .feature-card {
    opacity: var(--feature-reveal, 1);
    transform: translate3d(
        calc((1 - var(--feature-reveal, 1)) * var(--feature-direction) * 64px),
        0,
        0
    );
}
.features-enhanced .feature-card::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background: linear-gradient(
        100deg,
        transparent 20%,
        rgb(var(--dropdown-flash) / calc(9% * var(--glow-strength))) 45%,
        transparent 70%
    );
    transform: translateX(calc(-130% + var(--feature-reveal, 1) * 270%));
}
.feature-category {
    display: flex;
    gap: 12px;
    margin: 0 0 8px;
    color: rgb(var(--dropdown-flash));
    font-size: 12px;
    line-height: 1.6;
}
.feature-category span {
    color: rgb(var(--dropdown-blue));
}
.feature-card h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 500;
    line-height: 1.35;
    letter-spacing: -0.02em;
}
.feature-card > p {
    margin: 0;
    font-size: 14px;
    line-height: 1.65;
}
@media (max-width: 1000px) {
    .feature-content {
        grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr);
        gap: 32px;
    }
    .feature-card {
        grid-template-columns: 1fr;
        gap: 14px;
    }
    .feature-card > p {
        font-size: 15px;
        line-height: 1.75;
    }
}
@media (max-width: 720px) {
    .feature-content {
        grid-template-columns: 1fr;
        gap: 32px;
    }
    .feature-cue {
        display: none;
    }
}
@media (prefers-reduced-motion: reduce) {
    .features-pinned {
        min-height: 0;
    }
    .features-pinned .feature-content {
        position: static;
    }
    .features-enhanced .feature-card {
        opacity: 1;
        transform: none;
    }
    .features-enhanced .feature-card::before {
        display: none;
    }
}
</style>
