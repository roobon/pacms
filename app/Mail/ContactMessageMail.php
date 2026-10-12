<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A contact form message for the organisation; "Reply" answers the sender.
 */
class ContactMessageMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly ContactMessage $contact, public readonly string $siteName) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->contact->email, $this->contact->name)],
            subject: '['.$this->siteName.'] '.($this->contact->subject ?: 'Message from '.$this->contact->name),
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.contact-message');
    }
}
