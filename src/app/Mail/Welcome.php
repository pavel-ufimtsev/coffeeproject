<?php

namespace App\Mail;

use App\Models\User;
use App\Support\EmailTemplates;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent once a customer creates an account. The texts are edited in the
 * admin (Emails).
 */
class Welcome extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->texts()['subject']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.welcome',
            text: 'mail.welcome-text',
            with: [
                'texts' => $this->texts(),
                'shopUrl' => route('catalog.root', config('catalog.roots')[0]),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function texts(): array
    {
        return EmailTemplates::render('welcome', ['name' => $this->user->name]);
    }
}
