<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\StoreContactMessageRequest;
use App\Models\Contact\ContactMessage;
use App\Models\Content\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('shop.contact', ['settings' => SiteSetting::current()]);
    }

    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        // Piège à robots rempli : même réponse, rien d'enregistré.
        if (! $request->filled('website')) {
            ContactMessage::create($request->validated());
        }

        return back()->with('status', 'message-sent');
    }
}
