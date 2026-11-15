<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import SlotPickerGrid from '@/Components/Booking/SlotPickerGrid.vue';
import Button from '@/Components/UI/Button.vue';
import { formatDate, formatRupiah } from '@/Utils/format';

const props = defineProps({
    venue: {
        type: Object,
        required: true,
    },
    selected_court_ulid: {
        type: String,
        default: null,
    },
    selected_date: {
        type: String,
        required: true,
    },
    slots: {
        type: Array,
        default: () => [],
    },
    json_ld: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user || null);
const isCustomer = computed(() => authUser.value?.role === 'customer');

const loadingSlots = ref(false);
const selectedRange = ref(null);
const phoneTouched = ref(false);
const clientPhoneError = ref('');

function generateIdempotencyKey() {
    if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    return `bk-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
}

const form = useForm({
    court_ulid: props.selected_court_ulid || '',
    starts_at: '',
    ends_at: '',
    customer_name: authUser.value?.name || '',
    customer_phone: '',
    payment_mode: 'dp',
    idempotency_key: generateIdempotencyKey(),
});

const selectedCourt = computed(
    () => props.venue.courts.find((c) => c.ulid === props.selected_court_ulid) || null,
);

const coverPhoto = computed(
    () => props.venue.photos.find((p) => p.is_cover) || props.venue.photos[0] || null,
);

const formattedSelectedDate = computed(() =>
    formatDate(`${props.selected_date}T12:00:00Z`, props.venue.timezone),
);

const estimatedDp = computed(() => {
    if (!selectedRange.value) {
        return 0;
    }

    return Math.ceil(selectedRange.value.total_price * (props.venue.dp_percent / 100));
});

const payableNow = computed(() => {
    if (!selectedRange.value) {
        return 0;
    }

    return form.payment_mode === 'full' ? selectedRange.value.total_price : estimatedDp.value;
});

const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

function formatMinute(minute) {
    const norm = minute % 1440;
    const h = String(Math.floor(norm / 60)).padStart(2, '0');
    const m = String(norm % 60).padStart(2, '0');

    return `${h}.${m}`;
}

function reloadSchedule(courtUlid, dateStr) {
    router.get(
        `/venue/${props.venue.slug}`,
        { court: courtUlid, date: dateStr },
        {
            only: ['slots', 'selected_court_ulid', 'selected_date'],
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                loadingSlots.value = true;
            },
            onFinish: () => {
                loadingSlots.value = false;
            },
        },
    );
}

function selectCourt(courtUlid) {
    if (courtUlid === props.selected_court_ulid) {
        return;
    }
    form.court_ulid = courtUlid;
    form.clearErrors('slot');
    reloadSchedule(courtUlid, props.selected_date);
}

function onDateChange(event) {
    const newDate = event.target.value;
    if (!newDate) {
        return;
    }
    form.clearErrors('slot');
    reloadSchedule(props.selected_court_ulid, newDate);
}

function onSlotSelection(selection) {
    selectedRange.value = selection;
    form.clearErrors('slot');
    if (selection) {
        form.court_ulid = props.selected_court_ulid || '';
        form.starts_at = selection.starts_at;
        form.ends_at = selection.ends_at;
        form.idempotency_key = generateIdempotencyKey();
    } else {
        form.starts_at = '';
        form.ends_at = '';
    }
}

function validatePhoneClient() {
    const digits = form.customer_phone.replace(/\D+/g, '');
    if (digits === '') {
        clientPhoneError.value = 'Nomor telepon wajib diisi.';
        return false;
    }
    if (digits.length < 10 || digits.length > 15) {
        clientPhoneError.value = 'Nomor telepon harus 10–15 digit yang valid.';
        return false;
    }
    clientPhoneError.value = '';
    return true;
}

function onPhoneBlur() {
    phoneTouched.value = true;
    validatePhoneClient();
}

function submitBooking() {
    if (!selectedRange.value || form.processing) {
        return;
    }

    phoneTouched.value = true;
    if (!validatePhoneClient()) {
        return;
    }

    form.court_ulid = props.selected_court_ulid || '';
    form.post('/bookings', {
        preserveScroll: true,
        onError: (errors) => {
            if (errors.slot) {
                form.idempotency_key = generateIdempotencyKey();
                router.reload({
                    only: ['slots'],
                    onStart: () => {
                        loadingSlots.value = true;
                    },
                    onFinish: () => {
                        loadingSlots.value = false;
                    },
                });
            }
        },
    });
}
</script>

<template>
    <Head>
        <title>{{ venue.name }} - {{ venue.city }}</title>
        <meta
            name="description"
            :content="venue.description || `Pesan lapangan olahraga di ${venue.name}, ${venue.city}. Lihat slot kosong dan tarif per jam.`"
        />
        <component
            :is="'script'"
            v-for="(schema, index) in json_ld"
            :key="index"
            type="application/ld+json"
        >
            {{ JSON.stringify(schema) }}
        </component>
    </Head>

    <PublicLayout>
        <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
            <nav aria-label="Breadcrumb" class="mb-5 text-xs font-semibold text-ink-muted">
                <ol class="flex flex-wrap items-center gap-2">
                    <li>
                        <Link href="/" class="hover:text-ink">Beranda</Link>
                    </li>
                    <li aria-hidden="true">/</li>
                    <li>
                        <Link href="/venues" class="hover:text-ink">Cari Venue</Link>
                    </li>
                    <li aria-hidden="true">/</li>
                    <li aria-current="page" class="text-ink">{{ venue.name }}</li>
                </ol>
            </nav>

            <div class="grid gap-8 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <div class="overflow-hidden rounded-[var(--radius-card)] border border-border bg-surface">
                        <div class="aspect-[16/9] w-full bg-canvas">
                            <img
                                v-if="coverPhoto"
                                :src="coverPhoto.url"
                                :alt="coverPhoto.alt_text"
                                :width="coverPhoto.width"
                                :height="coverPhoto.height"
                                fetchpriority="high"
                                class="h-full w-full object-cover"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center text-sm font-semibold text-ink-muted"
                            >
                                {{ venue.name }}
                            </div>
                        </div>

                        <div class="p-6">
                            <p class="text-xs font-bold uppercase tracking-wider text-primary">
                                {{ venue.city }}
                            </p>
                            <h1 class="mt-1 font-display text-3xl font-bold text-ink sm:text-4xl">
                                {{ venue.name }}
                            </h1>
                            <p class="mt-2 text-sm text-ink-muted">
                                {{ venue.address }}
                            </p>
                            <p v-if="venue.description" class="mt-3 text-sm text-ink">
                                {{ venue.description }}
                            </p>

                            <div class="mt-5 grid gap-3 border-t border-border pt-4 text-xs sm:grid-cols-3">
                                <div>
                                    <span class="block text-ink-muted">Kebijakan DP</span>
                                    <span class="mt-0.5 block font-bold text-ink">Minimal {{ venue.dp_percent }}%</span>
                                </div>
                                <div>
                                    <span class="block text-ink-muted">Batas Pembatalan</span>
                                    <span class="mt-0.5 block font-bold text-ink">Maks {{ venue.cancel_min_hours }} jam sebelum main</span>
                                </div>
                                <div>
                                    <span class="block text-ink-muted">Waktu Tahan Slot</span>
                                    <span class="mt-0.5 block font-bold text-ink">{{ venue.hold_minutes }} menit</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <section class="mt-8 rounded-[var(--radius-card)] border border-border bg-surface p-6">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 class="font-display text-2xl font-bold text-ink">
                                    Pilih Lapangan &amp; Jadwal
                                </h2>
                                <p class="mt-1 text-sm text-ink-muted">
                                    Klik satu atau beberapa slot jam berurutan.
                                </p>
                            </div>

                            <div>
                                <label for="booking-date" class="block text-xs font-semibold text-ink">
                                    Tanggal Main
                                </label>
                                <input
                                    id="booking-date"
                                    type="date"
                                    :value="selected_date"
                                    :min="selected_date"
                                    class="mt-1 min-h-[44px] rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                                    @change="onDateChange"
                                />
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-2" role="group" aria-label="Pilih lapangan">
                            <button
                                v-for="court in venue.courts"
                                :key="court.ulid"
                                type="button"
                                :aria-pressed="court.ulid === selected_court_ulid"
                                class="min-h-[44px] rounded-[var(--radius-control)] border px-4 py-2 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                                :class="
                                    court.ulid === selected_court_ulid
                                        ? 'border-primary bg-primary text-white'
                                        : 'border-border bg-surface text-ink hover:bg-canvas'
                                "
                                @click="selectCourt(court.ulid)"
                            >
                                {{ court.name }}
                                <span v-if="court.sport" class="ml-1 text-xs opacity-80">({{ court.sport }})</span>
                            </button>
                        </div>

                        <div class="mt-6">
                            <SlotPickerGrid
                                :slots="slots"
                                :loading="loadingSlots"
                                @update:selection="onSlotSelection"
                            />
                        </div>
                    </section>

                    <section
                        v-if="venue.operating_hours.length > 0"
                        class="mt-6 rounded-[var(--radius-card)] border border-border bg-surface p-6"
                    >
                        <h2 class="font-display text-xl font-bold text-ink">
                            Jam Operasional
                        </h2>
                        <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                            <div
                                v-for="hour in venue.operating_hours"
                                :key="hour.weekday"
                                class="flex items-center justify-between border-b border-border py-1.5"
                            >
                                <dt class="font-semibold text-ink">{{ dayNames[hour.weekday] }}</dt>
                                <dd class="text-ink-muted">
                                    {{ hour.is_closed ? 'Tutup' : `${formatMinute(hour.opens_min)}–${formatMinute(hour.closes_min)}` }}
                                </dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <aside class="lg:col-span-1">
                    <div class="sticky top-24 rounded-[var(--radius-card)] border border-border bg-surface p-6">
                        <h2 class="font-display text-2xl font-bold text-ink">
                            Ringkasan Pemesanan
                        </h2>

                        <div v-if="!selectedRange" class="mt-4 rounded-[var(--radius-control)] border border-border bg-canvas p-4 text-sm text-ink-muted">
                            Pilih satu atau beberapa slot jam berurutan pada jadwal untuk melanjutkan pemesanan.
                        </div>

                        <div v-else class="mt-4 space-y-4">
                            <dl class="space-y-2 border-b border-border pb-4 text-sm">
                                <div class="flex justify-between gap-2">
                                    <dt class="text-ink-muted">Lapangan</dt>
                                    <dd class="font-semibold text-ink">{{ selectedCourt?.name }}</dd>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <dt class="text-ink-muted">Tanggal</dt>
                                    <dd class="font-semibold text-ink">{{ formattedSelectedDate }}</dd>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <dt class="text-ink-muted">Jam</dt>
                                    <dd class="font-semibold text-ink">
                                        {{ selectedRange.label }} ({{ selectedRange.hours_count }} jam)
                                    </dd>
                                </div>
                                <div class="flex justify-between gap-2">
                                    <dt class="text-ink-muted">Total Tarif</dt>
                                    <dd class="font-bold text-ink">{{ formatRupiah(selectedRange.total_price) }}</dd>
                                </div>
                            </dl>

                            <div
                                v-if="form.errors.slot || form.errors.venue_id || form.errors.court_id || form.errors.customer"
                                role="alert"
                                class="rounded-[var(--radius-control)] border border-danger bg-canvas p-3 text-xs font-semibold text-danger"
                            >
                                {{ form.errors.slot || form.errors.venue_id || form.errors.court_id || form.errors.customer }}
                            </div>

                            <div v-if="!isCustomer" class="space-y-3">
                                <p class="text-sm text-ink-muted">
                                    Masuk dengan akun Google Anda untuk menahan slot dan mengunggah bukti pembayaran.
                                </p>
                                <a
                                    href="/auth/google/redirect"
                                    class="inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-[var(--radius-control)] bg-primary px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover"
                                >
                                    Login dengan Google
                                </a>
                            </div>

                            <form v-else class="space-y-4" @submit.prevent="submitBooking">
                                <div>
                                    <label for="customer-name" class="block text-xs font-semibold text-ink">
                                        Nama Pembooking
                                    </label>
                                    <input
                                        id="customer-name"
                                        v-model="form.customer_name"
                                        type="text"
                                        required
                                        maxlength="100"
                                        :aria-invalid="Boolean(form.errors.customer_name)"
                                        :aria-describedby="form.errors.customer_name ? 'error-customer-name' : undefined"
                                        class="mt-1 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                                    />
                                    <p
                                        v-if="form.errors.customer_name"
                                        id="error-customer-name"
                                        class="mt-1 text-xs font-semibold text-danger"
                                    >
                                        {{ form.errors.customer_name }}
                                    </p>
                                </div>

                                <div>
                                    <label for="customer-phone" class="block text-xs font-semibold text-ink">
                                        Nomor Telepon / WhatsApp
                                    </label>
                                    <input
                                        id="customer-phone"
                                        v-model="form.customer_phone"
                                        type="tel"
                                        inputmode="numeric"
                                        required
                                        placeholder="081234567890"
                                        :aria-invalid="Boolean(form.errors.customer_phone || (phoneTouched && clientPhoneError))"
                                        :aria-describedby="form.errors.customer_phone || (phoneTouched && clientPhoneError) ? 'error-customer-phone' : undefined"
                                        class="mt-1 min-h-[44px] w-full rounded-[var(--radius-control)] border border-border bg-surface px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                                        @blur="onPhoneBlur"
                                    />
                                    <p
                                        v-if="form.errors.customer_phone || (phoneTouched && clientPhoneError)"
                                        id="error-customer-phone"
                                        class="mt-1 text-xs font-semibold text-danger"
                                    >
                                        {{ form.errors.customer_phone || clientPhoneError }}
                                    </p>
                                </div>

                                <fieldset>
                                    <legend class="text-xs font-semibold text-ink">
                                        Pilihan Pembayaran
                                    </legend>
                                    <div class="mt-2 grid grid-cols-2 gap-2">
                                        <label
                                            class="flex min-h-[44px] cursor-pointer items-center gap-2 rounded-[var(--radius-control)] border px-3 py-2 text-xs font-semibold"
                                            :class="form.payment_mode === 'dp' ? 'border-primary bg-canvas text-ink' : 'border-border text-ink-muted'"
                                        >
                                            <input v-model="form.payment_mode" type="radio" value="dp" class="accent-primary" />
                                            <span>DP {{ venue.dp_percent }}%</span>
                                        </label>
                                        <label
                                            class="flex min-h-[44px] cursor-pointer items-center gap-2 rounded-[var(--radius-control)] border px-3 py-2 text-xs font-semibold"
                                            :class="form.payment_mode === 'full' ? 'border-primary bg-canvas text-ink' : 'border-border text-ink-muted'"
                                        >
                                            <input v-model="form.payment_mode" type="radio" value="full" class="accent-primary" />
                                            <span>Bayar Lunas</span>
                                        </label>
                                    </div>
                                </fieldset>

                                <div class="rounded-[var(--radius-control)] bg-canvas p-3">
                                    <div class="flex items-center justify-between text-xs text-ink-muted">
                                        <span>Nominal Transfer Sekarang</span>
                                        <span class="text-base font-bold text-primary">{{ formatRupiah(payableNow) }}</span>
                                    </div>
                                </div>

                                <Button type="submit" :loading="form.processing" class="w-full">
                                    Tahan Slot &amp; Lanjut Bayar
                                </Button>
                            </form>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </PublicLayout>
</template>
