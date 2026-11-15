<script setup>
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    venues: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    venue_ulid: '',
});

function selectVenue(ulid) {
    form.venue_ulid = ulid;
    form.post('/dashboard/venues/select');
}
</script>

<template>
    <Head title="Pilih Venue">
        <meta name="robots" content="noindex" />
    </Head>

    <main class="min-h-screen bg-[#F5F4EF] text-[#0E1B16] flex items-center justify-center px-4 py-12">
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="select-venue-title"
            class="w-full max-w-lg rounded-xl border border-[#DAD8CE] bg-white p-6"
        >
            <h1 id="select-venue-title" class="text-2xl font-bold tracking-tight">Pilih Venue</h1>
            <p class="mt-1 text-sm text-[#53625B]">
                Pilih venue yang ingin Anda kelola pada sesi ini.
            </p>

            <div v-if="venues.length === 0" class="mt-6 rounded-[10px] border border-[#DAD8CE] bg-[#F5F4EF] p-4 text-sm text-[#53625B]">
                Akun Anda belum terdaftar pada venue mana pun. Silakan hubungi Superadmin untuk menautkan akun ke venue.
            </div>

            <ul v-else class="mt-6 space-y-3">
                <li v-for="venue in venues" :key="venue.ulid">
                    <button
                        type="button"
                        :disabled="form.processing"
                        class="w-full min-h-[44px] rounded-[10px] border border-[#DAD8CE] bg-white px-4 py-3 text-left hover:border-[#0F3B2E] focus:outline-none focus:ring-2 focus:ring-[#0F3B2E]"
                        @click="selectVenue(venue.ulid)"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="font-semibold text-[#0E1B16]">{{ venue.name }}</div>
                                <div class="text-xs text-[#53625B]">{{ venue.city }} · {{ venue.timezone }}</div>
                            </div>
                            <span class="rounded-md border border-[#DAD8CE] px-2 py-0.5 text-xs font-medium uppercase text-[#53625B]">
                                {{ venue.role }}
                            </span>
                        </div>
                    </button>
                </li>
            </ul>
        </div>
    </main>
</template>
