<?php

namespace App\Mail;

use App\Models\ApiUser;
use App\Models\Personalization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public ?Personalization $personalization = null;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {
        /** @var ApiUser $user */
        $user = \Auth::user();
        $this->personalization = $user->personalization;
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
