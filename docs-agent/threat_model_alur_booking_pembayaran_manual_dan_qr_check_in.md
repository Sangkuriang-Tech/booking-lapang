# Threat Model: Alur Booking, Pembayaran Manual, dan QR Check-in

Diperbarui 15 Nov 2026 · @duta

Enam ancaman paling berbahaya untuk aplikasi ini adalah double booking (T1), bukti bayar palsu (T3), akses lintas pengguna dan lintas venue (T5, T6), akun staf yang diambil alih (T9), dan rekening tujuan yang diganti penyerang (T10). Kerjakan mitigasinya lebih dulu. Dokumen ini dipakai sebagai `docs/THREAT_MODEL.md` dan melengkapi Bagian 14 pada prompt pembuatan aplikasi.

## Aset yang dilindungi

- Data pribadi pembooking (nama, email, nomor telepon) dan riwayat bookingnya
- Bukti transfer pembayaran
- Integritas jadwal: satu slot hanya untuk satu booking
- Pendapatan dan laporan tiap venue, serta rekening atau QRIS tujuan pembayaran
- Akun staf, pemilik, dan superadmin
- Token QR check-in
- Ketersediaan layanan dan kepercayaan venue terhadap sistem

## Aktor dan batas kepercayaan

- **Aktor:** pengunjung anonim, pembooking, karyawan venue, pemilik venue, superadmin, penyerang eksternal, dan insider (karyawan atau pemilik venue lain yang berniat buruk).
- **Batas kepercayaan:** browser ke server; server ke Google Sign-In; server ke layanan email dan queue; server ke penyimpanan file; venue A ke venue B (satu venue tidak boleh dipercaya terhadap data venue lain).
- **Asumsi:** pembayaran dilakukan manual langsung ke rekening atau QRIS venue; sistem tidak memegang uang. Jika kelak memakai payment gateway, dokumen ini harus ditinjau ulang.

## Ancaman dan mitigasi

