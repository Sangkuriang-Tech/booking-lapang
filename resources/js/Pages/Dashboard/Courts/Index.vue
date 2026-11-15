<script setup>
import { reactive } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import Button from '@/Components/UI/Button.vue';
import Card from '@/Components/UI/Card.vue';
import { formatRupiah } from '@/Utils/format';

const props = defineProps({
    categories: { type: Array, required: true },
    courts: { type: Array, required: true },
    sports: { type: Array, required: true },
    quota: { type: Object, required: true },
});

const categoryForm = useForm({ name: '' });

const courtForm = useForm({
    sport_id: props.sports[0]?.id || '',
    category_id: '',
    name: '',
    description: '',
    is_active: true,
});

const ruleForms = reactive({});
function getRuleForm(courtUlid) {
    if (!ruleForms[courtUlid]) {
        ruleForms[courtUlid] = useForm({
            day_type: 'weekday',
            start_min: 480,
            end_min: 1020,
            price_per_hour: 100000,
        });
    }
    return ruleForms[courtUlid];
}

function minToLabel(min) {
    const h = Math.floor(min / 60);
    const m = min % 60;
    return `${String(h).padStart(2, '0')}.${String(m).padStart(2, '0')}`;
}

function submitCategory() {
    categoryForm.post('/dashboard/court-categories', {
        preserveScroll: true,
        onSuccess: () => categoryForm.reset(),
    });
}

function deleteCategory(id) {
    router.delete(`/dashboard/court-categories/${id}`, { preserveScroll: true });
}

function submitCourt() {
    courtForm
        .transform((d) => ({ ...d, category_id: d.category_id || null }))
        .post('/dashboard/courts', {
            preserveScroll: true,
            onSuccess: () => courtForm.reset('name', 'description'),
        });
}

function deleteCourt(ulid) {
    router.delete(`/dashboard/courts/${ulid}`, { preserveScroll: true });
}

function submitPriceRule(courtUlid) {
    getRuleForm(courtUlid).post(`/dashboard/courts/${courtUlid}/price-rules`, {
        preserveScroll: true,
    });
}

