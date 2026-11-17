<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'section',
        validator: (value) => ['hero', 'section', 'footer'].includes(value),
    },
});

const rootRef = ref(null);
const parallaxOffset = ref(0);
let rafId = null;

const imageOpacityClass = computed(() => {
    if (props.variant === 'hero') {
        return 'opacity-[0.14]';
    }

    if (props.variant === 'footer') {
        return 'opacity-[0.07]';
    }

    return 'opacity-[0.09]';
});

const overlayClass = computed(() => {
    if (props.variant === 'hero') {
        return 'bg-gradient-to-b from-surface/85 via-surface/55 to-canvas/95';
    }

    if (props.variant === 'footer') {
        return 'bg-gradient-to-t from-surface/95 via-surface/80 to-surface/90';
    }

    return 'bg-gradient-to-b from-surface/90 via-surface/72 to-surface/92';
});

function updateParallax() {
    rafId = null;
    if (typeof window === 'undefined' || !rootRef.value) {
        return;
    }

    if (props.variant === 'hero') {
        parallaxOffset.value = Math.min(window.scrollY * 0.18, 64);
        return;
    }

    const rect = rootRef.value.getBoundingClientRect();
    const viewportCenter = window.innerHeight / 2;
    const elementCenter = rect.top + rect.height / 2;
    const delta = (viewportCenter - elementCenter) * 0.08;
    parallaxOffset.value = Math.max(-36, Math.min(36, delta));
}

function onScroll() {
    if (rafId !== null) {
        return;
    }

    rafId = window.requestAnimationFrame(updateParallax);
}

onMounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    updateParallax();
    window.addEventListener('scroll', onScroll, { passive: true });
});

onUnmounted(() => {
    if (typeof window !== 'undefined') {
        window.removeEventListener('scroll', onScroll);
        if (rafId !== null) {
            window.cancelAnimationFrame(rafId);
        }
    }
});
</script>

<template>
    <div ref="rootRef" aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
        <img
            src="/images/hero-sport-bg.jpg"
            alt=""
            width="1440"
            height="900"
            loading="lazy"
            decoding="async"
            class="h-[124%] w-full -translate-y-[10%] object-cover object-center will-change-transform"
            :class="imageOpacityClass"
            :style="{ transform: `translate3d(0, ${parallaxOffset}px, 0) scale(1.06)` }"
        />
        <div class="absolute inset-0" :class="overlayClass" />

        <svg
            viewBox="0 0 1440 600"
            preserveAspectRatio="none"
            class="absolute inset-0 h-full w-full"
            fill="none"
        >
            <defs>
                <linearGradient :id="`futuristic-line-${variant}`" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#0F3B2E" stop-opacity="0.04" />
                    <stop offset="50%" stop-color="#0F3B2E" stop-opacity="0.26" />
                    <stop offset="100%" stop-color="#D4F34A" stop-opacity="0.42" />
                </linearGradient>
                <linearGradient :id="`futuristic-accent-${variant}`" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#1F7A4D" stop-opacity="0.08" />
                    <stop offset="50%" stop-color="#D4F34A" stop-opacity="0.5" />
                    <stop offset="100%" stop-color="#0F3B2E" stop-opacity="0.1" />
                </linearGradient>
            </defs>

            <!-- Subtle perspective grid -->
            <path d="M0 140 H1440 M0 280 H1440 M0 420 H1440 M0 540 H1440" stroke="#0F3B2E" stroke-opacity="0.045" stroke-width="1" />
            <path d="M240 0 L140 600 M520 0 L480 600 M920 0 L960 600 M1200 0 L1300 600" stroke="#0F3B2E" stroke-opacity="0.04" stroke-width="1" />

            <!-- Base static telemetry curves -->
            <path
                d="M-40 490 C 210 420, 380 510, 620 390 C 860 270, 1080 350, 1480 180"
                stroke="#0F3B2E"
                stroke-opacity="0.08"
                stroke-width="1.5"
            />
            <path
                d="M-40 535 C 260 470, 480 555, 760 440 C 1020 330, 1210 385, 1480 250"
                stroke="#1F7A4D"
                stroke-opacity="0.07"
                stroke-width="1.25"
            />

            <!-- Moving futuristic data lines -->
            <path
                d="M-40 490 C 210 420, 380 510, 620 390 C 860 270, 1080 350, 1480 180"
                :stroke="`url(#futuristic-line-${variant})`"
                stroke-width="2.25"
                stroke-linecap="round"
                class="futuristic-line-flow"
            />
            <path
                d="M-40 535 C 260 470, 480 555, 760 440 C 1020 330, 1210 385, 1480 250"
                :stroke="`url(#futuristic-accent-${variant})`"
                stroke-width="2"
                stroke-linecap="round"
                class="futuristic-line-flow-reverse"
            />
            <path
                v-if="variant !== 'footer'"
                d="M-40 210 C 220 150, 460 250, 740 165 C 1010 85, 1220 155, 1480 75"
                :stroke="`url(#futuristic-line-${variant})`"
                stroke-width="1.5"
                stroke-linecap="round"
                class="futuristic-line-flow"
            />

            <!-- Pulsing telemetry nodes -->
            <circle cx="620" cy="390" r="4.5" fill="#1F7A4D" class="futuristic-pulse-dot" />
            <circle cx="960" cy="312" r="4" fill="#D4F34A" class="futuristic-pulse-dot" style="animation-delay: 1.1s" />
            <circle v-if="variant !== 'footer'" cx="740" cy="165" r="3.5" fill="#0F3B2E" class="futuristic-pulse-dot" style="animation-delay: 2.2s" />
        </svg>
    </div>
</template>

