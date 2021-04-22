<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $personalization = null;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->personalization = \Auth::user()->personalization;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Test email')->markdown('emails.test');
    }
}
