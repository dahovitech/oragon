<?php

namespace App\Bundle\BlogBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class BlogBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}