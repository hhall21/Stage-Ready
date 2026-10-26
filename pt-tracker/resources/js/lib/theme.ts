import { ref } from 'vue';

type Theme = 'dark' | 'light';

export const theme = ref<Theme>(
    typeof document !== 'undefined' &&
        document.documentElement.dataset.theme === 'light'
        ? 'light'
        : 'dark',
);

export function toggleTheme(): void {
    theme.value = theme.value === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = theme.value;
    try {
        localStorage.setItem('stage-ready-theme', theme.value);
    } catch {
        // Switching still works when browser storage is unavailable.
    }
}
