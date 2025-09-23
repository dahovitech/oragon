<?php

namespace App\Controller\Frontend;

use App\Repository\LoanTypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}', requirements: ['_locale' => 'fr|en'], defaults: ['_locale' => 'fr'])]
class HomeController extends AbstractController
{
    public function __construct(
        private LoanTypeRepository $loanTypeRepository
    ) {}

    #[Route('/', name: 'app_frontend_home')]
    public function index(): Response
    {
        $loanTypes = $this->loanTypeRepository->findActiveOrdered();
        
        return $this->render('frontend/pages/home.html.twig', [
            'loanTypes' => $loanTypes,
        ]);
    }

    #[Route('/a-propos', name: 'app_frontend_about')]
    public function about(): Response
    {
        return $this->render('frontend/pages/about.html.twig');
    }

    #[Route('/contact', name: 'app_frontend_contact')]
    public function contact(): Response
    {
        return $this->render('frontend/pages/contact.html.twig');
    }

    #[Route('/faq', name: 'app_frontend_faq')]
    public function faq(): Response
    {
        return $this->render('frontend/pages/faq.html.twig');
    }
}