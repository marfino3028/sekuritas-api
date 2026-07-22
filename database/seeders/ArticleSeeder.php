<?php

namespace Database\Seeders;

use App\Models\Article;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed artikel edukasi & berita pasar modal (halaman Education / Artikel).
 * Selaras dengan design/listartikel.png & design/detailartikel.png.
 */
class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            ['Mengenal Reksa Dana: Panduan Pemula Sebelum Berinvestasi', 'Edukasi',
             'Reksa dana adalah wadah menghimpun dana investor untuk dikelola Manajer Investasi. Pelajari jenis, risiko, dan cara memulainya dengan modal Rp 10.000.'],
            ['5 Jenis Reksa Dana dan Cara Memilih yang Tepat', 'Edukasi',
             'Pasar Uang, Pendapatan Tetap, Campuran, Saham, dan Syariah — masing-masing punya profil risiko dan potensi imbal hasil berbeda. Kenali sebelum memilih.'],
            ['Apa Itu NAV/Unit dan Kenapa Penting?', 'Edukasi',
             'Nilai Aktiva Bersih per Unit (NAV/UP) adalah harga satu unit reksa dana. Memahami NAV membantu Anda menilai kinerja dan waktu pembelian.'],
            ['Tips Mengatur Profil Risiko Investasi Sesuai Tujuan', 'Edukasi',
             'Konservatif, moderat, atau agresif? Profil risiko menentukan komposisi portofolio yang ideal untuk tujuan keuangan Anda.'],
            ['IHSG Menguat, Sentimen Positif Dorong Pasar Saham', 'Berita Pasar',
             'Indeks Harga Saham Gabungan ditutup menguat didukung aliran dana asing dan optimisme laporan keuangan emiten kuartal ini.'],
            ['Suku Bunga Acuan Stabil, Ini Dampaknya ke Reksa Dana Pendapatan Tetap', 'Berita Pasar',
             'Keputusan bank sentral mempertahankan suku bunga memberi ruang bagi reksa dana pendapatan tetap untuk mencatatkan kinerja positif.'],
            ['Strategi Dollar Cost Averaging untuk Investor Pemula', 'Edukasi',
             'Berinvestasi rutin dalam jumlah tetap membantu meredam volatilitas pasar dan membangun disiplin investasi jangka panjang.'],
            ['Reksa Dana Syariah: Investasi Sesuai Prinsip Halal', 'Edukasi',
             'Dikelola sesuai prinsip syariah dan diawasi Dewan Pengawas Syariah, cocok bagi investor yang mengutamakan aspek kepatuhan.'],
            ['Memahami Biaya dalam Reksa Dana: Management Fee hingga Subscription Fee', 'Edukasi',
             'Biaya kecil berdampak besar dalam jangka panjang. Pahami komponen biaya agar imbal hasil bersih Anda optimal.'],
            ['Persiapan Dana Pensiun Sejak Muda dengan Reksa Dana', 'Edukasi',
             'Semakin awal memulai, semakin ringan. Simulasi sederhana menunjukkan kekuatan compounding untuk masa pensiun Anda.'],
            ['Diversifikasi: Jangan Menaruh Semua Telur dalam Satu Keranjang', 'Edukasi',
             'Menyebar investasi ke beberapa instrumen dan Manajer Investasi menurunkan risiko tanpa mengorbankan potensi imbal hasil.'],
            ['Rupiah Bergerak Stabil, Investor Cermati Data Inflasi', 'Berita Pasar',
             'Nilai tukar rupiah relatif stabil seiring rilis data inflasi yang terkendali, menjaga daya tarik aset domestik.'],
        ];

        foreach ($articles as $i => [$title, $category, $excerpt]) {
            Article::firstOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'title'        => $title,
                    'category'     => $category,
                    'excerpt'      => $excerpt,
                    'content'      => $this->body($title, $excerpt),
                    'image_url'    => "https://picsum.photos/seed/vs-artikel-{$i}/1200/630",
                    'author'       => $category === 'Berita Pasar' ? 'Tim Riset Victoria Sekuritas' : 'Tim Edukasi Victoria Sekuritas',
                    'source'       => $category === 'Berita Pasar' ? 'Riset Internal' : null,
                    'is_published' => true,
                    'published_at' => Carbon::now()->subDays(($i + 1) * 3),
                ]
            );
        }

        $this->command->info('Artikel edukasi & berita berhasil di-seed (' . count($articles) . ' artikel).');
    }

    private function body(string $title, string $excerpt): string
    {
        return "<p><strong>{$excerpt}</strong></p>"
            . "<p>Investasi reksa dana kini semakin mudah diakses siapa saja. Melalui platform Victoria Sekuritas, "
            . "Anda dapat mulai berinvestasi dengan nominal terjangkau, memantau kinerja secara transparan, dan "
            . "memilih produk sesuai profil risiko Anda.</p>"
            . "<h3>Poin Penting</h3><ul>"
            . "<li>Mulai dari nominal kecil dan tingkatkan secara bertahap.</li>"
            . "<li>Pahami profil risiko sebelum memilih produk.</li>"
            . "<li>Investasi jangka panjang cenderung meredam volatilitas.</li>"
            . "</ul>"
            . "<p><em>Peringatan: Investasi melalui reksa dana mengandung risiko. Kinerja masa lalu tidak "
            . "mencerminkan kinerja masa depan. Bacalah prospektus sebelum berinvestasi.</em></p>";
    }
}
