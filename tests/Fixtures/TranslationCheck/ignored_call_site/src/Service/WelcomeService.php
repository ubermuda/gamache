<?php

namespace App\Service;

use App\Exception\MyException;

class WelcomeService
{
    public function fail(): void
    {
        throw new MyException('Sign in to your account');
    }
}
