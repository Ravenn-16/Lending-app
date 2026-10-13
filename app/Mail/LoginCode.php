<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class LoginCode extends Mailable
{
    public function __construct(public string $code) {}

    public function build()
    {
        return $this->subject('Your Lending System login code')->text('emails.login-code');
    }
}
