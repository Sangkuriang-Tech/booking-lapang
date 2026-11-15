<script setup>
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Card from '@/Components/UI/Card.vue';
import { formatNumber } from '@/Utils/format';

defineProps({
    counts: { type: Object, required: true },
    subscriptionsByStatus: { type: Object, required: true },
});
</script>

<template>
    <Head title="Ringkasan Superadmin" />

    <DashboardLayout>
        <div class="mx-auto max-w-6xl space-y-6">
            <div>
                <h1 class="font-display text-2xl font-bold text-ink">Ringkasan Superadmin</h1>
                <p class="mt-1 text-sm text-ink-muted">Pantau status langganan aktif, masa tenggang (H+1..H+7), dan nonaktif.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-5">
                <Card>
                    <p class="text-xs text-ink-muted">Langganan Aktif</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700">{{ formatNumber(counts.active) }}</p>
                </Card>
                <Card>
                    <p class="text-xs text-ink-muted">Masa Tenggang</p>
                    <p class="mt-1 text-2xl font-bold text-amber-700">{{ formatNumber(counts.grace_period) }}</p>
                </Card>
                <Card>
                    <p class="text-xs text-ink-muted">Nonaktif (&gt;H+7)</p>
                    <p class="mt-1 text-2xl font-bold text-red-600">{{ formatNumber(counts.inactive) }}</p>
                </Card>
                <Card>
                    <p class="text-xs text-ink-muted">Total Venue</p>
                    <p class="mt-1 text-2xl font-bold text-ink">{{ formatNumber(counts.venues) }}</p>
                </Card>
                <Card>
                    <p class="text-xs text-ink-muted">Akun Pemilik/Staf</p>
                    <p class="mt-1 text-2xl font-bold text-ink">{{ formatNumber(counts.staff_accounts) }}</p>
                </Card>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                <Card>
                    <h2 class="mb-3 text-base font-bold text-emerald-800">Aktif</h2>
                    <div v-if="subscriptionsByStatus.active.length === 0" class="text-xs text-ink-muted">Tidak ada data.</div>
                    <ul v-else class="space-y-2 text-sm">
                        <li v-for="sub in subscriptionsByStatus.active" :key="sub.id" class="rounded border border-border p-2.5">
                            <p class="font-bold text-ink">{{ sub.owner_name }}</p>
                            <p class="text-xs text-ink-muted">{{ sub.plan_name }} · s/d {{ sub.ends_on }}</p>
                        </li>
                    </ul>
                </Card>

                <Card>
                    <h2 class="mb-3 text-base font-bold text-amber-800">Masa Tenggang (H+1..H+7)</h2>
                    <div v-if="subscriptionsByStatus.grace_period.length === 0" class="text-xs text-ink-muted">Tidak ada data.</div>
                    <ul v-else class="space-y-2 text-sm">
                        <li v-for="sub in subscriptionsByStatus.grace_period" :key="sub.id" class="rounded border border-amber-300 bg-amber-50 p-2.5">
                            <p class="font-bold text-ink">{{ sub.owner_name }}</p>
                            <p class="text-xs text-amber-900">{{ sub.plan_name }} · Berakhir {{ sub.ends_on }}</p>
                        </li>
                    </ul>
                </Card>

                <Card>
                    <h2 class="mb-3 text-base font-bold text-red-800">Nonaktif (&gt;H+7)</h2>
                    <div v-if="subscriptionsByStatus.inactive.length === 0" class="text-xs text-ink-muted">Tidak ada data.</div>
                    <ul v-else class="space-y-2 text-sm">
                        <li v-for="sub in subscriptionsByStatus.inactive" :key="sub.id" class="rounded border border-red-300 bg-red-50 p-2.5">
                            <p class="font-bold text-ink">{{ sub.owner_name }}</p>
                            <p class="text-xs text-red-900">{{ sub.plan_name }} · Berakhir {{ sub.ends_on }}</p>
                        </li>
                    </ul>
                </Card>
            </div>
        </div>
    </DashboardLayout>
</template>
