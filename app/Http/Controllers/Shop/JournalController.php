<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Content\Post;
use Illuminate\View\View;

class JournalController extends Controller
{
    public function index(): View
    {
        return view('shop.journal', [
            // Produits liés chargés pour la couverture de repli (Post::coverUrl).
            'posts' => Post::query()->published()
                ->with(['products' => fn ($query) => $query->active()->with('images')])
                ->orderByDesc('published_at')
                ->paginate(9),
        ]);
    }

    public function show(Post $post): View
    {
        // Brouillon ou parution future : 404 (pas d'aperçu public).
        abort_unless($post->is_published && $post->published_at && ! $post->published_at->isFuture(), 404);

        $post->load(['products' => fn ($query) => $query->active()->with(['category', 'images', 'variants'])]);

        return view('shop.post', [
            'post' => $post,
            'others' => Post::query()->published()
                ->whereKeyNot($post->id)
                ->with(['products' => fn ($query) => $query->active()->with('images')])
                ->orderByDesc('published_at')
                ->take(3)
                ->get(),
        ]);
    }
}
