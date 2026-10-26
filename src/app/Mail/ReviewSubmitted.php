<?php

namespace App\Mail;

use App\Models\ProductReview;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the shop a new review is waiting for approval.
 */
class ReviewSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProductReview $review) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('New review: :product, :rating/5', [
            'product' => $this->review->product->name,
            'rating' => $this->review->rating,
        ]));
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.review-submitted',
            text: 'mail.review-submitted-text',
            with: ['review' => $this->review->loadMissing('product'), 'adminUrl' => route('admin.reviews.index', ['status' => 'pending'])],
        );
    }
}
