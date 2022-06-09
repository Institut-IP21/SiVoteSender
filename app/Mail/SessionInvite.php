<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class SessionInvite extends Mailable
{
    use Queueable, SerializesModels;

    public $code;
    public $template;
    public $subject;
    public $personalization;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(array $code, string $template, string $subject)
    {
        $template = str_replace('%%CODE%%', $code['code'], $template);

        $this->code     = $code;
        $this->template = $template;
        $this->subject  = $subject;
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
            ->subject($this->subject ?? __('emails.invite.subject'))
            ->markdown('emails.session-invite');
    }
}
