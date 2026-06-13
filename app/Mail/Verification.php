<?php

namespace App\Mail;

use App\Models\ApiUser;
use App\Models\Personalization;
use App\Models\Verification as ModelsVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Verification extends Mailable
{
    use Queueable, SerializesModels;

    public ?string $template;
    public string $url;
    public ?Personalization $personalization;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(?ModelsVerification $verification, string $url, ?string $subject = null, ?string $template = null)
    {
        if ($verification) {
            $this->subject  = $verification->subject ?? (string) __('emails.verification.subject');
            $this->template = str_replace('%%LINK%%', $url, $verification->template);
        } else {
            $this->subject  = $subject ?? (string) __('emails.verification.subject');
            $this->template = $template !== null ? str_replace('%%LINK%%', $url, $template) : null;
        }
        $this->url      = $url;
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
        return $this
            ->subject($this->subject)
            ->markdown('emails.verification');
    }
}
