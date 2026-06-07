<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->default('Berita Pasar');
            $table->string('excerpt', 500)->nullable();
            $table->longText('content');
            $table->string('image_url')->nullable();
            $table->string('author')->default('Tim Riset Sekuritas');
            $table->string('source')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // Seed artikel demo (dijalankan juga di DB produksi yang sudah ter-seed)
        $now = now();
        $p = function (string $body): string {
            return implode("\n\n", array_map('trim', explode('|', $body)));
        };

        $articles = [
            [
                'title'    => 'IHSG Cetak Rekor Baru, Tembus 7.400 Ditopang Saham Perbankan',
                'slug'     => 'ihsg-cetak-rekor-tembus-7400',
                'category' => 'Berita Pasar',
                'excerpt'  => 'Indeks Harga Saham Gabungan ditutup menguat 1,12% ke level 7.426 seiring derasnya aliran dana asing ke saham-saham bank besar.',
                'content'  => $p('Indeks Harga Saham Gabungan (IHSG) kembali mencetak rekor tertinggi sepanjang masa dengan ditutup menguat 1,12% ke level 7.426,50 pada perdagangan hari ini. Penguatan ditopang oleh saham-saham perbankan berkapitalisasi besar yang menjadi penggerak utama indeks.|Aliran dana asing (foreign net buy) tercatat mencapai Rp1,2 triliun di pasar reguler, melanjutkan tren akumulasi sejak awal kuartal. Sektor keuangan, konsumer, dan infrastruktur menjadi sektor dengan kontribusi penguatan terbesar.|Analis menilai sentimen positif didorong oleh ekspektasi penurunan suku bunga acuan serta rilis kinerja emiten yang di atas konsensus. Bagi investor reksa dana saham, momentum ini berpotensi menguntungkan dalam jangka menengah, namun tetap perlu memperhatikan profil risiko masing-masing.'),
                'author'   => 'Tim Riset Sekuritas',
                'published_at' => $now->copy()->subHours(4),
            ],
            [
                'title'    => 'Mengenal Reksa Dana: Panduan Lengkap untuk Investor Pemula',
                'slug'     => 'mengenal-reksa-dana-panduan-pemula',
                'category' => 'Edukasi',
                'excerpt'  => 'Reksa dana adalah wadah investasi yang dikelola Manajer Investasi profesional. Pahami jenis, cara kerja, dan keuntungannya di sini.',
                'content'  => $p('Reksa dana adalah wadah yang digunakan untuk menghimpun dana dari masyarakat pemodal untuk selanjutnya diinvestasikan dalam portofolio efek oleh Manajer Investasi (MI). Dengan reksa dana, investor pemula dapat berinvestasi di pasar modal tanpa harus memiliki pengetahuan mendalam atau modal besar.|Terdapat empat jenis utama reksa dana: Pasar Uang (risiko paling rendah, cocok untuk jangka pendek), Pendapatan Tetap (berbasis obligasi), Campuran (kombinasi saham dan obligasi), dan Saham (potensi imbal hasil tertinggi dengan risiko tertinggi).|Keuntungan utama reksa dana antara lain: dikelola secara profesional, terdiversifikasi sehingga risiko lebih tersebar, likuiditas tinggi, terjangkau mulai dari Rp10.000, serta diawasi oleh Otoritas Jasa Keuangan (OJK). Pastikan memilih produk yang sesuai dengan tujuan dan profil risiko Anda.'),
                'author'   => 'Tim Edukasi Sekuritas',
                'published_at' => $now->copy()->subDay(),
            ],
            [
                'title'    => '5 Tips Memilih Reksa Dana Sesuai Profil Risiko Anda',
                'slug'     => '5-tips-memilih-reksa-dana-sesuai-profil-risiko',
                'category' => 'Tips',
                'excerpt'  => 'Jangan asal pilih. Cocokkan produk reksa dana dengan profil risiko, tujuan, dan jangka waktu investasi Anda dengan 5 langkah ini.',
                'content'  => $p('1. Kenali profil risiko Anda. Lakukan kuesioner profil risiko untuk mengetahui apakah Anda termasuk tipe Konservatif, Moderat, atau Agresif. Ini menentukan jenis reksa dana yang sesuai.|2. Tentukan tujuan dan jangka waktu. Untuk dana darurat atau kebutuhan < 1 tahun, pilih Pasar Uang. Untuk tujuan jangka panjang seperti pensiun, reksa dana Saham lebih relevan.|3. Perhatikan rekam jejak Manajer Investasi dan AUM. MI dengan AUM besar dan konsisten umumnya lebih kredibel.|4. Bandingkan kinerja historis dan biaya (management fee, subscription/redemption fee). Imbal hasil masa lalu bukan jaminan, tetapi konsistensi penting.|5. Diversifikasi. Jangan menaruh semua dana pada satu produk. Sebarkan ke beberapa jenis sesuai komposisi yang nyaman bagi Anda.'),
                'author'   => 'Tim Edukasi Sekuritas',
                'published_at' => $now->copy()->subDays(2),
            ],
            [
                'title'    => 'Reksa Dana Pasar Uang vs Deposito: Mana yang Lebih Menguntungkan?',
                'slug'     => 'reksa-dana-pasar-uang-vs-deposito',
                'category' => 'Edukasi',
                'excerpt'  => 'Keduanya instrumen rendah risiko, tetapi punya karakter berbeda soal imbal hasil, likuiditas, dan pajak. Simak perbandingannya.',
                'content'  => $p('Reksa dana pasar uang dan deposito sama-sama dikenal sebagai instrumen berisiko rendah. Namun keduanya memiliki perbedaan yang penting dipahami sebelum memilih.|Dari sisi imbal hasil, reksa dana pasar uang historis memberikan return sekitar 4-6% per tahun, umumnya sedikit lebih tinggi dari deposito. Yang menarik, keuntungan reksa dana belum dipotong pajak final 20% seperti bunga deposito, sehingga net return bisa lebih kompetitif.|Dari sisi likuiditas, reksa dana pasar uang lebih fleksibel: dana dapat dicairkan kapan saja tanpa penalti, sementara deposito memiliki jatuh tempo dan penalti pencairan dini. Namun deposito dijamin LPS hingga Rp2 miliar, sedangkan reksa dana nilainya berfluktuasi (meski sangat stabil untuk pasar uang).'),
                'author'   => 'Tim Riset Sekuritas',
                'published_at' => $now->copy()->subDays(3),
            ],
            [
                'title'    => 'Outlook Pasar Modal 2026: Peluang di Tengah Tren Penurunan Suku Bunga',
                'slug'     => 'outlook-pasar-modal-2026',
                'category' => 'Analisis',
                'excerpt'  => 'Penurunan suku bunga acuan diperkirakan menjadi katalis positif bagi pasar saham dan obligasi sepanjang 2026. Ini sektor yang layak dicermati.',
                'content'  => $p('Memasuki 2026, pelaku pasar memperkirakan tren penurunan suku bunga acuan akan berlanjut seiring inflasi yang terkendali. Lingkungan suku bunga yang lebih rendah secara historis menjadi katalis positif bagi pasar saham dan obligasi.|Untuk reksa dana pendapatan tetap, penurunan suku bunga cenderung mendorong kenaikan harga obligasi sehingga berpotensi memberikan capital gain. Sementara reksa dana saham diuntungkan oleh ekspektasi perbaikan valuasi dan daya beli.|Sektor yang layak dicermati antara lain perbankan, konsumer, dan infrastruktur yang sensitif terhadap pertumbuhan ekonomi domestik. Tetap disiplin pada strategi jangka panjang dan diversifikasi untuk mengelola volatilitas jangka pendek.'),
                'author'   => 'Tim Riset Sekuritas',
                'published_at' => $now->copy()->subDays(5),
            ],
            [
                'title'    => 'Cara Mulai Investasi Reksa Dana dari Rp10.000',
                'slug'     => 'cara-mulai-investasi-reksa-dana-10000',
                'category' => 'Tips',
                'excerpt'  => 'Investasi kini bisa dimulai dari uang jajan. Ikuti langkah praktis memulai reksa dana secara online yang aman dan diawasi OJK.',
                'content'  => $p('Investasi reksa dana kini sangat terjangkau dan bisa dimulai hanya dengan Rp10.000. Berikut langkah praktis memulainya secara online.|Pertama, lengkapi registrasi dan verifikasi data diri (KYC) untuk memperoleh Single Investor Identification (SID) dari KSEI. Proses ini wajib dan menjamin kepemilikan Anda tercatat resmi.|Kedua, isi kuesioner profil risiko agar rekomendasi produk sesuai karakter Anda. Ketiga, pilih produk reksa dana, lakukan pembelian (subscription), dan selesaikan pembayaran. Unit akan tercatat di portofolio Anda setelah dana settle.|Mulailah dari nominal kecil secara rutin (dollar cost averaging) untuk membangun kebiasaan investasi yang sehat. Semua transaksi diawasi OJK dan dana disimpan terpisah di Bank Kustodian.'),
                'author'   => 'Tim Edukasi Sekuritas',
                'published_at' => $now->copy()->subDays(7),
            ],
        ];

        foreach ($articles as $a) {
            DB::table('articles')->insert(array_merge($a, [
                'is_published' => true,
                'created_at'   => $a['published_at'],
                'updated_at'   => $a['published_at'],
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
