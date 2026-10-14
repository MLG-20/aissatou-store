<?php

namespace Tests\Feature\Shop;

use App\Models\Catalogue\Product;
use App\Models\User;
use App\Models\Wishlist\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    private string $guestSessionId;

    private function fixedGuestSessionId(): string
    {
        return $this->guestSessionId ??= Str::random(40);
    }

    private function asGuestSession(): static
    {
        return $this->withCookie(config('session.cookie'), $this->fixedGuestSessionId());
    }

    public function test_a_guest_can_toggle_a_product_and_see_it(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->asGuestSession()->post(route('shop.wishlist.toggle', $product))
            ->assertRedirect();

        $this->asGuestSession()->get(route('shop.wishlist'))
            ->assertOk()
            ->assertSee($product->name);
    }

    public function test_toggling_twice_removes_the_product_without_duplicating(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->asGuestSession()->post(route('shop.wishlist.toggle', $product));
        $this->asGuestSession()->post(route('shop.wishlist.toggle', $product));

        $this->assertDatabaseCount('wishlist_items', 0);
        $this->asGuestSession()->get(route('shop.wishlist'))->assertDontSee($product->name);
    }

    public function test_two_guest_sessions_have_isolated_wishlists(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $this->asGuestSession()->post(route('shop.wishlist.toggle', $product));

        $this->withCookie(config('session.cookie'), Str::random(40))
            ->get(route('shop.wishlist'))
            ->assertOk()
            ->assertDontSee($product->name);
    }

    public function test_an_inactive_product_is_rejected(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $this->asGuestSession()->post(route('shop.wishlist.toggle', $product))
            ->assertSessionHasErrors('wishlist');

        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_guest_wishlist_merges_into_the_account_on_login_without_duplicates(): void
    {
        $user = User::factory()->create();
        $shared = Product::factory()->create(['is_active' => true]);
        $guestOnly = Product::factory()->create(['is_active' => true]);

        // Déjà dans le compte avant la connexion…
        $userWishlist = Wishlist::create(['user_id' => $user->id]);
        $userWishlist->items()->create(['product_id' => $shared->id]);

        // …et dans la session invitée (dont un doublon volontaire).
        $this->asGuestSession()->post(route('shop.wishlist.toggle', $shared));
        $this->asGuestSession()->post(route('shop.wishlist.toggle', $guestOnly));

        $this->asGuestSession()->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $ids = Wishlist::where('user_id', $user->id)->first()->items()
            ->pluck('product_id')->all();

        $this->assertEqualsCanonicalizing([$guestOnly->id, $shared->id], $ids);
        $this->assertDatabaseCount('wishlists', 1);
    }
}
