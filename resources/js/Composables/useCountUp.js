import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

export function useCountUp(targetRef, targetValue, durationMs = 650) {
    const displayValue = ref(Number(targetValue.value ?? targetValue) || 0);
    let observer = null;
    let frameId = null;
    let hasAnimated = false;

    const runAnimation = (endVal) => {
        if (typeof window === 'undefined' || endVal <= 0) {
            displayValue.value = endVal;
            return;
        }

        const reducedMotion =
            typeof window.matchMedia === 'function' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (reducedMotion) {
            displayValue.value = endVal;
            return;
        }

        const startTime = performance.now();

        const step = (now) => {
            const elapsed = now - startTime;
            const progress = Math.min(elapsed / durationMs, 1);
            const eased = 1 - Math.pow(1 - progress, 3);
            displayValue.value = Math.round(endVal * eased);

            if (progress < 1) {
                frameId = window.requestAnimationFrame(step);
            } else {
                displayValue.value = endVal;
            }
        };

        displayValue.value = 0;
        frameId = window.requestAnimationFrame(step);
    };

    onMounted(() => {
        const endVal = Number(targetValue.value ?? targetValue) || 0;

        if (typeof window === 'undefined' || !targetRef.value || typeof IntersectionObserver === 'undefined') {
            displayValue.value = endVal;
            return;
        }

        observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting && !hasAnimated) {
                        hasAnimated = true;
                        runAnimation(endVal);
                        observer?.disconnect();
                        break;
                    }
                }
            },
            { threshold: 0.25 },
        );

        observer.observe(targetRef.value);
    });

    watch(
        () => Number(targetValue.value ?? targetValue) || 0,
        (nextVal) => {
            if (!hasAnimated) {
                displayValue.value = nextVal;
            }
        },
    );

    onBeforeUnmount(() => {
        observer?.disconnect();
        if (frameId !== null && typeof window !== 'undefined') {
            window.cancelAnimationFrame(frameId);
        }
    });

    return { displayValue };
}
