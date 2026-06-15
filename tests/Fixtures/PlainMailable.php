<?php

namespace Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * A dependency-free Mailable for exercising the send/failure plumbing without
 * the Auth/personalization requirements of the real app mailables.
 */
class PlainMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function build(): self
    {
        return $this->subject('Plain test')->html('<p>hello</p>');
    }
}
