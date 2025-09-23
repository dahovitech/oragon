<?php

namespace App\Bundle\ApiBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class ApiBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}