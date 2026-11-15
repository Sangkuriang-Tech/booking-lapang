const rupiahFormatter = new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
});

const numberFormatter = new Intl.NumberFormat('id-ID');

/**
 * Format integer Rupiah without decimals (e.g. Rp150.000).
 *
 * @param {number|null|undefined} amount
 * @returns {string}
 */
export function formatRupiah(amount) {
    const safeAmount = Number.isFinite(Number(amount)) ? Math.trunc(Number(amount)) : 0;
    const isNegative = safeAmount < 0;
    const formatted = rupiahFormatter.format(Math.abs(safeAmount));

    return isNegative ? `-Rp${formatted}` : `Rp${formatted}`;
}

/**
 * Format integer or decimal number with Indonesian thousand separators (e.g. 1.250).
 *
 * @param {number|null|undefined} value
 * @returns {string}
 */
export function formatNumber(value) {
    const safeValue = Number.isFinite(Number(value)) ? Number(value) : 0;

    return numberFormatter.format(safeValue);
}

/**
 * Format ISO 8601 UTC date string in explicit venue timezone.
 *
 * @param {string} isoString
 * @param {string} [timeZone='Asia/Jakarta']
 * @returns {string}
 */
export function formatDate(isoString, timeZone = 'Asia/Jakarta') {
    const date = new Date(isoString);

    return new Intl.DateTimeFormat('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone,
    }).format(date);
}

/**
 * Format ISO 8601 UTC time string in 24-hour format with explicit venue timezone.
 *
 * @param {string} isoString
 * @param {string} [timeZone='Asia/Jakarta']
 * @returns {string}
 */
export function formatTime(isoString, timeZone = 'Asia/Jakarta') {
    const date = new Date(isoString);

    return new Intl.DateTimeFormat('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
        timeZone,
    }).format(date);
}

/**
 * Resolve Indonesian timezone short label.
 *
 * @param {string} [timeZone='Asia/Jakarta']
 * @returns {string}
 */
export function timeZoneLabel(timeZone = 'Asia/Jakarta') {
    if (timeZone === 'Asia/Makassar') {
        return 'WITA';
    }
    if (timeZone === 'Asia/Jayapura') {
        return 'WIT';
    }

    return 'WIB';
}

/**
 * Format time range in 24-hour format with explicit venue timezone (e.g. 19.00–20.00 WIB).
 *
 * @param {string} startIso
 * @param {string} endIso
 * @param {string} [timeZone='Asia/Jakarta']
 * @returns {string}
 */
export function formatTimeRange(startIso, endIso, timeZone = 'Asia/Jakarta') {
    return `${formatTime(startIso, timeZone)}–${formatTime(endIso, timeZone)} ${timeZoneLabel(timeZone)}`;
}

/**
 * Format normalized Indonesian phone number (6281234567890 -> 0812-3456-7890).
 *
 * @param {string|null|undefined} phone
 * @returns {string}
 */
export function formatPhone(phone) {
    if (!phone) {
        return '-';
    }

    let digits = String(phone).replace(/\D+/g, '');
    if (digits.startsWith('62')) {
        digits = `0${digits.slice(2)}`;
    }

    if (digits.length <= 4) {
        return digits;
    }
    if (digits.length <= 8) {
        return `${digits.slice(0, 4)}-${digits.slice(4)}`;
    }

    return `${digits.slice(0, 4)}-${digits.slice(4, 8)}-${digits.slice(8)}`;
}
