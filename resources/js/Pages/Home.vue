<script setup>
import { computed, defineAsyncComponent, h, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Skeleton from '@/Components/UI/Skeleton.vue';
import { useCountUp } from '@/Composables/useCountUp';
import { formatNumber } from '@/Utils/format';

const props = defineProps({
    sports: {
        type: Array,
        default: () => [],
    },
    cities: {
        type: Array,
        default: () => [],
    },
    featuredVenues: {
        type: Array,
        default: () => [],
    },
    plans: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({
            venues_count: 0,
            courts_count: 0,
            sports_count: 0,
        }),
    },
    seo: {
        type: Object,
        default: () => ({
            canonical: '',
            siteUrl: '',
            siteName: 'ArenaKita',
        }),
    },
});

const sectionFallback = {
    render() {
        return h('div', { class: 'mx-auto max-w-6xl px-4 py-12 sm:px-6' }, [
            h(Skeleton, { variant: 'card', height: '14rem' }),
        ]);
    },
};

const SportCategoriesSection = defineAsyncComponent({
    loader: () => import('@/Components/Landing/SportCategoriesSection.vue'),
    loadingComponent: sectionFallback,
});

const HowItWorksSection = defineAsyncComponent({
    loader: () => import('@/Components/Landing/HowItWorksSection.vue'),
    loadingComponent: sectionFallback,
});

const FeaturedVenuesSection = defineAsyncComponent({
    loader: () => import('@/Components/Landing/FeaturedVenuesSection.vue'),
    loadingComponent: sectionFallback,
});

const OwnerPlansSection = defineAsyncComponent({
    loader: () => import('@/Components/Landing/OwnerPlansSection.vue'),
    loadingComponent: sectionFallback,
});

const FaqSection = defineAsyncComponent({
    loader: () => import('@/Components/Landing/FaqSection.vue'),
    loadingComponent: sectionFallback,
});

const selectedSport = ref('');
const selectedCity = ref('');
const isSearching = ref(false);

const statsRef = ref(null);
const venuesTarget = computed(() => props.stats.venues_count ?? 0);
const courtsTarget = computed(() => props.stats.courts_count ?? 0);
const sportsTarget = computed(() => props.stats.sports_count ?? 0);

const { displayValue: venuesCountDisplay } = useCountUp(statsRef, venuesTarget);
const { displayValue: courtsCountDisplay } = useCountUp(statsRef, courtsTarget);
const { displayValue: sportsCountDisplay } = useCountUp(statsRef, sportsTarget);

const pageTitle = 'Booking Lapangan Olahraga Cepat & Anti Bentrok';
const metaDescription =
    'Cari dan pesan lapangan futsal, mini soccer, badminton, dan olahraga lainnya secara real-time di ArenaKita. Tanpa double booking, pembayaran transparan, dan check-in QR.';

const jsonLdPayload = computed(() => {
    const siteUrl = props.seo.siteUrl || 'http://127.0.0.1:8000';
    const siteName = props.seo.siteName || 'ArenaKita';

    return JSON.stringify({
        '@context': 'https://schema.org',
        '@graph': [
            {
                '@type': 'WebSite',
                '@id': `${siteUrl}/#website`,
                url: `${siteUrl}/`,
                name: siteName,
                inLanguage: 'id-ID',
                potentialAction: {
                    '@type': 'SearchAction',
                    target: `${siteUrl}/venues?sport={sport}&city={city}`,
                    'query-input': 'required name=sport required name=city',
                },
            },
            {
                '@type': 'Organization',
                '@id': `${siteUrl}/#organization`,
                name: siteName,
                url: `${siteUrl}/`,
            },
            {
                '@type': 'BreadcrumbList',
                itemListElement: [
                    {
                        '@type': 'ListItem',
                        position: 1,
                        name: 'Beranda',
                        item: `${siteUrl}/`,
                    },
                ],
            },
        ],
    });
});

function submitSearch() {
    if (isSearching.value) {
        return;
    }

    isSearching.value = true;
    router.get(
        '/venues',
        {
            sport: selectedSport.value || undefined,
            city: selectedCity.value || undefined,
        },
        {
            onFinish: () => {
                isSearching.value = false;
            },
        },
    );
}
</script>

