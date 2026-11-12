<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminNewOrder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New order '.$this->order->number.' · '.nexus_money($this->order->grand_total));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-new-order', with: ['order' => $this->order]);
    }
}
