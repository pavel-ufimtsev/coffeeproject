<?php

namespace App\Mail;

use App\Http\Requests\Shop\ContactRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A message from the contact form, sent to the shop. Replying answers the
 * customer directly.
 */
class ContactMessage extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{name: string, email: string, phone: string|null, topic: string, message: string}  $data
     */
    public function __construct(public array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->data['email'], $this->data['name'])],
            subject: __('Contact form: :topic, :name', ['topic' => $this->topic(), 'name' => $this->data['name']]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.contact-message',
            text: 'mail.contact-message-text',
            with: ['data' => $this->data, 'topic' => $this->topic()],
        );
    }

    private function topic(): string
    {
        return __(ContactRequest::TOPICS[$this->data['topic']] ?? $this->data['topic']);
    }
}
