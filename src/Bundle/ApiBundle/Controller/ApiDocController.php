<?php

namespace App\Bundle\ApiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiDocController extends AbstractController
{
    #[Route('/api/doc', name: 'api_doc')]
    public function apiDoc(): Response
    {
        return $this->render('@api/doc.html.twig');
    }
}