<?php

namespace App\Bundle\NotificationBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class NotificationBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return __DIR__;
    }
}