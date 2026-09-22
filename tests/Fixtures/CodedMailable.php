<?php

namespace Tests\Fixtures;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CodedMailable extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(public string $code)
    {
    }

    public function build(): self
    {
        return $this->subject('Coded test')->html('<p>Your code: ' . $this->code . '</p>');
    }
}
