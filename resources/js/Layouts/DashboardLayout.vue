<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import Toast from '@/Components/UI/Toast.vue';

const props = defineProps({
    activeVenueName: {
        type: String,
        default: '',
    },
    toast: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['closeToast', 'switchVenue']);
const sidebarOpen = ref(false);
const switchModalOpen = ref(false);
const switchingVenue = ref(false);
const page = usePage();

const user = computed(() => page.props.auth?.user || null);
const isSuperadmin = computed(() => user.value?.role === 'superadmin');
const isOwnerOrSuperadmin = computed(
    () => isSuperadmin.value || page.props.activeVenue?.role === 'owner',
);
const availableVenues = computed(() => page.props.availableVenues || []);
const activeVenueStatus = computed(() => page.props.activeVenue?.subscription_status || 'active');

const resolvedVenueName = computed(() => {
    if (props.activeVenueName) {
        return props.activeVenueName;
    }

    return page.props.activeVenue?.name || 'Mode Superadmin';
});

const canSwitchVenue = computed(() => availableVenues.value.length > 1);

const resolvedToast = computed(() => {
    if (props.toast) {
        return props.toast;
    }

    if (page.props.flash?.error) {
        return { show: true, type: 'error', message: page.props.flash.error };
    }

    if (page.props.flash?.success) {
        return { show: true, type: 'success', message: page.props.flash.success };
    }

    return { show: false, type: 'info', message: '' };
});

const currentUrl = computed(() => page.url || '');

const operationalNavItems = [
    { href: '/dashboard', label: 'Ringkasan', exact: true },
    { href: '/dashboard/validations', label: 'Validasi Pembayaran', exact: false },
    { href: '/dashboard/check-in', label: 'Scan QR & Check-in', exact: false },
    { href: '/dashboard/ots', label: 'Booking OTS', exact: false },
    { href: '/dashboard/schedule', label: 'Jadwal & Blokir Slot', exact: false },
];

const ownerNavItems = [
    { href: '/dashboard/settings/venue', label: 'Pengaturan Venue', exact: false },
    { href: '/dashboard/courts', label: 'Lapangan & Harga', exact: false },
    { href: '/dashboard/payment-accounts', label: 'Rekening / QRIS', exact: false },
    { href: '/dashboard/staff', label: 'Karyawan', exact: false },
    { href: '/dashboard/reports', label: 'Laporan Keuangan', exact: false },
];

const adminNavItems = [
    { href: '/admin', label: 'Ringkasan Superadmin', exact: true },
    { href: '/admin/plans', label: 'Master Paket', exact: false },
    { href: '/admin/subscriptions', label: 'Langganan Owner', exact: false },
    { href: '/admin/users', label: 'Akun Pemilik & Staf', exact: false },
    { href: '/admin/venues', label: 'Kelola Venue', exact: false },
    { href: '/admin/transactions', label: 'Transaksi Lintas Venue', exact: false },
];

function isActive(item) {
    if (item.exact) {
        return currentUrl.value === item.href;
    }

    return currentUrl.value.startsWith(item.href);
}

function subscriptionBadge(status) {
    if (status === 'grace_period') {
        return { label: 'Masa Tenggang', classes: 'bg-amber-100 text-amber-800 border-amber-300' };
    }
    if (status === 'inactive') {
        return { label: 'Nonaktif', classes: 'bg-red-100 text-red-800 border-red-300' };
    }

    return { label: 'Aktif', classes: 'bg-emerald-100 text-emerald-800 border-emerald-300' };
}

function selectVenue(venueUlid) {
    switchingVenue.value = true;
    router.post(
        '/dashboard/venues/select',
        { venue_ulid: venueUlid },
        {
            preserveScroll: true,
            onFinish: () => {
                switchingVenue.value = false;
                switchModalOpen.value = false;
            },
        },
    );
}

const logoutForm = useForm({});
function logout() {
    logoutForm.post('/logout');
}
</script>

<template>
    <div class="flex min-h-screen bg-canvas text-ink">
        <a
            href="#dashboard-content"
            class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-[var(--radius-control)] focus:bg-primary focus:px-4 focus:py-2.5 focus:text-sm focus:font-semibold focus:text-white"
        >
            Lewati ke konten dashboard
        </a>

        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
            class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r border-border bg-surface transition-transform duration-150 md:static"
            aria-label="Sidebar dashboard"
        >
            <div class="flex items-center justify-between border-b border-border px-4 py-3.5">
                <Link
                    :href="isSuperadmin && !page.props.activeVenue ? '/admin' : '/dashboard'"
                    class="inline-flex min-h-[44px] items-center gap-2 font-display text-xl font-bold text-primary"
                >
                    <span
                        class="inline-flex h-7 w-7 items-center justify-center rounded-[var(--radius-control)] bg-primary text-xs font-bold text-accent"
                        aria-hidden="true"
                    >
                        AK
                    </span>
                    <span>ArenaKita</span>
                </Link>

                <button
                    type="button"
                    aria-label="Tutup sidebar"
                    class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-[var(--radius-control)] text-ink hover:bg-canvas md:hidden"
                    @click="sidebarOpen = false"
                >
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                    </svg>
                </button>
            </div>

            <nav aria-label="Navigasi dashboard" class="flex-1 space-y-5 overflow-y-auto p-3">
                <slot name="sidebar">
                    <div v-if="page.props.activeVenue" class="space-y-1">
                        <p class="px-3 text-xs font-semibold tracking-wide text-ink-muted uppercase">
                            Operasional Venue
                        </p>
                        <Link
                            v-for="item in operationalNavItems"
                            :key="item.href"
                            :href="item.href"
                            :class="isActive(item) ? 'bg-primary text-white' : 'text-ink hover:bg-canvas'"
                            class="flex min-h-[44px] items-center rounded-[var(--radius-control)] px-3 py-2 text-sm font-semibold transition-colors"
                        >
                            {{ item.label }}
                        </Link>
                    </div>

                    <div v-if="page.props.activeVenue && isOwnerOrSuperadmin" class="space-y-1">
                        <p class="px-3 text-xs font-semibold tracking-wide text-ink-muted uppercase">
                            Pemilik Venue
                        </p>
                        <Link
                            v-for="item in ownerNavItems"
                            :key="item.href"
                            :href="item.href"
                            :class="isActive(item) ? 'bg-primary text-white' : 'text-ink hover:bg-canvas'"
                            class="flex min-h-[44px] items-center rounded-[var(--radius-control)] px-3 py-2 text-sm font-semibold transition-colors"
                        >
                            {{ item.label }}
                        </Link>
                    </div>

                    <div v-if="isSuperadmin" class="space-y-1">
                        <p class="px-3 text-xs font-semibold tracking-wide text-ink-muted uppercase">
                            Panel Superadmin
                        </p>
                        <Link
                            v-for="item in adminNavItems"
                            :key="item.href"
                            :href="item.href"
                            :class="isActive(item) ? 'bg-primary text-white' : 'text-ink hover:bg-canvas'"
                            class="flex min-h-[44px] items-center rounded-[var(--radius-control)] px-3 py-2 text-sm font-semibold transition-colors"
                        >
                            {{ item.label }}
                        </Link>
                    </div>
                </slot>
            </nav>
        </aside>

        <div class="flex flex-1 flex-col">
            <header class="flex items-center justify-between gap-4 border-b border-border bg-surface px-4 py-3 sm:px-6">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        aria-label="Buka sidebar"
                        class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-[var(--radius-control)] border border-border bg-surface text-ink hover:bg-canvas md:hidden"
                        @click="sidebarOpen = true"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <div>
                        <p class="text-xs font-medium text-ink-muted">Venue Aktif</p>
                        <p class="text-sm font-bold text-ink">{{ resolvedVenueName }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        v-if="canSwitchVenue"
                        type="button"
                        class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] border border-border bg-surface px-3.5 py-2 text-sm font-semibold text-primary hover:bg-canvas"
                        @click="switchModalOpen = true"
                    >
                        Ganti Venue
                    </button>

                    <button
                        type="button"
                        class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover"
                        @click="logout"
                    >
                        Keluar
                    </button>
                </div>
            </header>

            <main id="dashboard-content" class="flex-1 p-4 sm:p-6">
                <div
                    v-if="page.props.activeVenue && activeVenueStatus === 'grace_period'"
                    role="alert"
                    class="mb-4 rounded-[var(--radius-control)] border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900"
                >
                    <strong>Masa Tenggang Langganan (H+1 s/d H+7):</strong> Venue disembunyikan dari pencarian publik dan booking online baru ditutup. Silakan hubungi Superadmin untuk perpanjangan paket.
                </div>

                <div
                    v-if="page.props.activeVenue && activeVenueStatus === 'inactive'"
                    role="alert"
                    class="mb-4 rounded-[var(--radius-control)] border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900"
                >
                    <strong>Langganan Nonaktif:</strong> Pembuatan booking baru (online maupun OTS) dikunci sampai langganan diperpanjang.
                </div>

                <slot />
            </main>
        </div>

        <div
            v-if="switchModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="switch-venue-title"
        >
            <div class="w-full max-w-md rounded-[var(--radius-card)] border border-border bg-surface p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 id="switch-venue-title" class="text-lg font-bold text-ink">
                        Pilih Venue Aktif
                    </h2>
                    <button
                        type="button"
                        aria-label="Tutup modal"
                        class="inline-flex min-h-[40px] min-w-[40px] items-center justify-center rounded-[var(--radius-control)] text-ink-muted hover:bg-canvas"
                        @click="switchModalOpen = false"
                    >
                        ✕
                    </button>
                </div>

                <div class="space-y-2">
                    <button
                        v-for="venue in availableVenues"
                        :key="venue.ulid"
                        type="button"
                        :disabled="switchingVenue"
                        class="flex w-full items-center justify-between rounded-[var(--radius-control)] border border-border p-3 text-left transition-colors hover:bg-canvas disabled:opacity-50"
                        :class="page.props.activeVenue?.ulid === venue.ulid ? 'border-primary bg-canvas' : ''"
                        @click="selectVenue(venue.ulid)"
                    >
                        <div>
                            <p class="text-sm font-bold text-ink">{{ venue.name }}</p>
                            <p class="text-xs text-ink-muted">{{ venue.city }} · Peran: {{ venue.role === 'owner' ? 'Pemilik' : 'Karyawan' }}</p>
                        </div>
                        <span
                            class="rounded-full border px-2.5 py-0.5 text-xs font-semibold"
                            :class="subscriptionBadge(venue.subscription_status).classes"
                        >
                            {{ subscriptionBadge(venue.subscription_status).label }}
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <Toast
            :show="resolvedToast.show"
            :type="resolvedToast.type"
            :message="resolvedToast.message"
            @close="emit('closeToast')"
        />
    </div>
</template>
