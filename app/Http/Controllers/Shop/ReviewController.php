<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\StoreReviewRequest;
use App\Models\Content\Review;
use Illuminate\Http\RedirectResponse;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request): RedirectResponse
    {
        // Avis soumis par une cliente : créé NON publié. Il n'apparaîtra sur le
        // site qu'après validation par l'admin (Contenu du site > Avis clients).
        // Piège à robots rempli : même réponse, rien d'enregistré.
        if (! $request->filled('website')) {
            Review::create($request->validated() + ['is_published' => false]);
        }

        return redirect()
            ->route('home')
            ->withFragment('avis')
            ->with('status', 'review-submitted');
    }
}
