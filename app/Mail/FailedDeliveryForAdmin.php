<?php

namespace App\Mail;

use App\Models\Order\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Alerte interne : le livreur n'a pas pu remettre le colis (cliente absente). */
class FailedDeliveryForAdmin extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Livraison manquée : '.$this->order->number.' (cliente absente)',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.failed-delivery-admin', with: ['order' => $this->order]);
    }
}
