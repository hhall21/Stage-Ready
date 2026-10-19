<script setup lang="ts">
import type { JordanPetersQuote } from '@/lib/jordan-peters-quotes';

withDefaults(
    defineProps<{
        quote: JordanPetersQuote;
        variant?: 'intro' | 'display' | 'compact';
    }>(),
    { variant: 'intro' },
);
</script>

<template>
    <figure class="jordan-quote" :class="`jordan-quote--${variant}`">
        <blockquote :cite="quote.source">“{{ quote.text }}”</blockquote>
        <figcaption>
            <a :href="quote.source" target="_blank" rel="noopener noreferrer">
                Jordan Peters <span aria-hidden="true">↗</span>
                <span class="sr-only">
                    — quote source (opens in a new tab)</span
                >
            </a>
        </figcaption>
    </figure>
</template>

<style scoped>
.jordan-quote {
    margin: 28px 0;
    max-width: 345px;
    color: #fff;
}

blockquote {
    margin: 0;
    font-size: 15px;
    line-height: 1.8;
    text-wrap: pretty;
}

figcaption {
    margin-top: 12px;
    font-size: 11px;
    line-height: 1.6;
}

a {
    color: inherit;
    text-underline-offset: 4px;
}

a:hover,
a:focus-visible {
    text-decoration: underline;
}

.jordan-quote--display {
    margin: 20px 0 28px;
    max-width: 20ch;
    font-size: clamp(30px, 3.7vw, 48px);
}

.jordan-quote--display blockquote {
    font-size: inherit;
    font-weight: 500;
    letter-spacing: -0.045em;
    line-height: 1.15;
}

.jordan-quote--compact {
    margin: 18px 0 0;
}

.jordan-quote--compact blockquote {
    font-size: 22px;
    font-weight: 500;
    letter-spacing: -0.03em;
    line-height: 1.35;
}
</style>
