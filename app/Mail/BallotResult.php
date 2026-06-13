<?php

namespace App\Mail;

use App\Models\ApiUser;
use App\Models\Personalization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class BallotResult extends Mailable
{
    use Queueable, SerializesModels;
    public ?Personalization $personalization;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(public string $template, string $subject, public string $csv, public string $resultLink, ?string $locale = null)
    {
        $this->subject  = $subject;
        /** @var ApiUser $user */
        $user = Auth::user();
        $this->personalization = $user->personalization;

        // Render any auto-appended labels in the same locale the body and
        // subject were composed in, instead of the sender service default.
        if (!empty($locale)) {
            $this->locale($locale);
        }
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->subject($this->subject)
            ->markdown('emails.ballot-result')
            ->attachData($this->csv, 'results.csv', [
                'mime' => 'text/csv',
            ]);
    }
}
