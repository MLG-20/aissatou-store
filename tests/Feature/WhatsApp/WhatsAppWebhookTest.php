<?php

namespace Tests\Feature\WhatsApp;

use App\Enums\Order\WhatsAppMessageStatus;
use App\Enums\Order\WhatsAppTemplate;
use App\Models\Cart\Cart;
use App\Models\Cart\CartItem;
use App\Models\Catalogue\Product;
use App\Models\Customer\Address;
use App\Models\Order\WhatsAppMessage;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Webhook Meta (étape D, docs/files/08-whatsapp-cloud-api.md) : abonnement
 * vérifié par jeton, notifications signées, statuts qui ne reculent jamais.
 */
class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'secret-de-test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.app_secret' => self::SECRET,
            'whatsapp.verify_token' => 'jeton-verif',
        ]);
    }

    private function message(WhatsAppMessageStatus $status = WhatsAppMessageStatus::Sent): WhatsAppMessage
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $cart = Cart::create(['user_id' => $user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $address = Address::factory()->for($user)->create();
        $order = app(OrderService::class)->createFromCart($user, $cart, $address, 'cod');

        return $order->whatsappMessages()->create([
            'to' => '+221771234567',
            'template' => WhatsAppTemplate::OrderConfirmed,
            'wamid' => 'wamid.ABC',
            'status' => $status,
        ]);
    }

    /** @param  list<array<string, mixed>>  $statuses */
    private function notify(array $statuses = [], array $messages = [], ?string $signature = null): TestResponse
    {
        $body = json_encode(['object' => 'whatsapp_business_account', 'entry' => [[
            'id' => '1',
            'changes' => [['field' => 'messages', 'value' => array_filter([
                'messaging_product' => 'whatsapp',
                'statuses' => $statuses,
                'messages' => $messages,
            ])]],
        ]]]);

        return $this->call('POST', '/webhooks/whatsapp', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature ?? 'sha256='.hash_hmac('sha256', $body, self::SECRET),
        ], content: $body);
    }

    public function test_meta_subscription_is_confirmed_with_the_right_token(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=jeton-verif&hub.challenge=1158201444')
            ->assertOk()
            ->assertContent('1158201444');
    }

    public function test_a_wrong_verify_token_is_refused(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=autre&hub.challenge=1')->assertForbidden();

        config(['whatsapp.verify_token' => null]);
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=&hub.challenge=1')->assertForbidden();
    }

    public function test_statuses_update_the_message_log(): void
    {
        $message = $this->message();

        $this->notify([['id' => 'wamid.ABC', 'status' => 'delivered']])->assertOk();
        $this->assertSame(WhatsAppMessageStatus::Delivered, $message->fresh()->status);

        $this->notify([['id' => 'wamid.ABC', 'status' => 'read']])->assertOk();
        $this->assertSame(WhatsAppMessageStatus::Read, $message->fresh()->status);
    }

    public function test_a_late_status_never_moves_back(): void
    {
        $message = $this->message(WhatsAppMessageStatus::Read);

        $this->notify([['id' => 'wamid.ABC', 'status' => 'delivered']])->assertOk();

        $this->assertSame(WhatsAppMessageStatus::Read, $message->fresh()->status);
    }

    public function test_a_failure_keeps_meta_reason(): void
    {
        $message = $this->message();

        $this->notify([['id' => 'wamid.ABC', 'status' => 'failed', 'errors' => [
            ['code' => 131047, 'title' => 'Re-engagement message'],
        ]]])->assertOk();

        $message->refresh();
        $this->assertSame(WhatsAppMessageStatus::Failed, $message->status);
        $this->assertSame('Refus de Meta 131047 : Re-engagement message', $message->error);
    }

    public function test_a_bad_signature_changes_nothing(): void
    {
        $message = $this->message();

        $this->notify([['id' => 'wamid.ABC', 'status' => 'read']], signature: 'sha256=faux')->assertForbidden();

        $this->assertSame(WhatsAppMessageStatus::Sent, $message->fresh()->status);
    }

    public function test_nothing_is_accepted_without_an_app_secret(): void
    {
        $message = $this->message();
        config(['whatsapp.app_secret' => null]);

        $this->notify([['id' => 'wamid.ABC', 'status' => 'read']], signature: 'sha256='.hash_hmac('sha256', '', ''))->assertForbidden();

        $this->assertSame(WhatsAppMessageStatus::Sent, $message->fresh()->status);
    }

    public function test_unknown_messages_and_incoming_replies_are_acknowledged(): void
    {
        $this->notify(
            [['id' => 'wamid.INCONNU', 'status' => 'read']],
            [['from' => '221771234567', 'id' => 'wamid.IN', 'type' => 'text', 'text' => ['body' => 'Merci !']]],
        )->assertOk();

        $this->assertDatabaseCount('whatsapp_messages', 0);
    }
}
