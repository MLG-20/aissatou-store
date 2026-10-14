<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\CartService;
use App\Services\WishlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly WishlistService $wishlistService,
    ) {}

    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Capturé avant authenticate()/login() — les deux régénèrent l'id de
        // session en interne, donc il faut le lire avant pour savoir quel
        // panier invité fusionner (jamais faire confiance à un id de
        // session/panier fourni par la requête).
        $guestSessionId = $request->session()->getId();

        $request->authenticate();

        $request->session()->regenerate();

        $this->cartService->mergeGuestCartIntoUser($guestSessionId, $request->user());
        $this->wishlistService->mergeGuestWishlistIntoUser($guestSessionId, $request->user());

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
