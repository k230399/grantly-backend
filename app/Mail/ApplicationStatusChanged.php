<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationStatusChanged extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Application $application,
        public string $previousStatus,
        public string $newStatus,
        public ?string $notes = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $readable = str_replace('_', ' ', $this->newStatus);

        return new Envelope(
            subject: "Application update: {$this->application->reference_number} is now " . ucwords($readable),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.application-status-changed',
            with: [
                'application'    => $this->application,
                'round'          => $this->application->grantRound,
                'previousStatus' => $this->previousStatus,
                'newStatus'      => $this->newStatus,
                'notes'          => $this->notes,
                'viewUrl'        => rtrim((string) config('services.frontend.url'), '/') . "/apply/{$this->application->id}",
            ],
        );
    }
}
