<?php

namespace App\Controller\Frontend;

use App\Entity\LoanType;
use App\Repository\LoanTypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/prets', name: 'app_loan_')]
class LoanController extends AbstractController
{
    public function __construct(
        private LoanTypeRepository $loanTypeRepository
    ) {}

    #[Route('/', name: 'catalog')]
    public function catalog(Request $request): Response
    {
        // Filtres
        $accountType = $request->query->get('account_type', 'all');
        $minAmount = $request->query->get('min_amount');
        $maxAmount = $request->query->get('max_amount');
        $category = $request->query->get('category', 'all');

        // Récupérer tous les types de prêts actifs
        $queryBuilder = $this->loanTypeRepository->createQueryBuilder('lt')
            ->where('lt.isActive = true')
            ->orderBy('lt.sortOrder', 'ASC');

        // Appliquer les filtres
        if ($accountType !== 'all') {
            $queryBuilder->andWhere('JSON_CONTAINS(lt.allowedAccountTypes, :accountType) = 1')
                ->setParameter('accountType', json_encode($accountType));
        }

        if ($minAmount) {
            $queryBuilder->andWhere('lt.maxAmount >= :minAmount')
                ->setParameter('minAmount', (float)$minAmount);
        }

        if ($maxAmount) {
            $queryBuilder->andWhere('lt.minAmount <= :maxAmount')
                ->setParameter('maxAmount', (float)$maxAmount);
        }

        $loanTypes = $queryBuilder->getQuery()->getResult();

        // Statistiques pour les filtres
        $allLoanTypes = $this->loanTypeRepository->findBy(['isActive' => true]);
        $stats = [
            'total' => count($allLoanTypes),
            'individual' => count(array_filter($allLoanTypes, fn($lt) => in_array('INDIVIDUAL', $lt->getAllowedAccountTypes()))),
            'business' => count(array_filter($allLoanTypes, fn($lt) => in_array('BUSINESS', $lt->getAllowedAccountTypes()))),
            'minAmountOverall' => count($allLoanTypes) > 0 ? min(array_map(fn($lt) => $lt->getMinAmount(), $allLoanTypes)) : 0,
            'maxAmountOverall' => count($allLoanTypes) > 0 ? max(array_map(fn($lt) => $lt->getMaxAmount(), $allLoanTypes)) : 0,
        ];

        return $this->render('frontend/loan/catalog.html.twig', [
            'loanTypes' => $loanTypes,
            'stats' => $stats,
            'filters' => [
                'accountType' => $accountType,
                'minAmount' => $minAmount,
                'maxAmount' => $maxAmount,
                'category' => $category,
            ]
        ]);
    }

    #[Route('/type/{slug}', name: 'detail')]
    public function detail(LoanType $loanType): Response
    {
        if (!$loanType->isActive()) {
            throw $this->createNotFoundException('Ce type de prêt n\'est plus disponible.');
        }

        // Vérifier si l'utilisateur connecté peut accéder à ce type de prêt
        $user = $this->getUser();
        $canApply = false;
        
        if ($user && $user->isVerified()) {
            $userAccountType = $user->getAccountType()->value;
            $canApply = in_array($userAccountType, $loanType->getAllowedAccountTypes());
        }

        return $this->render('frontend/loan/detail.html.twig', [
            'loanType' => $loanType,
            'canApply' => $canApply,
            'user' => $user
        ]);
    }

    #[Route('/calculateur', name: 'calculator')]
    public function calculator(): Response
    {
        $loanTypes = $this->loanTypeRepository->findBy(['isActive' => true], ['name' => 'ASC']);

        return $this->render('frontend/loan/calculator.html.twig', [
            'loanTypes' => $loanTypes
        ]);
    }

    #[Route('/calculer', name: 'calculate', methods: ['POST'])]
    public function calculate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        $amount = (float)$data['amount'];
        $duration = (int)$data['duration']; // en mois
        $loanTypeId = (int)$data['loanTypeId'];
        
        $loanType = $this->loanTypeRepository->find($loanTypeId);
        
        if (!$loanType) {
            return new JsonResponse(['error' => 'Type de prêt non trouvé'], 400);
        }

        // Vérifier les limites
        if ($amount < $loanType->getMinAmount() || $amount > $loanType->getMaxAmount()) {
            return new JsonResponse([
                'error' => 'Montant hors limites',
                'minAmount' => $loanType->getMinAmount(),
                'maxAmount' => $loanType->getMaxAmount()
            ], 400);
        }

        if ($duration < $loanType->getMinDuration() || $duration > $loanType->getMaxDuration()) {
            return new JsonResponse([
                'error' => 'Durée hors limites',
                'minDuration' => $loanType->getMinDuration(),
                'maxDuration' => $loanType->getMaxDuration()
            ], 400);
        }

        // Calcul des mensualités
        $monthlyRate = $loanType->getBaseInterestRate() / 100 / 12;
        
        if ($monthlyRate > 0) {
            $monthlyPayment = $amount * ($monthlyRate * pow(1 + $monthlyRate, $duration)) / 
                             (pow(1 + $monthlyRate, $duration) - 1);
        } else {
            $monthlyPayment = $amount / $duration; // Prêt à 0%
        }

        $totalAmount = $monthlyPayment * $duration;
        $totalInterest = $totalAmount - $amount;

        return new JsonResponse([
            'monthlyPayment' => round($monthlyPayment, 2),
            'totalAmount' => round($totalAmount, 2),
            'totalInterest' => round($totalInterest, 2),
            'interestRate' => $loanType->getBaseInterestRate(),
            'duration' => $duration,
            'amount' => $amount,
            'loanType' => [
                'id' => $loanType->getId(),
                'name' => $loanType->getName(),
                'slug' => $loanType->getSlug()
            ]
        ]);
    }

    #[Route('/comparer', name: 'compare')]
    public function compare(Request $request): Response
    {
        $compareIds = $request->query->get('compare', []);
        $loanTypes = [];

        if (!empty($compareIds) && is_array($compareIds)) {
            $loanTypes = $this->loanTypeRepository->findBy([
                'id' => array_slice($compareIds, 0, 3), // Max 3 comparaisons
                'isActive' => true
            ]);
        }

        return $this->render('frontend/loan/compare.html.twig', [
            'loanTypes' => $loanTypes
        ]);
    }
}