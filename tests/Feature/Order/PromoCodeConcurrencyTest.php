<?php

namespace Tests\Feature\Order;

use App\Enums\Order\PromoCodeType;
use App\Exceptions\PromoCodeException;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Customer\Address;
use App\Models\Order\Order;
use App\Models\Order\PromoCode;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\WithCashOnDelivery;
use Tests\TestCase;
use Throwable;

/**
 * Checklist phase 6 : « une tentative d'utilisation simultanée d'un code
 * promo à usage unique par deux commandes ne permet pas un double usage ».
 *
 * VRAIE concurrence : deux processus forkés, chacun avec sa propre
 * connexion MySQL, valident leur commande au même instant. Les données
 * doivent donc être réellement commitées (pas de transaction de test) :
 * DatabaseTruncation vide la base avant, et tearDown la revide après pour
 * ne pas polluer les tests suivants.
 */
class PromoCodeConcurrencyTest extends TestCase
{
    use DatabaseTruncation, WithCashOnDelivery;

    /**
     * Données de référence insérées par les migrations (pages légales,
     * registre des cookies) : les vider priverait les tests suivants, qui
     * ne remigrent pas.
     *
     * @var list<string>
     */
    protected $exceptTables = ['legal_pages', 'cookie_declarations'];

    protected function tearDown(): void
    {
        DB::reconnect();
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    public function test_two_simultaneous_orders_cannot_both_use_a_single_use_code(): void
    {
        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('Extensions pcntl/posix requises pour le test de concurrence.');
        }

        Mail::fake();
        $promo = PromoCode::create([
            'code' => 'UNIQUE',
            'type' => PromoCodeType::Percent,
            'value' => 10,
            'max_uses' => 1,
            'is_active' => true,
        ]);
        $product = Product::factory()->create(['price' => 10000]);

        $buyers = collect(range(1, 2))->map(function () use ($product): array {
            $user = User::factory()->create();
            $cart = Cart::create(['user_id' => $user->id]);
            CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

            return [$user->id, $cart->id, Address::factory()->for($user)->create()->id];
        })->all();

        $results = [];
        $start = microtime(true) + 0.5; // départ synchronisé des deux processus

        // Chaque enfant doit ouvrir SA connexion : on ferme celle du parent
        // avant de forker (une connexion PDO partagée serait corrompue).
        DB::disconnect();
        $pids = [];

        foreach ($buyers as $index => [$userId, $cartId, $addressId]) {
            $file = tempnam(sys_get_temp_dir(), 'promo-race-');
            $pid = pcntl_fork();

            if ($pid === 0) {
                time_sleep_until($start);

                try {
                    app(OrderService::class)->createFromCart(
                        User::findOrFail($userId), Cart::findOrFail($cartId), Address::findOrFail($addressId), 'cod', 'UNIQUE'
                    );
                    $outcome = 'commande';
                } catch (PromoCodeException) {
                    $outcome = 'refus';
                } catch (Throwable $e) {
                    $outcome = 'erreur: '.$e->getMessage();
                }

                file_put_contents($file, $outcome);
                // Sortie brutale : ne pas exécuter les handlers de fin de PHPUnit
                // hérités du parent.
                posix_kill(getmypid(), SIGKILL);
            }

            $pids[$pid] = $file;
        }

        foreach ($pids as $pid => $file) {
            pcntl_waitpid($pid, $status);
            $results[] = file_get_contents($file);
            unlink($file);
        }

        DB::reconnect();
        sort($results);

        $this->assertSame(['commande', 'refus'], $results);
        $this->assertSame(1, Order::whereNotNull('promo_code')->count());
        $this->assertSame(1, $promo->fresh()->uses_count);
        $this->assertSame(1, DB::table('promo_code_redemptions')->count());
    }
}
