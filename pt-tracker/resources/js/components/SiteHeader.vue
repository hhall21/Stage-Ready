<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { theme, toggleTheme } from '@/lib/theme';
withDefaults(defineProps<{ dashboard?: boolean; skipTarget?: string }>(), {
    dashboard: false,
    skipTarget: undefined,
});
const page = usePage();
const themeLabel = computed(() =>
    theme.value === 'light' ? 'Switch to dark mode' : 'Switch to light mode',
);
</script>
<template>
    <header class="site-header" :class="{ 'signed-in': page.props.auth.user }">
        <a v-if="skipTarget" class="skip-link" :href="skipTarget">
            Skip to main content
        </a>
        <Link href="/" class="brand" aria-label="Stage-Ready home"
            ><span class="brand-mark">SR</span
            ><span
                >STAGE-READY<span class="brand-sub"
                    >COACHING WORKSPACE</span
                ></span
            ></Link
        >
        <nav aria-label="Main navigation">
            <span v-if="page.props.auth.user" class="header-user">
                <span class="sr-only">Signed in as </span>
                {{ page.props.auth.user.name }}
            </span>
            <template v-if="!dashboard"
                ><a href="/#features">Features</a
                ><a href="/#how-it-works">How it works</a
                ><Link v-if="!page.props.auth.user" href="/register"
                    >Join as a coach</Link
                ><Link
                    class="button button-small"
                    :href="page.props.auth.navigation.href"
                    >{{ page.props.auth.navigation.label }}</Link
                ></template
            >
            <template v-else>
                <Link v-if="page.props.auth.user?.is_admin" href="/admin"
                    >Admin portal</Link
                >
                <slot />
            </template>
        </nav>
        <button
            type="button"
            class="button button-small theme-toggle"
            :aria-label="themeLabel"
            :title="themeLabel"
            @click="toggleTheme"
        >
            <svg
                width="20"
                height="20"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <template v-if="theme === 'dark'">
                    <circle cx="12" cy="12" r="4" />
                    <path
                        d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5"
                    />
                </template>
                <path
                    v-else
                    d="M20.5 13.5A8.5 8.5 0 0 1 10.5 3.5a8.5 8.5 0 1 0 10 10Z"
                />
            </svg>
            <span class="theme-toggle-label">{{
                theme === 'light' ? 'Dark mode' : 'Light mode'
            }}</span>
        </button>
    </header>
</template>

<style scoped>
.site-header {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
}
.site-header nav {
    justify-content: flex-end;
    min-width: 0;
}
.theme-toggle {
    justify-self: end;
    gap: 8px;
    min-width: 44px;
    padding: 10px 12px;
}
.theme-toggle svg {
    flex-shrink: 0;
}
.skip-link {
    position: absolute;
    top: 8px;
    left: 16px;
    z-index: 100;
    padding: 12px 16px;
    background: var(--ink);
    border: 1px solid #fff;
    transform: translateY(-200%);
}
.skip-link:focus {
    transform: translateY(0);
}
.header-user {
    max-width: 28ch;
    overflow-wrap: anywhere;
    text-align: right;
    font-weight: 500;
    line-height: 1.5;
}
.signed-in nav {
    flex-wrap: wrap;
    justify-content: flex-end;
    min-width: 0;
}
@media (max-width: 1000px) {
    .theme-toggle-label {
        display: none;
    }
}
@media (max-width: 720px) {
    .site-header {
        grid-template-columns: minmax(0, 1fr) auto;
        flex-wrap: wrap;
        gap: 16px;
    }
    .theme-toggle {
        grid-column: 2;
        grid-row: 1;
    }
    .site-header nav {
        grid-column: 1 / -1;
        grid-row: 2;
        flex: 1 1 100%;
        flex-wrap: wrap;
        gap: 8px 24px;
        justify-content: flex-start;
    }
    .site-header nav > a {
        display: inline-flex;
        align-items: center;
        min-height: 44px;
    }
    .site-header nav > .button {
        margin-left: auto;
    }
    .signed-in {
        flex-wrap: wrap;
        row-gap: 16px;
    }
    .signed-in nav {
        flex: 1 1 100%;
    }
    .header-user {
        max-width: min(28ch, 100%);
    }
}
</style>
