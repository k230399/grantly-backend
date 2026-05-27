<?php

namespace App\Mail;

use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

// ShouldQueue makes Mail::send() push this to the queue instead of running inline,
// so the submit endpoint isn't held up if Resend is slow. Requires `php artisan queue:work`.
class ApplicationSubmitted extends Mailable implements ShouldQueue
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
