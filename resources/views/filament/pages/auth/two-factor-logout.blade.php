<form method="POST" action="{{ route('filament.admin.auth.logout') }}" class="text-center">
    @csrf
    <button type="submit" class="text-sm text-gray-500 underline">Se déconnecter</button>
</form>
