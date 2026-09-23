<?php

namespace Database\Seeders;

use App\Models\Article;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seed artikel blog PT LiF Manajemen Investasi (halaman Blog / Artikel).
 *
 * Isi = artikel asli lif-investasi.co.id (database/seeders/data/lif-articles.json).
 * Gambar sampul disajikan web depan di /images/lif/artikel/, jadi URL-nya disusun dari FRONTEND_URL.
 */
class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = json_decode(file_get_contents(__DIR__ . '/data/lif-articles.json'), true);
        $frontend = rtrim(config('app.frontend_url'), '/');

        // Contoh artikel generik dari migration create_articles_table — diganti artikel asli LiF
        Article::whereIn('slug', [
            'ihsg-cetak-rekor-tembus-7400',
            'mengenal-reksa-dana-panduan-pemula',
            '5-tips-memilih-reksa-dana-sesuai-profil-risiko',
            'reksa-dana-pasar-uang-vs-deposito',
            'outlook-pasar-modal-2026',
            'cara-mulai-investasi-reksa-dana-10000',
        ])->delete();

        foreach ($articles as $a) {
            Article::firstOrCreate(
                ['slug' => $a['slug']],
                [
                    'title'        => $a['title'],
                    'category'     => $a['category'],
                    'excerpt'      => $a['excerpt'],
                    'content'      => $a['content'],
                    'image_url'    => $frontend . $a['image'],
                    'author'       => $a['author'],
                    'source'       => 'lif-investasi.co.id',
                    'is_published' => true,
                    'published_at' => Carbon::parse($a['published_at']),
                ]
            );
        }

        $this->command->info('Artikel blog LiF berhasil di-seed (' . count($articles) . ' artikel).');
    }
}
