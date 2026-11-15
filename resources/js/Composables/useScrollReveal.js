import { onBeforeUnmount, onMounted, ref } from 'vue';

export function useScrollReveal(targetRef, options = {}) {
    const isRevealed = ref(true);
    let observer = null;

    onMounted(() => {
        if (typeof window === 'undefined' || !targetRef.value) {
            isRevealed.value = true;
            return;
        }

        const reducedMotion =
            typeof window.matchMedia === 'function' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reducedMotion || typeof IntersectionObserver === 'undefined') {
            isRevealed.value = true;
            return;
        }

        const rect = targetRef.value.getBoundingClientRect();
        if (rect.top < window.innerHeight * 0.92) {
            isRevealed.value = true;
            return;
        }

        isRevealed.value = false;

        observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        isRevealed.value = true;
                        observer?.disconnect();
                        break;
                    }
                }
            },
            {
                threshold: options.threshold ?? 0.12,
                rootMargin: options.rootMargin ?? '0px 0px -32px 0px',
            },
        );

        observer.observe(targetRef.value);
    });

    onBeforeUnmount(() => {
        observer?.disconnect();
    });

    return { isRevealed };
}
