<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\EmailTemplates;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The confirmation a customer gets right after placing an order. The texts
 * around the order lines are edited in the admin (Emails).
 */
class OrderPlaced extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->texts()['subject']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.order-placed',
            text: 'mail.order-placed-text',
            with: [
                'texts' => $this->texts(),
                'order' => $this->order->loadMissing('items'),
                'url' => route('checkout.complete', $this->order->token),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function texts(): array
    {
        return EmailTemplates::render('order_placed', [
            'name' => $this->order->first_name,
            'number' => (string) $this->order->number,
            'total' => Money::rub($this->order->total),
        ]);
    }
}