| ID | Ancaman | Risiko | Mitigasi wajib | Tes |
| --- | --- | --- | --- | --- |
| T1 | Dua pembooking mendapat slot yang sama, atau booking bertabrakan dengan blokir jadwal (race condition) | Tinggi | Exclusion constraint PostgreSQL pada `bookings`, trigger `schedule_blocks`, `lockForUpdate()` pada baris `courts` di semua Action pembuat booking dan pembuat blokir jadwal, tangkap error `23P01` menjadi pesan ramah | Tes konkurensi dengan dua request bersamaan dan booking vs blokir |
| T2 | Satu akun atau satu pelaku dengan banyak akun Google menahan banyak slot (slot hoarding) | Sedang | Login Google wajib sebelum hold, batas booking `held` aktif per pengguna, pembatasan tambahan berbasis IP/subnet untuk akun tanpa riwayat booking `completed`, hold kedaluwarsa otomatis, rate limit | Hold melebihi batas per akun atau per IP ditolak |
| T3 | Bukti bayar palsu atau dipakai ulang di beberapa booking | Tinggi | Slot belum `confirmed` sebelum karyawan memvalidasi dan mencocokkan mutasi rekening; simpan hash SHA-256 file dan beri peringatan bila bukti yang sama muncul lagi; catat siapa yang menyetujui | Bukti yang sama di dua booking memicu peringatan |
| T4 | Harga, DP, atau status diubah dari sisi client | Tinggi | Semua nilai dihitung di server (termasuk kalkulasi multi-jam lintas band harga), input client untuk harga dan status diabaikan, transisi status hanya lewat Action dan Policy | Request dengan harga palsu diabaikan |
| T5 | IDOR: membaca booking atau bukti milik orang lain | Tinggi | Policy di setiap aksi, ID acak (ULID) di URL, bukti hanya lewat controller atau signed URL berumur pendek | Pembooking A membuka booking dan bukti pembooking B: 403 atau 404 |
| T6 | Staf atau pemilik venue A membaca atau mengubah data venue B, termasuk dengan mengganti venue aktif di request | Tinggi | Global scope `venue_id`, Policy, hanya Superadmin yang lintas venue dan setiap aksinya tercatat; untuk pengguna dengan banyak venue, venue aktif divalidasi ke tabel keanggotaan pada setiap request, bukan dipercaya dari client | Tes isolasi untuk setiap resource; pemilik venue A dan B tidak bisa mengakses venue C; mengganti venue aktif ke venue yang bukan miliknya ditolak; daftar di modal pilih venue dan props halaman tidak memuat venue lain |
| T7 | QR disalin atau dibagikan, lalu dipakai orang lain | Sedang | Token acak 32 byte, sekali pakai, terikat ke booking dan venue; layar scan menampilkan nama pembooking, nomor telepon, lapangan, dan jam agar karyawan memverifikasi; QR hanya berisi token | Scan dua kali: yang kedua gagal; scan di venue lain gagal |
| T8 | QR dipakai di luar jadwal atau setelah booking selesai | Sedang | Token hanya valid pada jendela waktu (60 menit sebelum mulai sampai akhir slot) dan status `confirmed`; token dicabut saat booking dibatalkan | Scan di luar jendela ditolak |
| T9 | Akun staf diambil alih (password bocor, phishing) | Tinggi | Password minimal 12 karakter dan tidak ada di daftar bocor, throttle dan lockout login, notifikasi login dari perangkat baru, 2FA untuk pemilik dan superadmin (Fase 2) | Brute force login memicu throttle |
| T10 | Penyerang atau pihak internal mengganti rekening atau QRIS tujuan, sehingga pembayaran masuk ke rekening yang salah | Tinggi | Perubahan rekening wajib input ulang password; email pemberitahuan ke pemilik dan superadmin; riwayat perubahan di audit log; jeda 24 jam sebelum rekening baru ditampilkan ke pembooking (kecuali rekening pertama saat setup venue baru) | Ubah rekening tanpa re-auth ditolak; rekening ke-2 tertunda 24 jam; email terkirim |
| T11 | Karyawan mengonfirmasi booking tanpa bayar atau menghapus bukti | Sedang | Audit log append-only untuk validasi, penolakan, dan pelunasan; karyawan tidak bisa mengubah harga atau rekening; bukti tidak bisa dihapus; laporan selisih untuk pemilik (booking `confirmed` tanpa pembayaran tercatat) | Karyawan mencoba menghapus bukti: ditolak |
| T12 | Upload berbahaya (web shell, file yang disamarkan, gambar bomb) | Tinggi | Aturan upload di Bagian 14: allowlist JPG/PNG, validasi isi file, re-encode, penyimpanan privat, tanpa eksekusi script | Tes upload di Bagian 14 |
| T13 | Pengambilan data pengguna lewat pencarian atau enumerasi ID | Sedang | Halaman publik tidak menampilkan data pribadi pembooking, ID acak, rate limit, pesan error generik | Iterasi ID tidak menghasilkan data |
| T14 | Sistem dipakai mengirim email spam ke alamat orang lain | Sedang | Email hanya ke alamat dari Google Sign-In yang terverifikasi, tidak ada field penerima bebas, rate limit pengiriman | Tidak ada endpoint yang menerima alamat penerima dari client |
| T15 | Serangan DoS pada pencarian dan jadwal | Sedang | Cache daftar venue, query ter-index, batas pagination, rate limit per IP, WAF atau CDN bila perlu | Uji beban sederhana pada halaman publik |
| T16 | Venue nonaktif tetap menerima booking | Rendah | Status langganan dicek di server pada setiap pembuatan booking, bukan hanya disembunyikan di UI | Booking baru di venue nonaktif ditolak |
| T17 | Token QR atau data pribadi bocor lewat log, backup, atau email | Sedang | Jangan mencatat token dan isi bukti di log; samarkan email/telepon di log; backup terenkripsi; token QR terbatas jendela waktu dan sekali pakai (T7, T8) | Periksa log setelah alur booking: tidak ada token |
| T18 | Waktu dimanipulasi (zona waktu atau jam perangkat) | Rendah | Waktu server sebagai acuan, penyimpanan UTC, validasi jam operasional di server | Booking di luar jam operasional ditolak |
| T19 | Data pribadi disimpan terlalu lama atau terlalu banyak | Sedang | Kumpulkan data minimum (nama, email, nomor telepon terenkripsi), enkripsi bukti bayar saat disimpan (nama dan email sengaja tidak dienkripsi) (lihat Bagian 14 pada prompt aplikasi), sediakan kebijakan privasi, retensi bukti (90 hari setelah tanggal main), kemampuan menghapus data pengguna. Konsultasikan ke ahli hukum soal kewajiban pelindungan data pribadi yang berlaku | Job retensi menghapus bukti yang kedaluwarsa |

## Keputusan yang perlu ditetapkan

Nilai di bawah adalah ketetapan awal untuk pilot. Ubah sesuai kebutuhan venue pilot di `docs/ASSUMPTIONS.md`.

- **Retensi bukti bayar:** 90 hari setelah tanggal main, lalu dihapus otomatis.
- **Jendela validitas QR:** 60 menit sebelum mulai sampai akhir slot.
- **Jeda perubahan rekening:** 24 jam sebelum rekening baru tampil ke pembooking (kecuali rekening aktif pertama milik venue yang langsung tampil).
- **Laporan lintas bulan:** dicatat berdasarkan tanggal main (`play_date`), bukan tanggal transfer masuk.
- **Format bukti:** Fase 1 hanya JPG dan PNG. Izinkan PDF hanya jika sudah ada pemindaian antivirus di Fase berikutnya.

## Risiko yang tetap ada

- **Validasi manual bergantung pada ketelitian karyawan.** Sistem hanya bisa memberi peringatan (hash bukti, laporan selisih), bukan memastikan uang benar-benar masuk. Latih karyawan untuk mencocokkan dengan mutasi rekening.
- **Pembayaran di luar sistem** berarti sengketa uang tetap diselesaikan antara pembooking dan venue. Catat kebijakan ini di syarat layanan.
- **QR yang dibagikan sengaja oleh pembooking** tidak bisa dicegah sepenuhnya. Verifikasi nama dan nomor telepon di layar scan adalah pertahanan terakhir.

## Cara memakai dokumen ini

Tinjau ulang di akhir setiap fase dan saat menambah fitur baru (payment gateway, reschedule mandiri, pembatalan otomatis, WhatsApp). Setiap ancaman berisiko Tinggi harus punya tes otomatis sebelum fitur terkait dirilis.