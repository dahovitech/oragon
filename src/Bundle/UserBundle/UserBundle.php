<?php

namespace App\Bundle\UserBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class UserBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}