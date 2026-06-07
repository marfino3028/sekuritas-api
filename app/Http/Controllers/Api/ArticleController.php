<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ArticleController — Artikel & berita pasar modal.
 * Endpoint publik, tidak membutuhkan autentikasi.
 */
class ArticleController extends Controller
{
    /**
     * Daftar artikel terbit dengan filter kategori & pencarian.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Article::published();

        if ($request->filled('category') && $request->category !== 'Semua') {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('excerpt', 'like', "%{$keyword}%");
            });
        }

        $perPage  = min((int) $request->input('per_page', 9), 30);
        $articles = $query->orderByDesc('published_at')->paginate($perPage);

        $categories = Article::published()
            ->select('category')->distinct()->orderBy('category')->pluck('category');

        return response()->json([
            'success'    => true,
            'data'       => $articles->items(),
            'categories' => $categories,
            'meta'       => [
                'current_page' => $articles->currentPage(),
                'last_page'    => $articles->lastPage(),
                'per_page'     => $articles->perPage(),
                'total'        => $articles->total(),
            ],
        ]);
    }

    /**
     * Detail artikel berdasarkan slug + artikel terkait.
     */
    public function show(string $slug): JsonResponse
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();

        $related = Article::published()
            ->where('id', '!=', $article->id)
            ->where('category', $article->category)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get(['id', 'title', 'slug', 'category', 'excerpt', 'image_url', 'published_at']);

        return response()->json([
            'success' => true,
            'data'    => $article,
            'related' => $related,
        ]);
    }
}
