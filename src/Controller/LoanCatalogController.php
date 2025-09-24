<?php

namespace App\Controller;

use App\Entity\LoanType;
use App\Repository\LoanTypeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/services')]
class LoanCatalogController extends AbstractController
{
    public function __construct(
        private LoanTypeRepository $loanTypeRepository
    ) {
    }

    #[Route('/', name: 'loan_catalog_index')]
    public function index(Request $request): Response
    {
        $accountType = $request->query->get('type', 'all');
        $minAmount = $request->query->get('min_amount');
        $maxAmount = $request->query->get('max_amount');

        $queryBuilder = $this->loanTypeRepository->createQueryBuilder('lt')
            ->where('lt.isActive = :active')
            ->setParameter('active', true);

        // Filter by account type if specified
        if ($accountType !== 'all') {
            $queryBuilder->andWhere('JSON_CONTAINS(lt.allowedAccountTypes, :accountType) = 1')
                        ->setParameter('accountType', json_encode($accountType));
        }

        // Filter by amount range if specified
        if ($minAmount) {
            $queryBuilder->andWhere('lt.maxAmount >= :minAmount')
                        ->setParameter('minAmount', $minAmount);
        }

        if ($maxAmount) {
            $queryBuilder->andWhere('lt.minAmount <= :maxAmount')
                        ->setParameter('maxAmount', $maxAmount);
        }

        $loanTypes = $queryBuilder->orderBy('lt.name', 'ASC')
                                 ->getQuery()
                                 ->getResult();

        return $this->render('loan_catalog/index.html.twig', [
            'loanTypes' => $loanTypes,
            'currentAccountType' => $accountType,
            'currentMinAmount' => $minAmount,
            'currentMaxAmount' => $maxAmount,
        ]);
    }

    #[Route('/{slug}', name: 'loan_catalog_detail')]
    public function detail(LoanType $loanType): Response
    {
        return $this->render('loan_catalog/detail.html.twig', [
            'loanType' => $loanType,
        ]);
    }

    #[Route('/calculator/ajax', name: 'loan_calculator_ajax', methods: ['POST'])]
    public function calculateLoan(Request $request): Response
    {
        $amount = (float) $request->request->get('amount', 0);
        $duration = (int) $request->request->get('duration', 12);
        $loanTypeId = $request->request->get('loan_type_id');

        // Get loan type if specified, otherwise use default rate
        $interestRate = 5.0; // Default rate
        if ($loanTypeId) {
            $loanType = $this->loanTypeRepository->find($loanTypeId);
            if ($loanType) {
                $interestRate = (float) $loanType->getBaseInterestRate();
            }
        }

        // Calculate monthly payment using standard loan formula
        $monthlyRate = $interestRate / 100 / 12;
        $monthlyPayment = $amount * ($monthlyRate * pow(1 + $monthlyRate, $duration)) / (pow(1 + $monthlyRate, $duration) - 1);
        $totalAmount = $monthlyPayment * $duration;
        $totalInterest = $totalAmount - $amount;

        return $this->json([
            'monthly_payment' => round($monthlyPayment, 2),
            'total_amount' => round($totalAmount, 2),
            'total_interest' => round($totalInterest, 2),
            'interest_rate' => $interestRate
        ]);
    }

    #[Route('/simulate', name: 'loan_simulator')]
    public function simulator(): Response
    {
        $loanTypes = $this->loanTypeRepository->findActiveTypes();

        return $this->render('loan_catalog/simulator.html.twig', [
            'loanTypes' => $loanTypes,
        ]);
    }
}