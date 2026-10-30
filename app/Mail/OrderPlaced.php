<?php

namespace App\Mail;

use App\Models\Order\Order;
use App\Services\Payment\PaymentManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation de commande au client. Envoyée en file d'attente (phase 3 :
 * jamais de blocage du tunnel sur l'envoi d'email) — ne contient que le
 * récapitulatif nécessaire, pas de données sensibles superflues.
 */
class OrderPlaced extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre commande '.$this->order->number.($this->paysOnline() ? ' est enregistrée' : ' est confirmée'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-placed',
            with: ['order' => $this->order->load(['items']), 'paysOnline' => $this->paysOnline()],
        );
    }

    /** Paiement en ligne : l'email part avant le paiement, rien n'est encore réglé. */
    private function paysOnline(): bool
    {
        return (bool) app(PaymentManager::class)->find((string) $this->order->payment?->provider)?->isOnline();
    }
}