<template>
    <Head>
        <title>{{ pageTitle }}</title>
        <meta name="description" :content="metaDescription" />
        <link v-if="seo.canonical" rel="canonical" :href="seo.canonical" />
        <meta property="og:type" content="website" />
        <meta property="og:locale" content="id_ID" />
        <meta property="og:site_name" :content="seo.siteName || 'ArenaKita'" />
        <meta property="og:title" :content="`${pageTitle} - ${seo.siteName || 'ArenaKita'}`" />
        <meta property="og:description" :content="metaDescription" />
        <meta v-if="seo.canonical" property="og:url" :content="seo.canonical" />
        <meta name="twitter:card" content="summary" />
        <meta name="twitter:title" :content="`${pageTitle} - ${seo.siteName || 'ArenaKita'}`" />
        <meta name="twitter:description" :content="metaDescription" />
        <component :is="'script'" type="application/ld+json">{{ jsonLdPayload }}</component>
    </Head>

    <PublicLayout>
        <section class="border-b border-border bg-surface">
            <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16 lg:py-20">
                <div class="grid items-center gap-10 lg:grid-cols-12">
                    <div class="lg:col-span-7">
                        <span class="inline-flex items-center rounded-[var(--radius-control)] bg-accent px-3 py-1 text-xs font-bold uppercase tracking-wider text-ink">
                            Booking Real-Time · Anti Double Booking
                        </span>

                        <h1 class="mt-4 font-display text-4xl leading-[1.05] font-bold tracking-tight text-ink sm:text-5xl lg:text-6xl">
                            Booking Lapangan Olahraga Tanpa Bentrok Jadwal
                        </h1>

                        <p class="hero-stagger-1 mt-4 max-w-xl text-base leading-relaxed text-ink-muted sm:text-lg">
                            Cek jadwal kosong futsal, mini soccer, dan badminton di berbagai kota secara langsung. Tahan slot, bayar ke rekening resmi venue, dan masuk lapangan dengan tiket QR.
                        </p>

                        <form
                            action="/venues"
                            method="get"
                            class="hero-stagger-2 mt-7 rounded-[var(--radius-card)] border border-border bg-canvas p-4 sm:p-5"
                            @submit.prevent="submitSearch"
                        >
                            <div class="grid gap-3 sm:grid-cols-12">
                                <div class="sm:col-span-4">
                                    <label for="hero-sport" class="block text-xs font-bold uppercase tracking-wide text-ink-muted">
                                        Olahraga
                                    </label>
                                    <select
                                        id="hero-sport"
                                        v-model="selectedSport"
                                        name="sport"
                                        class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm font-medium text-ink"
                                    >
                                        <option value="">Semua olahraga</option>
                                        <option v-for="sport in sports" :key="sport.slug" :value="sport.slug">
                                            {{ sport.name }}
                                        </option>
                                    </select>
                                </div>

                                <div class="sm:col-span-4">
                                    <label for="hero-city" class="block text-xs font-bold uppercase tracking-wide text-ink-muted">
                                        Kota
                                    </label>
                                    <select
                                        id="hero-city"
                                        v-model="selectedCity"
                                        name="city"
                                        class="mt-1.5 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm font-medium text-ink"
                                    >
                                        <option value="">Semua kota</option>
                                        <option v-for="cityItem in cities" :key="cityItem.city_slug" :value="cityItem.city_slug">
                                            {{ cityItem.city }}
                                        </option>
                                    </select>
                                </div>

                                <div class="flex items-end sm:col-span-4">
                                    <button
                                        type="submit"
                                        :disabled="isSearching"
                                        class="inline-flex min-h-[44px] w-full items-center justify-center rounded-[var(--radius-control)] bg-primary px-5 py-2.5 text-sm font-bold text-white transition-colors hover:bg-primary-hover disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        {{ isSearching ? 'Mencari...' : 'Cari Lapangan' }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div ref="statsRef" class="hero-stagger-3 lg:col-span-5">
                        <div class="rounded-[var(--radius-card)] border border-border bg-primary p-6 text-white sm:p-7">
                            <p class="text-xs font-bold uppercase tracking-wider text-accent">
                                Ekosistem ArenaKita
                            </p>
                            <h2 class="mt-2 font-display text-3xl font-bold text-white">
                                Jadwal Terkunci Akurat di Database
                            </h2>
                            <p class="mt-2 text-sm leading-relaxed text-canvas/90">
                                Setiap pemesanan menahan slot secara atomik sehingga tidak ada dua tim bermain di jam yang sama.
                            </p>

                            <dl class="mt-6 grid grid-cols-3 gap-3 border-t border-white/15 pt-5">
                                <div>
                                    <dt class="text-xs font-medium text-canvas/80">Venue Aktif</dt>
                                    <dd class="mt-1 font-display text-3xl font-bold tabular-nums text-accent">
                                        {{ formatNumber(venuesCountDisplay) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-canvas/80">Lapangan</dt>
                                    <dd class="mt-1 font-display text-3xl font-bold tabular-nums text-accent">
                                        {{ formatNumber(courtsCountDisplay) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-medium text-canvas/80">Olahraga</dt>
                                    <dd class="mt-1 font-display text-3xl font-bold tabular-nums text-accent">
                                        {{ formatNumber(sportsCountDisplay) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <SportCategoriesSection :sports="sports" />

        <HowItWorksSection />

        <FeaturedVenuesSection :venues="featuredVenues" />

        <OwnerPlansSection :plans="plans" />

        <FaqSection />
    </PublicLayout>
</template>
