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

    public string $template;
    public string $csv;
    public ?Personalization $personalization;
    public string $resultLink;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(string $template, string $subject, string $csv, string $resultLink)
    {
        $this->template = $template;
        $this->subject  = $subject;
        $this->csv  = $csv;
        $this->resultLink = $resultLink;
        /** @var ApiUser $user */
        $user = Auth::user();
        $this->personalization = $user->personalization;
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
