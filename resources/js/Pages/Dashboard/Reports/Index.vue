<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import { formatNumber, formatRupiah } from '@/Utils/format';

const props = defineProps({
    report: { type: Object, required: true },
});

const period = ref(props.report.period);
const date = ref(props.report.date);
const month = ref(props.report.month);

function applyFilter() {
    router.get(
        '/dashboard/reports',
        {
            period: period.value,
            date: period.value === 'daily' ? date.value : undefined,
            month: period.value === 'monthly' ? month.value : undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
}

function exportCsvUrl() {
    const params = new URLSearchParams({ period: period.value });
    if (period.value === 'daily') {
        params.set('date', date.value);
    } else {
        params.set('month', month.value);
    }
    return `/dashboard/reports/export?${params.toString()}`;
}
</script>

<template>
    <Head title="Laporan Keuangan Venue" />

    <DashboardLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="font-display text-2xl font-bold text-ink">Laporan Keuangan Venue</h1>
                    <p class="mt-1 text-sm text-ink-muted">
                        Dihitung berdasarkan tanggal main (play_date) pada venue aktif saat ini.
                    </p>
                </div>

                <a
                    v-if="report.can_export_reports"
                    :href="exportCsvUrl()"
                    class="inline-flex min-h-[44px] items-center rounded-[var(--radius-control)] bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover"
                >
                    Ekspor CSV
                </a>
                <span
                    v-else
                    class="rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-xs font-medium text-ink-muted"
                >
                    Ekspor CSV tersedia di paket Pro & Enterprise
                </span>
            </div>

            <Card>
                <form class="flex flex-wrap items-end gap-4" @submit.prevent="applyFilter">
                    <div>
                        <label for="rep-period" class="mb-1 block text-sm font-semibold text-ink">Periode</label>
                        <select id="rep-period" v-model="period" class="rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink">
                            <option value="daily">Harian</option>
                            <option value="monthly">Bulanan</option>
                        </select>
                    </div>
                    <div v-if="period === 'daily'">
                        <label for="rep-date" class="mb-1 block text-sm font-semibold text-ink">Tanggal Main</label>
                        <input id="rep-date" v-model="date" type="date" class="rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div v-else>
                        <label for="rep-month" class="mb-1 block text-sm font-semibold text-ink">Bulan Main</label>
                        <input id="rep-month" v-model="month" type="month" class="rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <Button type="submit">Tampilkan</Button>
                </form>
            </Card>

            <div class="grid gap-4 sm:grid-cols-4">
                <Card>
                    <p class="text-xs font-medium text-ink-muted">Pendapatan Disetujui</p>
                    <p class="mt-1 text-xl font-bold text-primary">{{ formatRupiah(report.summary.total_revenue) }}</p>
                </Card>
                <Card>
                    <p class="text-xs font-medium text-ink-muted">Total Booking</p>
                    <p class="mt-1 text-xl font-bold text-ink">{{ formatNumber(report.summary.total_bookings) }}</p>
                </Card>
                <Card>
                    <p class="text-xs font-medium text-ink-muted">DP + Pelunasan</p>
                    <p class="mt-1 text-sm font-bold text-ink">{{ formatRupiah(report.summary.dp_revenue + report.summary.settlement_revenue) }}</p>
                </Card>
                <Card>
                    <p class="text-xs font-medium text-ink-muted">Selisih (Threat T11)</p>
                    <p class="mt-1 text-xl font-bold" :class="report.summary.discrepancy_count > 0 ? 'text-red-600' : 'text-emerald-700'">
                        {{ formatNumber(report.summary.discrepancy_count) }}
                    </p>
                </Card>
            </div>

            <Card>
                <h2 class="mb-3 text-lg font-bold text-ink">Jumlah Booking per Status</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div v-for="(count, status) in report.status_counts" :key="status" class="rounded-[var(--radius-control)] border border-border bg-canvas p-3">
                        <p class="text-xs font-medium text-ink-muted uppercase">{{ status }}</p>
                        <p class="mt-1 text-lg font-bold text-ink">{{ formatNumber(count) }}</p>
                    </div>
                </div>
            </Card>

            <Card>
                <h2 class="mb-3 text-lg font-bold text-ink">Laporan Selisih (Confirmed Tanpa Pembayaran Disetujui)</h2>
                <div v-if="report.discrepancies.length === 0" class="text-sm text-emerald-700">
                    Tidak ada selisih pembayaran pada periode ini.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-border text-xs text-ink-muted uppercase">
                                <th class="py-2 pr-3">Kode</th>
                                <th class="py-2 pr-3">Tanggal Main</th>
                                <th class="py-2 pr-3">Lapangan</th>
                                <th class="py-2 pr-3">Pembooking</th>
                                <th class="py-2 pr-3">Status</th>
                                <th class="py-2 text-right">Tagihan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in report.discrepancies" :key="item.ulid" class="border-b border-border">
                                <td class="py-2 pr-3 font-mono font-semibold">{{ item.code }}</td>
                                <td class="py-2 pr-3">{{ item.play_date }}</td>
                                <td class="py-2 pr-3">{{ item.court_name }}</td>
                                <td class="py-2 pr-3">{{ item.customer_name }}</td>
                                <td class="py-2 pr-3">{{ item.status }}</td>
                                <td class="py-2 text-right font-semibold text-red-600">{{ formatRupiah(item.total_amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>
    </DashboardLayout>
</template>
