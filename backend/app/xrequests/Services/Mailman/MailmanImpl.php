<?php

namespace Xrequests\Services\Mailman;

use Illuminate\Support\Facades\Mail;

class MailmanImpl implements Mailman {

    function send(string $email, string $file, string $subject, array $values = []) {
        Mail::send($file, $values, function ($message) use ($email, $subject) {
            $message->from('xyz@gmail.com')
                ->to($email)
                ->subject($subject);
        });
    }
}
