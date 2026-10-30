<?php

namespace App\Http\Requests\Shop;

use App\Services\Payment\PaymentManager;
use App\Support\DeliveryLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // Scopée au compte connecté : l'adresse d'un autre client est
            // rejetée en 422 ici, et le service refait le contrôle (403).
            'address_id' => [
                'required', 'integer',
                Rule::exists('customer_addresses', 'id')->where('user_id', $this->user()->id),
            ],
            'provider' => ['required', 'string', 'in:'.implode(',', $this->providerKeys())],
            // Case décochée par défaut : absente = pas d'accord.
            'whatsapp_opt_in' => ['nullable', 'boolean'],
            // Position partagée au dernier moment pour une adresse qui n'en
            // avait pas : le lien Maps reste réservé au carnet d'adresses.
            ...collect(DeliveryLocation::rules())->except('maps_link')->all(),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return DeliveryLocation::messages();
    }

    /** @return list<string> */
    private function providerKeys(): array
    {
        return collect(app(PaymentManager::class)->available())
            ->map(fn ($provider) => $provider->key())
            ->all();
    }
}
