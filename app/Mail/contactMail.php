<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class contactMail extends Mailable
{
    use Queueable, SerializesModels;
    /**
     * Create a new message instance.
     */
    public $email, $name, $sub, $mes;
    public function __construct($name, $email, $sub, $mes)
    {
        $this->email = $email;
        $this->name = $name;
        $this->sub = $sub;
        $this->mes = $mes;
    }

    /**
     * Get the mes envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->sub,
        );
    }

    /**
     * Get the mes content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-mail',
            with: [
                'name' => $this->name,
                'email' => $this->email,
                'sub' => $this->sub,
                'mes' => $this->mes
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            //
        ];
    }
}
