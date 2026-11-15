<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import { formatRupiah } from '@/Utils/format';

const props = defineProps({
    transactions: { type: Object, required: true },
    filters: { type: Object, required: true },
    venues: { type: Array, required: true },
});

const venueUlid = ref(props.filters.venue_ulid || '');
const status = ref(props.filters.status || '');
const playDate = ref(props.filters.play_date || '');

function applyFilter() {
    router.get(
        '/admin/transactions',
        {
            venue_ulid: venueUlid.value || undefined,
            status: status.value || undefined,
            play_date: playDate.value || undefined,
        },
        { preserveState: true },
    );
}
</script>

<template>
    <Head title="Transaksi Lintas Venue" />

    <DashboardLayout>
        <div class="mx-auto max-w-6xl space-y-6">
            <div>
                <h1 class="font-display text-2xl font-bold text-ink">Transaksi Lintas Venue</h1>
                <p class="mt-1 text-sm text-ink-muted">Akses khusus Superadmin (setiap kunjungan dicatat ke audit log).</p>
            </div>

            <Card>
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="applyFilter">
                    <div>
                        <label for="tx-venue" class="mb-1 block text-xs font-semibold text-ink">Venue</label>
                        <select id="tx-venue" v-model="venueUlid" class="rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink">
                            <option value="">Semua Venue</option>
                            <option v-for="v in venues" :key="v.ulid" :value="v.ulid">{{ v.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="tx-date" class="mb-1 block text-xs font-semibold text-ink">Tanggal Main</label>
                        <input id="tx-date" v-model="playDate" type="date" class="rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <Button type="submit">Filter</Button>
                </form>
            </Card>

            <Card>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-border text-xs text-ink-muted uppercase">
                                <th class="py-2 pr-3">Kode</th>
                                <th class="py-2 pr-3">Venue</th>
                                <th class="py-2 pr-3">Lapangan</th>
                                <th class="py-2 pr-3">Pembooking</th>
                                <th class="py-2 pr-3">Tanggal Main</th>
                                <th class="py-2 pr-3">Status</th>
                                <th class="py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="tx in transactions.data" :key="tx.ulid" class="border-b border-border">
                                <td class="py-2 pr-3 font-mono font-semibold">{{ tx.code }}</td>
                                <td class="py-2 pr-3">{{ tx.venue_name }}</td>
                                <td class="py-2 pr-3">{{ tx.court_name }}</td>
                                <td class="py-2 pr-3">{{ tx.customer_name }}</td>
                                <td class="py-2 pr-3">{{ tx.play_date }}</td>
                                <td class="py-2 pr-3">{{ tx.status }}</td>
                                <td class="py-2 text-right font-semibold">{{ formatRupiah(tx.total_amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>
    </DashboardLayout>
</template>
