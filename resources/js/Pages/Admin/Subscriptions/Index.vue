<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';

const props = defineProps({
    subscriptions: { type: Array, required: true },
    owners: { type: Array, required: true },
    plans: { type: Array, required: true },
});

const form = useForm({
    owner_ulid: props.owners[0]?.ulid || '',
    plan_id: props.plans[0]?.id || '',
    starts_on: new Date().toISOString().slice(0, 10),
    ends_on: new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10),
    is_active: true,
    note: '',
});

function submit() {
    form.post('/admin/subscriptions', {
        preserveScroll: true,
        onSuccess: () => form.reset('note'),
    });
}

function toggleStatus(sub) {
    router.post(
        `/admin/subscriptions/${sub.id}/status`,
        { is_active: !sub.is_active },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Langganan Pemilik" />

    <DashboardLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div>
                <h1 class="font-display text-2xl font-bold text-ink">Langganan Pemilik</h1>
                <p class="mt-1 text-sm text-ink-muted">Tetapkan, perpanjang, atau nonaktifkan langganan per pemilik venue.</p>
            </div>

            <Card>
                <h2 class="mb-4 text-lg font-bold text-ink">Tetapkan Langganan Baru</h2>
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                    <div>
                        <label for="sub-owner" class="mb-1 block text-xs font-semibold text-ink">Pemilik Venue</label>
                        <select id="sub-owner" v-model="form.owner_ulid" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink">
                            <option v-for="owner in owners" :key="owner.ulid" :value="owner.ulid">{{ owner.name }} ({{ owner.email }})</option>
                        </select>
                    </div>
                    <div>
                        <label for="sub-plan" class="mb-1 block text-xs font-semibold text-ink">Paket</label>
                        <select id="sub-plan" v-model="form.plan_id" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink">
                            <option v-for="plan in plans" :key="plan.id" :value="plan.id">{{ plan.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="sub-start" class="mb-1 block text-xs font-semibold text-ink">Mulai Tanggal</label>
                        <input id="sub-start" v-model="form.starts_on" type="date" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div>
                        <label for="sub-end" class="mb-1 block text-xs font-semibold text-ink">Berakhir Tanggal</label>
                        <input id="sub-end" v-model="form.ends_on" type="date" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    </div>
                    <div class="sm:col-span-2 flex justify-end">
                        <Button type="submit" :disabled="form.processing">Simpan Langganan</Button>
                    </div>
                </form>
            </Card>

            <Card>
                <h2 class="mb-4 text-lg font-bold text-ink">Riwayat Langganan</h2>
                <div class="space-y-3">
                    <div v-for="sub in subscriptions" :key="sub.id" class="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-control)] border border-border p-3">
                        <div>
                            <p class="text-sm font-bold text-ink">{{ sub.owner_name }} · {{ sub.plan_name }}</p>
                            <p class="text-xs text-ink-muted">{{ sub.starts_on }} s/d {{ sub.ends_on }} · Status: {{ sub.status }}</p>
                        </div>
                        <button type="button" class="text-xs font-semibold text-primary hover:underline" @click="toggleStatus(sub)">
                            {{ sub.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    </div>
                </div>
            </Card>
        </div>
    </DashboardLayout>
</template>
