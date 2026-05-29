<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// Sent inline (no ShouldQueue): Mail::send() delivers during the request rather than via the
// queue, so it works without a running queue worker. The submit endpoint wraps send() in a
// try/catch, so a slow or failing Resend can't block a legitimate submission.
class ApplicationSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    // The application + grant round are eager-loaded by the controller before send().
    public function __construct(public Application $application)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Application submitted: {$this->application->reference_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.application-submitted',
            with: [
                'application' => $this->application,
                'round'       => $this->application->grantRound,
                'viewUrl'     => rtrim((string) config('services.frontend.url'), '/') . "/apply/{$this->application->id}",
            ],
        );
    }
}
