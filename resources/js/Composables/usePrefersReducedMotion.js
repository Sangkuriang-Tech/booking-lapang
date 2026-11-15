import { onBeforeUnmount, onMounted, ref } from 'vue';

export function usePrefersReducedMotion() {
    const prefersReducedMotion = ref(false);
    let mediaQuery = null;

    const updatePreference = (event) => {
        prefersReducedMotion.value = event.matches;
    };

    onMounted(() => {
        if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
            return;
        }

        mediaQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        prefersReducedMotion.value = mediaQuery.matches;

        if (typeof mediaQuery.addEventListener === 'function') {
            mediaQuery.addEventListener('change', updatePreference);
        }
    });

    onBeforeUnmount(() => {
        if (mediaQuery && typeof mediaQuery.removeEventListener === 'function') {
            mediaQuery.removeEventListener('change', updatePreference);
        }
    });

    return { prefersReducedMotion };
}
