<?php

namespace App\Http\Controllers\Public;

use App\Actions\Landing\GetPublicVenues;
use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SeoController extends Controller
{
    public const SITEMAP_CACHE_KEY = 'seo:sitemap:v1';

    public const LLMS_CACHE_KEY = 'seo:llms:v1';

    public function sitemap(Request $request, GetPublicVenues $getPublicVenues): Response
    {
        $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');

        $xml = Cache::remember(self::SITEMAP_CACHE_KEY, now()->addMinutes(10), function () use ($baseUrl, $getPublicVenues): string {
            $venues = $getPublicVenues->execute(withDetails: false);

            $urls = [
                [
                    'loc' => $baseUrl.'/',
                    'changefreq' => 'daily',
                    'priority' => '1.0',
                    'lastmod' => null,
                ],
                [
                    'loc' => $baseUrl.'/venues',
                    'changefreq' => 'daily',
                    'priority' => '0.9',
                    'lastmod' => null,
                ],
            ];

            foreach ($venues as $venue) {
                $urls[] = [
                    'loc' => $baseUrl.'/venue/'.rawurlencode($venue->slug),
                    'changefreq' => 'daily',
                    'priority' => '0.8',
                    'lastmod' => $venue->updated_at?->toAtomString(),
                ];
            }

            $lines = [
                '<?xml version="1.0" encoding="UTF-8"?>',
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
            ];

            foreach ($urls as $item) {
                $lines[] = '  <url>';
                $lines[] = '    <loc>'.e($item['loc']).'</loc>';
                if ($item['lastmod'] !== null) {
                    $lines[] = '    <lastmod>'.e($item['lastmod']).'</lastmod>';
                }
                $lines[] = '    <changefreq>'.$item['changefreq'].'</changefreq>';
                $lines[] = '    <priority>'.$item['priority'].'</priority>';
                $lines[] = '  </url>';
            }

            $lines[] = '</urlset>';

            return implode("\n", $lines)."\n";
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function robots(Request $request): Response
    {
        $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');

        $content = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /dashboard',
            'Disallow: /login',
            'Disallow: /account',
            'Disallow: /staff/login',
            '',
            'Sitemap: '.$baseUrl.'/sitemap.xml',
        ])."\n";

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function llms(Request $request, GetPublicVenues $getPublicVenues): Response
    {
        $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');
        $appName = config('app.name', 'ArenaKita');

        $content = Cache::remember(self::LLMS_CACHE_KEY, now()->addMinutes(10), function () use ($appName, $baseUrl, $getPublicVenues): string {
            $venues = $getPublicVenues->execute(withDetails: false);

            $lines = [
                "# {$appName}",
                '',
                '> Platform SaaS booking lapangan olahraga multi-venue (futsal, mini soccer, badminton, dan cabang olahraga lainnya) di Indonesia dengan anti double booking dan verifikasi pembayaran transparan.',
                '',
                '## Halaman Utama',
                "- [Beranda]({$baseUrl}/): Pencarian venue berdasarkan kategori olahraga dan kota, cara kerja booking, venue unggulan, paket pemilik venue, dan FAQ.",
                "- [Daftar Venue]({$baseUrl}/venues): Pencarian dan filter venue olahraga aktif.",
                "- [Sitemap XML]({$baseUrl}/sitemap.xml): Daftar URL publik terindeks.",
                '',
                '## Cara Kerja Booking',
                '1. Pilih cabang olahraga, kota, venue, dan slot jam kosong (mendukung multi-jam berurutan).',
                '2. Masuk dengan akun Google saat konfirmasi slot, lalu pilih pembayaran DP atau lunas.',
                '3. Unggah bukti transfer ke rekening atau QRIS resmi milik venue sebelum batas waktu hold berakhir.',
                '4. Dapatkan tiket QR check-in unik setelah pembayaran disetujui oleh staf venue.',
                '',
                '## Venue Aktif',
            ];

            if ($venues->isEmpty()) {
                $lines[] = '- Belum ada venue publik aktif saat ini.';
            } else {
                foreach ($venues as $venue) {
                    /** @var Venue $venue */
                    $lines[] = "- [{$venue->name}]({$baseUrl}/venue/{$venue->slug}): {$venue->city} — {$venue->address}";
                }
            }

            return implode("\n", $lines)."\n";
        });

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
