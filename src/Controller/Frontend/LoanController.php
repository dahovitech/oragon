<?php

namespace App\Controller\Frontend;

use App\Entity\LoanType;
use App\Repository\LoanTypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{_locale}/prets', requirements: ['_locale' => 'fr|en'], defaults: ['_locale' => 'fr'])]
class LoanController extends AbstractController
{
    public function __construct(
        private LoanTypeRepository $loanTypeRepository
    ) {}

    #[Route('/', name: 'app_frontend_loans')]
    public function index(): Response
    {
        $loanTypes = $this->loanTypeRepository->findActiveOrdered();
        
        return $this->render('frontend/pages/loans/index.html.twig', [
            'loanTypes' => $loanTypes,
        ]);
    }

    #[Route('/{slug}', name: 'app_frontend_loan_detail')]
    public function detail(LoanType $loanType): Response
    {
        if (!$loanType->isActive()) {
            throw $this->createNotFoundException('Ce type de prêt n\'est pas disponible.');
        }
        
        return $this->render('frontend/pages/loans/detail.html.twig', [
            'loanType' => $loanType,
        ]);
    }

    #[Route('/simulateur', name: 'app_frontend_simulator')]
    public function simulator(): Response
    {
        $loanTypes = $this->loanTypeRepository->findActiveOrdered();
        
        return $this->render('frontend/pages/loans/simulator.html.twig', [
            'loanTypes' => $loanTypes,
        ]);
    }
}