import type { ObjectDirective } from 'vue';

const cleanups = new WeakMap<HTMLElement, () => void>();

export function unveilContent(element: HTMLElement): void {
    element.getAnimations().forEach((animation) => animation.cancel());
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    element.animate(
        [
            {
                height: '0px',
                opacity: 0,
                overflow: 'hidden',
                transform: 'translateY(-8px)',
            },
            {
                height: `${element.offsetHeight}px`,
                opacity: 1,
                overflow: 'hidden',
                transform: 'translateY(0)',
            },
        ],
        { duration: 450, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' },
    );
}

export function motionScrollBehavior(): ScrollBehavior {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ? 'auto'
        : 'smooth';
}

export const vReveal: ObjectDirective<HTMLElement> = {
    mounted(element) {
        if (
            window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
            !('IntersectionObserver' in window)
        )
            return;

        const reveal = () => {
            element.classList.add('is-revealed');
            observer.disconnect();
            element.removeEventListener('focusin', reveal);
        };
        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) reveal();
            },
            { threshold: 0.08 },
        );

        observer.observe(element);
        element.classList.add('reveal-on-scroll');
        element.addEventListener('focusin', reveal);
        cleanups.set(element, () => {
            observer.disconnect();
            element.removeEventListener('focusin', reveal);
        });
    },
    beforeUnmount(element) {
        cleanups.get(element)?.();
        cleanups.delete(element);
    },
};
