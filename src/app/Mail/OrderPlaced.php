<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The confirmation a customer gets right after placing an order.
 */
class OrderPlaced extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Order :number received', ['number' => $this->order->number]),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-placed', with: [
            'order' => $this->order->loadMissing('items'),
            'url' => route('checkout.complete', $this->order->token),
        ]);
    }
}
