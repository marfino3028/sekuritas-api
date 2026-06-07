<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * CMS ArticleController — CRUD artikel & berita.
 */
class ArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Article::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(fn ($q) => $q->where('title', 'like', "%{$keyword}%"));
        }

        $perPage  = min((int) $request->input('per_page', 20), 100);
        $articles = $query->orderByDesc('published_at')->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $articles->items(),
            'meta'    => [
                'current_page' => $articles->currentPage(),
                'last_page'    => $articles->lastPage(),
                'per_page'     => $articles->perPage(),
                'total'        => $articles->total(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $article = Article::findOrFail($id);
        return response()->json(['success' => true, 'data' => $article]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title'        => 'required|string|max:200',
            'slug'         => 'nullable|string|max:220|unique:articles,slug',
            'category'     => 'required|string|max:60',
            'excerpt'      => 'nullable|string|max:500',
            'content'      => 'required|string',
            'image_url'    => 'nullable|string|max:500',
            'author'       => 'nullable|string|max:120',
            'source'       => 'nullable|string|max:200',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['slug'] = $data['slug'] ?? $this->uniqueSlug($data['title']);
        $data['published_at'] = $data['published_at'] ?? now();
        $data['author'] = $data['author'] ?? 'Tim Riset Sekuritas';

        $article = Article::create($data);

        return response()->json([
            'success' => true,
            'message' => "Artikel '{$article->title}' berhasil dibuat.",
            'data'    => $article,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $article = Article::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title'        => 'string|max:200',
            'slug'         => "nullable|string|max:220|unique:articles,slug,{$id}",
            'category'     => 'string|max:60',
            'excerpt'      => 'nullable|string|max:500',
            'content'      => 'string',
            'image_url'    => 'nullable|string|max:500',
            'author'       => 'nullable|string|max:120',
            'source'       => 'nullable|string|max:200',
            'is_published' => 'boolean',
            'published_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validasi gagal', 'errors' => $validator->errors()], 422);
        }

        $article->update($request->only([
            'title', 'slug', 'category', 'excerpt', 'content',
            'image_url', 'author', 'source', 'is_published', 'published_at',
        ]));

        return response()->json([
            'success' => true,
            'message' => "Artikel '{$article->title}' berhasil diperbarui.",
            'data'    => $article->fresh(),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $article = Article::findOrFail($id);
        $article->delete();

        return response()->json(['success' => true, 'message' => 'Artikel berhasil dihapus.']);
    }

    public function toggle(int $id): JsonResponse
    {
        $article = Article::findOrFail($id);
        $article->update(['is_published' => ! $article->is_published]);

        return response()->json([
            'success' => true,
            'message' => $article->is_published ? 'Artikel dipublikasikan.' : 'Artikel disembunyikan.',
            'data'    => $article,
        ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;
        while (Article::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}
