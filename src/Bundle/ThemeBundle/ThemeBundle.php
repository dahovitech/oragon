<?php

namespace App\Bundle\ThemeBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class ThemeBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}