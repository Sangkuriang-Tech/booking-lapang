<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pemberitahuan Perubahan Rekening Tujuan</title>
</head>
<body style="font-family: sans-serif; color: #111827; line-height: 1.5;">
    <h2>Pemberitahuan Keamanan: {{ $venue->name }}</h2>
    <p>Telah terjadi perubahan pada rekening/QRIS tujuan pembayaran venue <strong>{{ $venue->name }}</strong>.</p>
    <ul>
        <li><strong>Aktivitas:</strong> {{ $eventLabel }}</li>
        <li><strong>Jenis:</strong> {{ strtoupper($account->kind) }}</li>
        @if($account->bank_name)
            <li><strong>Bank:</strong> {{ $account->bank_name }}</li>
        @endif
        @if($account->account_holder)
            <li><strong>Atas Nama:</strong> {{ $account->account_holder }}</li>
        @endif
        <li><strong>Status Aktif:</strong> {{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</li>
        <li><strong>Mulai Tampil ke Pembooking:</strong> {{ $account->visible_from->setTimezone($venue->timezone ?: 'Asia/Jakarta')->format('d M Y H:i') }}</li>
        <li><strong>Dilakukan oleh:</strong> {{ $actor->name }}</li>
    </ul>
    <p>Jika Anda tidak mengenali perubahan ini, segera hubungi Superadmin dan periksa log audit venue.</p>
</body>
</html>
