<?php

namespace App\Mail;

use App\Models\ApiUser;
use App\Models\Personalization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class BallotInvite extends Mailable
{
    use Queueable, SerializesModels;

    public string $code;
    public string $url;
    public string $template;
    public ?Personalization $personalization;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(string $code, string $url, string $template, string $subject, ?string $locale = null)
    {
        $template = str_replace('%%CODE%%', $code, $template);
        $template = str_replace('%%LINK%%', $url, $template);

        $url = str_replace('%%CODE%%', $code, $url);

        $this->code     = $code;
        $this->url      = $url;
        $this->template = $template;
        $this->subject  = $subject;
        /** @var ApiUser $user */
        $user = Auth::user();
        $this->personalization = $user->personalization;

        // Render the auto-appended button label in the same locale the body and
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
            ->markdown('emails.ballot-invite');
    }
}