function deletePriceRule(ruleId) {
    router.delete(`/dashboard/price-rules/${ruleId}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Lapangan & Aturan Harga" />

    <DashboardLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="font-display text-2xl font-bold text-ink">Lapangan & Aturan Harga</h1>
                    <p class="mt-1 text-sm text-ink-muted">
                        Kelola kategori, daftar lapangan, dan tarif weekday/weekend.
                    </p>
                </div>
                <div class="rounded-[var(--radius-control)] border border-border bg-surface px-4 py-2 text-sm font-semibold text-ink">
                    Kuota Lapangan: {{ quota.used }} / {{ quota.max === null ? 'Tidak Terbatas' : quota.max }}
                </div>
            </div>

            <Card>
                <h2 class="mb-3 text-lg font-bold text-ink">Kategori Lapangan</h2>
                <form class="mb-4 flex flex-wrap gap-2" @submit.prevent="submitCategory">
                    <input v-model="categoryForm.name" type="text" required placeholder="Nama kategori (misal: Indoor Vinyl)" class="flex-1 rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                    <Button type="submit" :disabled="categoryForm.processing">Tambah Kategori</Button>
                </form>
                <p v-if="categoryForm.errors.name" class="mb-2 text-xs text-red-600">{{ categoryForm.errors.name }}</p>
                <div class="flex flex-wrap gap-2">
                    <span v-for="cat in categories" :key="cat.id" class="inline-flex items-center gap-2 rounded-full border border-border bg-canvas px-3 py-1 text-xs font-semibold text-ink">
                        {{ cat.name }}
                        <button type="button" class="text-red-600 hover:underline" @click="deleteCategory(cat.id)">×</button>
                    </span>
                </div>
            </Card>

            <Card>
                <h2 class="mb-3 text-lg font-bold text-ink">Tambah Lapangan Baru</h2>
                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submitCourt">
                    <div>
                        <label for="court-name" class="mb-1 block text-sm font-semibold text-ink">Nama Lapangan</label>
                        <input id="court-name" v-model="courtForm.name" type="text" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink" />
                        <p v-if="courtForm.errors.name" class="mt-1 text-xs text-red-600">{{ courtForm.errors.name }}</p>
                    </div>
                    <div>
                        <label for="court-sport" class="mb-1 block text-sm font-semibold text-ink">Cabang Olahraga</label>
                        <select id="court-sport" v-model="courtForm.sport_id" required class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink">
                            <option v-for="sport in sports" :key="sport.id" :value="sport.id">{{ sport.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="court-cat" class="mb-1 block text-sm font-semibold text-ink">Kategori (Opsional)</label>
                        <select id="court-cat" v-model="courtForm.category_id" class="w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink">
                            <option value="">Tanpa Kategori</option>
                            <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                        </select>
                    </div>
                    <div class="flex items-end justify-end">
                        <Button type="submit" :disabled="courtForm.processing">Tambah Lapangan</Button>
                    </div>
                </form>
            </Card>

            <div class="space-y-4">
                <Card v-for="court in courts" :key="court.ulid">
                    <div class="mb-4 flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-base font-bold text-ink">{{ court.name }}</h3>
                            <p class="text-xs text-ink-muted">{{ court.sport_name }} · {{ court.category_name || 'Umum' }} · {{ court.is_active ? 'Aktif' : 'Nonaktif' }}</p>
                        </div>
                        <button type="button" class="text-xs font-semibold text-red-600 hover:underline" @click="deleteCourt(court.ulid)">
                            Hapus Lapangan
                        </button>
                    </div>

                    <div class="mb-4 space-y-2">
                        <p class="text-xs font-semibold text-ink-muted uppercase">Aturan Harga</p>
                        <div v-if="court.price_rules.length === 0" class="text-xs text-ink-muted">Belum ada aturan harga.</div>
                        <div v-for="rule in court.price_rules" :key="rule.id" class="flex items-center justify-between rounded-[var(--radius-control)] border border-border bg-canvas px-3 py-2 text-sm">
                            <span>
                                <strong class="uppercase">{{ rule.day_type }}</strong> · {{ minToLabel(rule.start_min) }}–{{ minToLabel(rule.end_min) }}
                            </span>
                            <div class="flex items-center gap-3">
                                <span class="font-bold text-primary">{{ formatRupiah(rule.price_per_hour) }}/jam</span>
                                <button type="button" class="text-xs text-red-600 hover:underline" @click="deletePriceRule(rule.id)">Hapus</button>
                            </div>
                        </div>
                    </div>

                    <form class="grid gap-2 border-t border-border pt-3 sm:grid-cols-5" @submit.prevent="submitPriceRule(court.ulid)">
                        <select v-model="getRuleForm(court.ulid).day_type" class="rounded-[var(--radius-control)] border border-border bg-surface px-2.5 py-1.5 text-sm text-ink">
                            <option value="weekday">Weekday</option>
                            <option value="weekend">Weekend</option>
                        </select>
                        <input v-model.number="getRuleForm(court.ulid).start_min" type="number" min="0" max="1439" step="30" placeholder="Mulai (menit)" class="rounded-[var(--radius-control)] border border-border bg-surface px-2.5 py-1.5 text-sm text-ink" />
                        <input v-model.number="getRuleForm(court.ulid).end_min" type="number" min="1" max="2880" step="30" placeholder="Selesai (menit)" class="rounded-[var(--radius-control)] border border-border bg-surface px-2.5 py-1.5 text-sm text-ink" />
                        <input v-model.number="getRuleForm(court.ulid).price_per_hour" type="number" min="1000" step="5000" placeholder="Harga/jam" class="rounded-[var(--radius-control)] border border-border bg-surface px-2.5 py-1.5 text-sm text-ink" />
                        <Button type="submit" :disabled="getRuleForm(court.ulid).processing">Tambah Tarif</Button>
                    </form>
                    <p v-if="getRuleForm(court.ulid).errors.start_min" class="mt-1 text-xs text-red-600">
                        {{ getRuleForm(court.ulid).errors.start_min }}
                    </p>
                </Card>
            </div>
        </div>
    </DashboardLayout>
</template>
