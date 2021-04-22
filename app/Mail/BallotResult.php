<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BallotResult extends Mailable
{
    use Queueable, SerializesModels;

    public $template;
    public $subject;
    public $csv;
    public $personalization;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(string $template, string $subject, string $csv)
    {
        $this->template = $template;
        $this->subject  = $subject;
        $this->csv  = $csv;
        $this->personalization = \Auth::user()->personalization;
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
