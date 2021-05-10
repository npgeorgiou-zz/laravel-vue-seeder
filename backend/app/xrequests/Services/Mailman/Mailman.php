<?php

namespace Xrequests\Services\Mailman;

interface Mailman {
    function send(string $email, string $file, string $subject, array $values = []);
}
