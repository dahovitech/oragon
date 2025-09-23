<?php

namespace App\Bundle\MediaBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class MediaBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}