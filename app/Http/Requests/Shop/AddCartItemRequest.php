<?php

namespace App\Http\Requests\Shop;

use App\Models\Catalogue\Product;
use Illuminate\Foundation\Http\FormRequest;

class AddCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
            // « Acheter maintenant » : même ajout, puis droit à la commande.
            'buy_now' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $product = Product::find($this->input('product_id'));

            if ($product && $product->variants()->exists() && ! $this->filled('variant_id')) {
                $validator->errors()->add('variant_id', 'Merci de sélectionner une variante.');
            }
        });
    }
}
