<?php

namespace App\Mail;

use App\Models\Verification as ModelsVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Verification extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $template;
    public $url;
    public $personalization;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(?ModelsVerification $verification = null, $url, ?string $subject = null, ?string $template = null)
    {
        if ($verification) {
            $this->subject  = $verification->subject;
            $this->template = str_replace('%%LINK%%', $url, $verification->template);
        } else {
            $this->subject  = $subject;
            $this->template = str_replace('%%LINK%%', $url, $template);
        }
        $this->url      = $url;
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
            ->subject($this->subject ?? __('emails.verification.subject'))
            ->markdown('emails.verification');
    }
}
