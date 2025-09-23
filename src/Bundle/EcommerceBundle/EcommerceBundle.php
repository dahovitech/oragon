<?php

namespace App\Bundle\EcommerceBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class EcommerceBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}