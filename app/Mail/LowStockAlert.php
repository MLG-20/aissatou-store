<?php

namespace App\Mail;

use App\Models\Catalogue\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Alerte stock faible (Phase 6B), envoyée une fois au franchissement du seuil. */
class LowStockAlert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ProductVariant $variant) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->variant->stock === 0 ? 'Rupture de stock : ' : 'Stock faible : ').$this->variant->label(),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.low-stock-alert', with: ['variant' => $this->variant]);
    }
}
