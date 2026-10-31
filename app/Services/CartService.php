<?php

namespace App\Services;

use App\Exceptions\CartException;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductVariant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartService
{
    /**
     * Résout « le » panier du visiteur courant : par compte s'il est
     * authentifié, sinon par l'id de session en cours. N'accepte jamais un
     * identifiant de panier/session venant de la requête — c'est ce qui
     * garantit l'isolation stricte des paniers invités entre eux (un
     * visiteur ne peut jamais atteindre que le sien).
     */
    public function currentCart(Request $request): Cart
    {
        if ($user = $request->user()) {
            return Cart::firstOrCreate(['user_id' => $user->id]);
        }

        return $this->cartForSession($request->session()->getId());
    }

    public function cartForSession(string $sessionId): Cart
    {
        return Cart::firstOrCreate(['session_id' => $sessionId]);
    }

    /**
     * Comptage en lecture seule pour le badge du header — ne crée
     * volontairement pas de panier, contrairement à currentCart(), pour que
     * simplement naviguer n'écrive jamais de ligne en base.
     */
    public function currentItemCount(Request $request): int
    {
        $cart = $request->user()
            ? Cart::where('user_id', $request->user()->id)->first()
            : Cart::where('session_id', $request->session()->getId())->first();

        return $cart?->items()->sum('quantity') ?? 0;
    }

    /**
     * Panier existant du visiteur avec ses lignes chargées, pour l'affichage du
     * tiroir dans le header. Comme currentItemCount(), NE crée jamais de panier
     * (lecture seule) — naviguer ne doit rien écrire en base. Retourne null si
     * le visiteur n'a pas encore de panier.
     */
    public function currentCartForDisplay(Request $request): ?Cart
    {
        $cart = $request->user()
            ? Cart::where('user_id', $request->user()->id)->first()
            : Cart::where('session_id', $request->session()->getId())->first();

        $cart?->load(['items.product.images', 'items.product.category', 'items.variant']);

        return $cart;
    }

    /**
     * @throws CartException
     */
    /**
     * `$replace` (« Acheter maintenant ») : la ligne prend la quantité choisie
     * sur la fiche au lieu de s'y ajouter — un article déjà au panier n'est
     * pas acheté en double.
     */
    public function addItem(Cart $cart, Product $product, ?ProductVariant $variant, int $quantity, bool $replace = false): CartItem
    {
        if (! $product->is_active) {
            throw CartException::productUnavailable();
        }

        if ($product->variants()->exists() && ! $variant) {
            throw CartException::variantRequired();
        }

        if ($variant && ($variant->product_id !== $product->id || ! $variant->is_active)) {
            throw CartException::variantUnavailable();
        }

        return DB::transaction(function () use ($cart, $product, $variant, $quantity, $replace) {
            $item = CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->where('product_variant_id', $variant?->id)
                ->lockForUpdate()
                ->first();

            $newQuantity = $replace ? $quantity : ($item->quantity ?? 0) + $quantity;

            if ($variant && $newQuantity > $variant->stock) {
                throw CartException::insufficientStock($variant->stock);
            }

            if ($item) {
                $item->update(['quantity' => $newQuantity]);

                return $item->fresh();
            }

            return CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $quantity,
            ]);
        });
    }

    /**
     * @throws CartException
     */
    public function updateQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity < 1) {
            $this->removeItem($item);

            return;
        }

        if ($item->variant && $quantity > $item->variant->stock) {
            throw CartException::insufficientStock($item->variant->stock);
        }

        $item->update(['quantity' => $quantity]);
    }

    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    /**
     * Appelée une fois à la connexion, avec l'id de session capturé côté
     * serveur avant la régénération de session — jamais avec un id fourni
     * par le client.
     */
    public function mergeGuestCartIntoUser(string $guestSessionId, User $user): Cart
    {
        return DB::transaction(function () use ($guestSessionId, $user) {
            $guestCart = Cart::where('session_id', $guestSessionId)->first();
            $userCart = Cart::firstOrCreate(['user_id' => $user->id]);

            if (! $guestCart || $guestCart->id === $userCart->id) {
                return $userCart;
            }

            foreach ($guestCart->items as $guestItem) {
                $existing = CartItem::query()
                    ->where('cart_id', $userCart->id)
                    ->where('product_id', $guestItem->product_id)
                    ->where('product_variant_id', $guestItem->product_variant_id)
                    ->first();

                if ($existing) {
                    $mergedQuantity = $existing->quantity + $guestItem->quantity;

                    if ($guestItem->variant) {
                        $mergedQuantity = min($mergedQuantity, $guestItem->variant->stock);
                    }

                    $existing->update(['quantity' => $mergedQuantity]);
                } else {
                    $guestItem->update(['cart_id' => $userCart->id]);
                }
            }

            $guestCart->delete();

            return $userCart->fresh('items');
        });
    }
}
