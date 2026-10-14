<?php

namespace App\Services;

use App\Exceptions\WishlistException;
use App\Models\Catalogue\Product;
use App\Models\User;
use App\Models\Wishlist\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Liste d'envies : même contrat d'isolation et de fusion que CartService
 * (exigence du spec 02.2 — ne pas réinventer une seconde mécanique) :
 * résolution par compte ou par id de session serveur uniquement, fusion
 * sans doublon à la connexion/inscription.
 */
class WishlistService
{
    public function currentWishlist(Request $request): Wishlist
    {
        if ($user = $request->user()) {
            return Wishlist::firstOrCreate(['user_id' => $user->id]);
        }

        return Wishlist::firstOrCreate(['session_id' => $request->session()->getId()]);
    }

    /**
     * Ids des produits mis de côté, en lecture seule (badge header, état des
     * cœurs) — ne crée jamais de liste, comme currentItemCount() du panier.
     *
     * @return list<int>
     */
    public function currentProductIds(Request $request): array
    {
        $wishlist = $request->user()
            ? Wishlist::where('user_id', $request->user()->id)->first()
            : Wishlist::where('session_id', $request->session()->getId())->first();

        if (! $wishlist) {
            return [];
        }

        return $wishlist->items()->pluck('product_id')->all();
    }

    /**
     * Ajoute ou retire (vrai interrupteur) — renvoie true si ajouté.
     *
     * @throws WishlistException
     */
    public function toggle(Wishlist $wishlist, Product $product): bool
    {
        if (! $product->is_active) {
            throw WishlistException::productUnavailable();
        }

        return DB::transaction(function () use ($wishlist, $product) {
            $existing = $wishlist->items()->where('product_id', $product->id)->first();

            if ($existing) {
                $existing->delete();

                return false;
            }

            $wishlist->items()->create(['product_id' => $product->id]);

            return true;
        });
    }

    public function mergeGuestWishlistIntoUser(string $guestSessionId, User $user): Wishlist
    {
        return DB::transaction(function () use ($guestSessionId, $user) {
            $guestWishlist = Wishlist::where('session_id', $guestSessionId)->first();
            $userWishlist = Wishlist::firstOrCreate(['user_id' => $user->id]);

            if (! $guestWishlist || $guestWishlist->id === $userWishlist->id) {
                return $userWishlist;
            }

            $existingIds = $userWishlist->items()->pluck('product_id')->all();

            foreach ($guestWishlist->items as $item) {
                if (! in_array($item->product_id, $existingIds, true)) {
                    $item->update(['wishlist_id' => $userWishlist->id]);
                }
            }

            $guestWishlist->delete();

            return $userWishlist->fresh('items');
        });
    }
}
