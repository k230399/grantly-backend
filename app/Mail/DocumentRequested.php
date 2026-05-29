<?php

namespace App\Mail;

use App\Models\Application;
use App\Models\DocumentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Sent inline (no ShouldQueue): Mail::send() delivers during the request rather than via the
// queue, so it works without a running queue worker. The document-request endpoint wraps send()
// in a try/catch, so a slow or failing Resend can't block creating the request itself.
class DocumentRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Application $application,
        public DocumentRequest $documentRequest,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Document requested: {$this->application->reference_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.document-requested',
            with: [
                'application'     => $this->application,
                'round'           => $this->application->grantRound,
                'documentRequest' => $this->documentRequest,
                'viewUrl'         => rtrim((string) config('services.frontend.url'), '/') . "/apply/{$this->application->id}",
            ],
        );
    }
}
