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
