<?php

namespace Database\Seeders;

use App\Models\Catalogue\Product;
use App\Models\Content\Post;
use Illuminate\Database\Seeder;

/**
 * Deux articles de démonstration (Phase 2.2D) pour montrer le module : la
 * cliente les remplace par ses propres histoires depuis Contenu > Journal.
 */
class JournalSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            [
                'title' => "L'art du tombé : bien choisir son hijab",
                'excerpt' => 'Soie, modal ou coton : comment la matière change la silhouette — et comment choisir.',
                'content' => '<p>Un hijab ne se choisit pas seulement à sa couleur : <strong>la matière fait le tombé</strong>, et le tombé fait la silhouette.</p><h2>La soie, pour les grandes occasions</h2><p>Brillance discrète, fluidité immédiate — elle demande un peu de pratique mais récompense toujours.</p><h2>Le modal, au quotidien</h2><p>Souple, il tient en place du matin au soir sans retouche.</p>',
                'keywords' => ['Hijab', 'Modal', 'Soie'],
            ],
            [
                'title' => "L'art d'offrir : nos coffrets",
                'excerpt' => 'Un parfum, un foulard, une attention : composer un cadeau qui compte.',
                'content' => '<p>Offrir, c\'est choisir à la place de l\'autre. Nos coffrets rassemblent des pièces qui se répondent : <strong>une matière, un sillage, une intention</strong>.</p><ul><li>Un foulard en soie, pour le geste</li><li>Une eau de parfum, pour la trace</li><li>Un mot, pour le sens</li></ul>',
                'keywords' => ['Parfum', 'Coffret', 'Cadeau', 'Broche'],
            ],
        ];

        foreach ($articles as $position => $data) {
            $post = Post::updateOrCreate(
                ['title' => $data['title']],
                [
                    'excerpt' => $data['excerpt'],
                    'content' => $data['content'],
                    'is_published' => true,
                    'published_at' => now()->subDays(7 - $position),
                    'position' => $position,
                ],
            );

            $ids = Product::query()
                ->where(function ($query) use ($data): void {
                    foreach ($data['keywords'] as $keyword) {
                        $query->orWhere('name', 'like', "%{$keyword}%");
                    }
                })
                ->limit(4)
                ->pluck('id');

            $post->products()->syncWithoutDetaching($ids);
        }
    }
}
