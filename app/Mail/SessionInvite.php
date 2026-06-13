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
    public function __construct(array $code, string $template, string $subject)
    {
        $template = str_replace('%%CODE%%', $code['code'], $template);

        $this->code     = $code;
        $this->template = $template;
        $this->subject  = $subject;
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
            ->markdown('emails.session-invite');
    }
}
