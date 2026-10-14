<?php

namespace Database\Seeders;

use App\Enums\Order\OrderStatus;
use App\Models\Admin;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Jeu de démonstration de la Phase 3 : deux clientes connues (identifiants
 * ci-dessous) et cinq commandes couvrant tous les statuts, pour constater le
 * tunnel, l'historique client, le back-office et la facture sans passer de
 * fausses commandes à la main.
 *
 * Les commandes passent par le vrai OrderService (numéros, prix figés,
 * stock décrémenté, historique) : la démo montre le système réel, pas des
 * lignes insérées à la main. Les emails sont neutralisés (Mail::fake) pour
 * ne pas remplir la file d'attente de démo.
 *
 * Comptes : aicha.demo@example.com / fatou.demo@example.com, mot de passe
 * `password` pour les deux.
 */
class OrderDemoSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        Mail::fake();

        $authorId = Admin::first()?->id;

        $aicha = $this->client('Aïcha Ndiaye', 'aicha.demo@example.com', 'Dakar', 'Rue 10, Plateau');
        $fatou = $this->client('Fatou Sow', 'fatou.demo@example.com', 'Pikine', 'Rue 5, Darou Salam');

        $scenarios = [
            [$aicha, [], 'nouvelle'],
            [$aicha, [OrderStatus::Preparing], 'preparation'],
            [$fatou, [OrderStatus::Preparing, OrderStatus::Shipped], 'expediee'],
            [$fatou, [OrderStatus::Preparing, OrderStatus::Shipped, OrderStatus::Delivered], 'livree'],
            [$aicha, [OrderStatus::Cancelled], 'annulee'],
        ];

        foreach ($scenarios as [$client, $transitions, $label]) {
            $order = $this->orderFor($client);

            foreach ($transitions as $status) {
                app(OrderService::class)->changeStatus($order, $status, $authorId);
                $order = $order->fresh();
            }

            $this->command->info("Commande {$order->number} ({$label}) pour {$client->email}");
        }

        $this->command->info('Clientes démo : aicha.demo@example.com / fatou.demo@example.com (mot de passe : '.self::PASSWORD.')');
    }

    private function client(string $name, string $email, string $city, string $street): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make(self::PASSWORD), 'email_verified_at' => now()],
        );

        Address::firstOrCreate(
            ['user_id' => $user->id, 'address_line' => $street],
            [
                'recipient_name' => $name,
                'phone' => '77'.fake()->numerify('######'),
                'city' => $city,
                'is_default' => true,
            ],
        );

        return $user;
    }

    private function orderFor(User $client): Order
    {
        $product = Product::active()->whereHas('variants')->inRandomOrder()->firstOrFail();
        $variant = $product->variants()->active()->inRandomOrder()->firstOrFail();
        $variant->update(['stock' => max($variant->stock, 20)]);

        $cart = Cart::firstOrCreate(['user_id' => $client->id]);

        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        return app(OrderService::class)->createFromCart(
            $client,
            $cart,
            $client->addresses()->where('is_default', true)->firstOrFail(),
            'cod',
        );
    }
}
