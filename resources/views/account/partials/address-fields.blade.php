@php($address = $address ?? null)

<div>
    <x-input-label value="Nom du destinataire" />
    <x-text-input name="recipient_name" type="text" class="mt-1 block w-full" :value="old('recipient_name', $address?->recipient_name)" required />
    <x-input-error class="mt-2" :messages="$errors->get('recipient_name')" />
</div>

<div>
    <x-input-label value="Téléphone" />
    <x-text-input name="phone" type="tel" class="mt-1 block w-full" :value="old('phone', $address?->phone)" required placeholder="+221 7X XXX XX XX" />
    <x-input-error class="mt-2" :messages="$errors->get('phone')" />
</div>

<div>
    <x-input-label value="Ville" />
    <x-text-input name="city" type="text" class="mt-1 block w-full" :value="old('city', $address?->city)" required />
    <x-input-error class="mt-2" :messages="$errors->get('city')" />
</div>

<div>
    <x-input-label value="Adresse (quartier, rue)" />
    <x-text-input name="address_line" type="text" class="mt-1 block w-full" :value="old('address_line', $address?->address_line)" required />
    <x-input-error class="mt-2" :messages="$errors->get('address_line')" />
</div>

<div>
    <x-input-label value="Repère (optionnel)" />
    <x-text-input name="landmark" type="text" class="mt-1 block w-full" :value="old('landmark', $address?->landmark)" placeholder="Ex. : en face de la pharmacie X" />
    <x-input-error class="mt-2" :messages="$errors->get('landmark')" />
</div>

<div>
    <x-input-label value="Position exacte (facultatif)" />
    <x-shop.location-picker :address="$address" class="mt-1" />
</div>

<label class="flex items-center gap-2 text-sm text-brand-ink/80">
    <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $address?->is_default))>
    Définir comme adresse par défaut
</label>
