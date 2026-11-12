<?php

namespace Modules\AbandonedCart\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbandonedCheckoutReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly string $recoveryUrl,
        public readonly string $storeName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your '.$this->storeName.' checkout is still waiting',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'abandoned-cart::email');
    }
}
