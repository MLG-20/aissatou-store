<?php

namespace App\View\Components;

use App\Enums\Order\OrderStatus;
use App\Models\User;
use App\Services\WishlistService;
use Illuminate\Support\Str;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Cadre commun de l'espace cliente : en-tête personnel (salutation selon
 * l'heure, monogramme, ancienneté, badge « Cliente fidèle », trois chiffres
 * à elle) et menu de l'espace. Toutes les pages du compte l'utilisent, pour
 * qu'on ne « sorte » jamais de chez soi en passant d'une page à l'autre.
 */
class AccountLayout extends Component
{
    public User $user;

    public string $firstName;

    public string $greeting;

    public bool $loyal;

    /** @var array{orders: int, active: int, wishlist: int, addresses: int} */
    public array $counts;

    public function __construct(public ?string $title = null)
    {
        $this->user = request()->user();
        $this->firstName = (string) Str::of($this->user->name)->trim()->explode(' ')->first();

        $hour = (int) now()->format('G');
        $this->greeting = $hour >= 18 || $hour < 5 ? 'Bonsoir' : 'Bonjour';

        $this->loyal = User::query()->whereKey($this->user->id)->regularCustomers()->exists();

        $this->counts = [
            'orders' => $this->user->orders()->count(),
            'active' => $this->user->orders()
                ->whereIn('status', [OrderStatus::New, OrderStatus::Preparing, OrderStatus::Shipped])
                ->count(),
            'wishlist' => count(app(WishlistService::class)->currentProductIds(request())),
            'addresses' => $this->user->addresses()->count(),
        ];
    }

    public function render(): View
    {
        return view('layouts.account');
    }
}
