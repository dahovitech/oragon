<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DefaultController
 * @package App\Controller
 *
 */
class FrontController extends AbstractController
{
    #[Route('/', name: 'homepage')]
    public function homepage(Request $request): Response
    {
        return $this->render("@theme/homepage.html.twig");

    }

}
