<?php

namespace App\Bundle\AnalyticsBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class AnalyticsBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return __DIR__;
    }
}