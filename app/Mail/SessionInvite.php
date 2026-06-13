<?php

namespace App\Mail;

use App\Models\ApiUser;
use App\Models\Personalization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class SessionInvite extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array<string, string> */
    public array $code;
    public string $template;
    public ?Personalization $personalization;

    /**
     * Create a new message instance.
     *
     * @param array<string, string> $code
     * @return void
     */
    public function __construct(array $code, string $template, string $subject, ?string $locale = null)
    {
        $template = str_replace('%%CODE%%', $code['code'], $template);

        $this->code     = $code;
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
            ->subject($this->subject ?? __('emails.invite.subject'))
            ->markdown('emails.session-invite');
    }
}
