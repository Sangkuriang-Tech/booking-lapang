<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import { formatRupiah } from '@/Utils/format';

defineProps({
    plans: { type: Array, required: true },
});

const form = useForm({
    code: '',
    name: '',
    price_monthly: 0,
    max_venues: 1,
    max_courts_per_venue: 4,
    max_staff_total: 3,
    max_payment_accounts_per_venue: 1,
    proof_retention_days: 30,
    can_export_reports: false,
    is_active: true,
});

function submit() {
    form.post('/admin/plans', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Master Paket Langganan" />

    <DashboardLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <h1 class="font-display text-2xl font-bold text-ink">Master Paket Langganan</h1>
                <p class="mt-1 text-sm text-ink-muted">Kelola paket Starter, Pro, Enterprise, dan kuota fitur.</p>
            </div>

            <Card>
                <h2 class="mb-4 text-lg font-bold text-ink">Tambah Paket Baru</h2>
                <form class="grid gap-4 sm:grid-cols-3" @submit.prevent="submit">
                    <div>
                        <label for="plan-code" class="mb-1 block text-xs font-semibold text-ink">Kode Paket</label>
                        <input id="plan-code" v-model="form.code" type="text" required placeholder="starter" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div>
                        <label for="plan-name" class="mb-1 block text-xs font-semibold text-ink">Nama Paket</label>
                        <input id="plan-name" v-model="form.name" type="text" required placeholder="Starter" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div>
                        <label for="plan-price" class="mb-1 block text-xs font-semibold text-ink">Harga Bulanan (Rp)</label>
                        <input id="plan-price" v-model.number="form.price_monthly" type="number" min="0" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div>
                        <label for="plan-venues" class="mb-1 block text-xs font-semibold text-ink">Maks Venue (kosong = bebas)</label>
                        <input id="plan-venues" v-model="form.max_venues" type="number" min="1" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div>
                        <label for="plan-courts" class="mb-1 block text-xs font-semibold text-ink">Maks Lapangan/Venue</label>
                        <input id="plan-courts" v-model="form.max_courts_per_venue" type="number" min="1" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div>
                        <label for="plan-staff" class="mb-1 block text-xs font-semibold text-ink">Maks Total Staf</label>
                        <input id="plan-staff" v-model="form.max_staff_total" type="number" min="1" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div>
                        <label for="plan-acc" class="mb-1 block text-xs font-semibold text-ink">Maks Rekening/Venue</label>
                        <input id="plan-acc" v-model="form.max_payment_accounts_per_venue" type="number" min="1" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div>
                        <label for="plan-ret" class="mb-1 block text-xs font-semibold text-ink">Retensi Bukti (hari)</label>
                        <input id="plan-ret" v-model.number="form.proof_retention_days" type="number" min="1" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div class="flex items-end pb-2">
                        <label class="inline-flex items-center gap-2 text-sm text-ink">
                            <input v-model="form.can_export_reports" type="checkbox" class="h-4 w-4 rounded border-border text-primary" />
                            Boleh Ekspor Laporan
                        </label>
                    </div>
                    <div class="sm:col-span-3 flex justify-end">
                        <Button type="submit" :disabled="form.processing">Simpan Paket</Button>
                    </div>
                </form>
            </Card>

            <div class="grid gap-4 sm:grid-cols-3">
                <Card v-for="plan in plans" :key="plan.id">
                    <h3 class="text-lg font-bold text-ink">{{ plan.name }}</h3>
                    <p class="mt-1 text-xl font-bold text-primary">{{ formatRupiah(plan.price_monthly) }}/bln</p>
                    <ul class="mt-3 space-y-1 text-xs text-ink-muted">
                        <li>Venue: {{ plan.max_venues ?? 'Tidak terbatas' }}</li>
                        <li>Lapangan/venue: {{ plan.max_courts_per_venue ?? 'Tidak terbatas' }}</li>
                        <li>Total Staf: {{ plan.max_staff_total ?? 'Tidak terbatas' }}</li>
                        <li>Rekening/venue: {{ plan.max_payment_accounts_per_venue ?? 'Tidak terbatas' }}</li>
                        <li>Retensi bukti: {{ plan.proof_retention_days }} hari</li>
                        <li>Ekspor laporan: {{ plan.can_export_reports ? 'Ya' : 'Tidak' }}</li>
                    </ul>
                </Card>
            </div>
        </div>
    </DashboardLayout>
</template>
