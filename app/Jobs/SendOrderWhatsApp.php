<?php

namespace App\Jobs;

use App\Enums\Order\WhatsAppMessageStatus;
use App\Enums\Order\WhatsAppTemplate;
use App\Models\Order\Order;
use App\Models\Order\WhatsAppMessage;
use App\Services\WhatsApp\WhatsAppClient;
use App\Services\WhatsApp\WhatsAppException;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envoie un message WhatsApp de suivi de commande, en file d'attente comme
 * les e-mails : la commande ne dépend jamais de Meta. Aucune exception ne
 * remonte : une panne passagère est retentée, un refus est journalisé.
 */
class SendOrderWhatsApp implements ShouldQueue
{
    use Queueable;

    /** Délais (secondes) avant chaque nouvelle tentative sur panne passagère. */
    private const RETRY_DELAYS = [30, 120];

    /** Premier essai + une tentative par délai ; sans cela, le worker n'en accepte qu'un. */
    public int $tries = 3;

    public function __construct(public WhatsAppMessage $message) {}

    /**
     * Point d'entrée, appelé après commit. Rien n'est fait si WhatsApp n'est
     * pas configuré ou si le client n'a pas donné son accord. Un numéro
     * inutilisable est journalisé en échec, pour que la boutique le voie.
     */
    public static function dispatchFor(Order $order, WhatsAppTemplate $template): void
    {
        if (! app(WhatsAppClient::class)->isConfigured() || ! $order->whatsapp_opt_in) {
            return;
        }

        $mobile = PhoneNumber::isMobile($order->phone);

        $message = $order->whatsappMessages()->create([
            'to' => PhoneNumber::toE164($order->phone) ?? mb_substr((string) $order->phone, 0, 20),
            'template' => $template,
            'status' => $mobile ? WhatsAppMessageStatus::Queued : WhatsAppMessageStatus::Failed,
            'error' => $mobile ? null : 'Numéro sans WhatsApp ou invalide (mobile sénégalais attendu)',
        ]);

        if ($mobile) {
            dispatch(new self($message));
        }
    }

    public function handle(WhatsAppClient $client): void
    {
        // Déjà traité (job rejoué) : ne jamais envoyer deux fois.
        if ($this->message->status !== WhatsAppMessageStatus::Queued) {
            return;
        }

        try {
            $wamid = $client->sendTemplate($this->message->to, $this->message->template, $this->message->order);
        } catch (WhatsAppException $e) {
            $this->message->update(['error' => $e->getMessage()]);

            if ($e->retryable && $this->attempts() <= count(self::RETRY_DELAYS)) {
                $this->release(self::RETRY_DELAYS[$this->attempts() - 1]);

                return;
            }

            $this->message->update(['status' => WhatsAppMessageStatus::Failed]);

            return;
        }

        $this->message->update(['wamid' => $wamid, 'status' => WhatsAppMessageStatus::Sent, 'error' => null]);
    }
}
