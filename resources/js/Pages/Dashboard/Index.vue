<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    courtsCount: {
        type: Number,
        required: true,
    },
    activeVenue: {
        type: Object,
        default: null,
    },
    availableVenues: {
        type: Array,
        default: () => [],
    },
});

const logoutForm = useForm({});

function logout() {
    logoutForm.post('/logout');
}
</script>

<template>
    <Head title="Dashboard Venue">
        <meta name="robots" content="noindex" />
    </Head>

    <div class="min-h-screen bg-[#F5F4EF] text-[#0E1B16]">
        <header class="border-b border-[#DAD8CE] bg-white px-6 py-4">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-[#53625B]">Venue Aktif</p>
                    <h1 class="text-xl font-bold">
                        {{ activeVenue ? activeVenue.name : 'Mode Superadmin' }}
                    </h1>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        v-if="availableVenues.length > 1"
                        href="/dashboard/venues/select"
                        class="min-h-[44px] inline-flex items-center rounded-[10px] border border-[#DAD8CE] px-4 py-2 text-sm font-medium hover:border-[#0F3B2E]"
                    >
                        Ganti Venue
                    </Link>

                    <button
                        type="button"
                        class="min-h-[44px] rounded-[10px] bg-[#0F3B2E] px-4 py-2 text-sm font-semibold text-white hover:bg-[#0B2D23]"
                        @click="logout"
                    >
                        Keluar
                    </button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-8">
            <div
                v-if="activeVenue && activeVenue.subscription_status === 'grace_period'"
                class="mb-6 rounded-[10px] border border-[#8A5A0B] bg-white p-4 text-sm text-[#8A5A0B]"
            >
                Langganan venue sedang dalam masa tenggang (H+7). Booking online baru dinonaktifkan; validasi pembayaran, check-in QR, dan laporan tetap dapat digunakan.
            </div>
            <div
                v-else-if="activeVenue && activeVenue.subscription_status === 'inactive'"
                class="mb-6 rounded-[10px] border border-[#B3261E] bg-white p-4 text-sm text-[#B3261E]"
            >
                Langganan venue tidak aktif. Pembuatan booking baru dikunci sampai paket diperpanjang.
            </div>

            <div class="rounded-xl border border-[#DAD8CE] bg-white p-6">
                <p class="text-sm text-[#53625B]">Jumlah Lapangan di Venue Aktif</p>
                <p class="mt-2 text-3xl font-bold">{{ courtsCount }}</p>
            </div>
        </main>
    </div>
</template>
