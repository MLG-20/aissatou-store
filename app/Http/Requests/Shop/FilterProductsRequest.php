<?php

namespace App\Http\Requests\Shop;

use App\Models\Catalogue\Category;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Material;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class FilterProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'collection_id' => ['nullable', 'integer', 'exists:collections,id'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            // gte:min_price ne doit s'appliquer que si min_price est réellement fourni,
            // sinon la règle échoue en comparant max_price à un champ absent.
            'max_price' => array_filter([
                'nullable', 'numeric', 'min:0',
                $this->filled('min_price') ? 'gte:min_price' : null,
            ]),
            'in_stock' => ['nullable', 'boolean'],
            // Paramètre en français comme le reste des routes publiques : c'est
            // une URL que la cliente peut partager telle quelle.
            'nouveautes' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Valeurs prêtes à l'emploi pour Product::scopeFilter() — le contrôleur
     * n'a jamais à relire les paramètres bruts de la requête.
     *
     * @return array{q: ?string, category_id: ?int, material_id: ?int, collection_id: ?int, min_price: ?float, max_price: ?float, in_stock: bool, new_arrivals: bool}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        // Une catégorie inactive ne doit pas pouvoir être utilisée pour filtrer
        // (même logique anti-énumération que sur les pages catégorie/produit).
        $categoryId = $validated['category_id'] ?? null;
        if ($categoryId !== null && ! Category::query()->active()->whereKey($categoryId)->exists()) {
            $categoryId = null;
        }

        // Une matière inactive ne doit pas pouvoir être utilisée pour filtrer,
        // comme pour les catégories ci-dessus.
        $materialId = $validated['material_id'] ?? null;
        if ($materialId !== null && ! Material::query()->active()->whereKey($materialId)->exists()) {
            $materialId = null;
        }

        return [
            'q' => $validated['q'] ?? null,
            'category_id' => $categoryId,
            'material_id' => $materialId,
            'collection_id' => $this->activeCollectionId($validated['collection_id'] ?? null),
            'min_price' => isset($validated['min_price']) ? (float) $validated['min_price'] : null,
            'max_price' => isset($validated['max_price']) ? (float) $validated['max_price'] : null,
            'in_stock' => $this->boolean('in_stock'),
            'new_arrivals' => $this->boolean('nouveautes'),
        ];
    }

    /**
     * Une collection inactive ne doit pas pouvoir être utilisée pour filtrer,
     * comme pour les catégories et les matières ci-dessus.
     */
    private function activeCollectionId(?int $collectionId): ?int
    {
        if ($collectionId !== null && ! Collection::query()->active()->whereKey($collectionId)->exists()) {
            return null;
        }

        return $collectionId;
    }
}
