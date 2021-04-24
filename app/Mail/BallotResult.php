<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class BallotResult extends Mailable
{
    use Queueable, SerializesModels;

    public $template;
    public $subject;
    public $csv;
    public $personalization;
    public $resultLink;

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
        $this->personalization = Auth::user()->personalization;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this
            ->subject($this->subject ?? __('emails.result.subject'))
            ->markdown('emails.ballot-result')
            ->attachData($this->csv, 'results.csv', [
                'mime' => 'text/csv',
            ]);
    }
}
