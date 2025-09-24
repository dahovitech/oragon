<?php

namespace App\Controller;

use App\Entity\LoanApplication;
use App\Entity\LoanType;
use App\Enum\LoanApplicationStatus;
use App\Form\LoanApplicationFormType;
use App\Repository\LoanApplicationRepository;
use App\Repository\LoanTypeRepository;
use App\Service\LoanCalculatorService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/loan-application')]
#[IsGranted('ROLE_USER')]
class LoanApplicationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoanApplicationRepository $loanApplicationRepository,
        private LoanTypeRepository $loanTypeRepository,
        private LoanCalculatorService $loanCalculatorService,
        private NotificationService $notificationService
    ) {}

    #[Route('/', name: 'loan_application_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        $applications = $this->loanApplicationRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('loan_application/index.html.twig', [
            'applications' => $applications,
        ]);
    }

    #[Route('/new', name: 'loan_application_new')]
    public function new(Request $request): Response
    {
        $loanTypes = $this->loanTypeRepository->findActiveOrdered();
        
        if (empty($loanTypes)) {
            $this->addFlash('error', 'Aucun type de prêt n\'est actuellement disponible.');
            return $this->redirectToRoute('user_dashboard');
        }

        $loanApplication = new LoanApplication();
        $loanApplication->setUser($this->getUser());
        
        $form = $this->createForm(LoanApplicationFormType::class, $loanApplication, [
            'loan_types' => $loanTypes,
            'personal_info_data' => [],
            'financial_info_data' => []
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Traiter les données des sous-formulaires
            $this->processFormData($loanApplication, $form);
            
            // Calculer les détails financiers
            $this->calculateLoanDetails($loanApplication);
            
            // Sauvegarder en brouillon d'abord
            $loanApplication->setStatus(LoanApplicationStatus::DRAFT);
            
            $this->entityManager->persist($loanApplication);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre demande de prêt a été créée avec succès. Vous pouvez la compléter et la soumettre quand vous le souhaitez.');

            return $this->redirectToRoute('loan_application_edit', [
                'id' => $loanApplication->getId()
            ]);
        }

        return $this->render('loan_application/new.html.twig', [
            'form' => $form->createView(),
            'loan_types' => $loanTypes,
        ]);
    }

    #[Route('/{id}/edit', name: 'loan_application_edit')]
    public function edit(LoanApplication $loanApplication, Request $request): Response
    {
        // Vérifier que l'utilisateur est propriétaire de la demande
        if ($loanApplication->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier si la demande est encore modifiable
        if (!$loanApplication->getStatus()->isEditable()) {
            $this->addFlash('warning', 'Cette demande ne peut plus être modifiée car elle a déjà été soumise.');
            return $this->redirectToRoute('loan_application_show', ['id' => $loanApplication->getId()]);
        }

        $loanTypes = $this->loanTypeRepository->findActiveOrdered();
        
        $form = $this->createForm(LoanApplicationFormType::class, $loanApplication, [
            'loan_types' => $loanTypes,
            'personal_info_data' => $loanApplication->getPersonalInfo() ?? [],
            'financial_info_data' => $loanApplication->getFinancialInfo() ?? []
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Traiter les données des sous-formulaires
            $this->processFormData($loanApplication, $form);
            
            // Recalculer les détails financiers
            $this->calculateLoanDetails($loanApplication);
            
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre demande de prêt a été mise à jour avec succès.');

            return $this->redirectToRoute('loan_application_show', [
                'id' => $loanApplication->getId()
            ]);
        }

        return $this->render('loan_application/edit.html.twig', [
            'loan_application' => $loanApplication,
            'form' => $form->createView(),
            'loan_types' => $loanTypes,
        ]);
    }

    #[Route('/{id}', name: 'loan_application_show')]
    public function show(LoanApplication $loanApplication): Response
    {
        // Vérifier que l'utilisateur est propriétaire de la demande
        if ($loanApplication->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('loan_application/show.html.twig', [
            'loan_application' => $loanApplication,
        ]);
    }

    #[Route('/{id}/submit', name: 'loan_application_submit', methods: ['POST'])]
    public function submit(LoanApplication $loanApplication): Response
    {
        // Vérifier que l'utilisateur est propriétaire de la demande
        if ($loanApplication->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier si la demande peut être soumise
        if (!$loanApplication->getStatus()->isEditable()) {
            $this->addFlash('error', 'Cette demande a déjà été soumise.');
            return $this->redirectToRoute('loan_application_show', ['id' => $loanApplication->getId()]);
        }

        // Valider que tous les champs requis sont remplis
        if (!$this->validateApplicationForSubmission($loanApplication)) {
            $this->addFlash('error', 'Veuillez compléter tous les champs obligatoires avant de soumettre votre demande.');
            return $this->redirectToRoute('loan_application_edit', ['id' => $loanApplication->getId()]);
        }

        // Changer le statut en SUBMITTED
        $loanApplication->setStatus(LoanApplicationStatus::SUBMITTED);
        $this->entityManager->flush();

        // Envoyer notification par email au client et à l'équipe admin
        $this->notificationService->sendLoanApplicationSubmitted($loanApplication);

        $this->addFlash('success', 'Votre demande de prêt a été soumise avec succès. Notre équipe l\'examinera dans les plus brefs délais.');

        return $this->redirectToRoute('loan_application_show', ['id' => $loanApplication->getId()]);
    }

    #[Route('/{id}/delete', name: 'loan_application_delete', methods: ['POST'])]
    public function delete(LoanApplication $loanApplication): Response
    {
        // Vérifier que l'utilisateur est propriétaire de la demande
        if ($loanApplication->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Vérifier si la demande peut être supprimée (uniquement en brouillon)
        if (!$loanApplication->getStatus()->isEditable()) {
            $this->addFlash('error', 'Cette demande ne peut pas être supprimée car elle a été soumise.');
            return $this->redirectToRoute('loan_application_show', ['id' => $loanApplication->getId()]);
        }

        $this->entityManager->remove($loanApplication);
        $this->entityManager->flush();

        $this->addFlash('success', 'Votre demande de prêt a été supprimée.');

        return $this->redirectToRoute('loan_application_index');
    }

    #[Route('/calculate-payment', name: 'loan_application_calculate_payment', methods: ['POST'])]
    public function calculatePayment(Request $request): Response
    {
        $amount = (float) $request->request->get('amount');
        $duration = (int) $request->request->get('duration');
        $loanTypeId = (int) $request->request->get('loan_type_id');

        $loanType = $this->loanTypeRepository->find($loanTypeId);
        
        if (!$loanType) {
            return $this->json(['error' => 'Type de prêt non trouvé'], 400);
        }

        $interestRate = (float) $loanType->getBaseInterestRate();
        
        $calculation = $this->loanCalculatorService->calculateLoan($amount, $duration, $interestRate);

        return $this->json($calculation);
    }

    private function calculateLoanDetails(LoanApplication $loanApplication): void
    {
        $amount = (float) $loanApplication->getRequestedAmount();
        $duration = $loanApplication->getDuration();
        $interestRate = (float) $loanApplication->getLoanType()->getBaseInterestRate();

        $calculation = $this->loanCalculatorService->calculateLoan($amount, $duration, $interestRate);

        $loanApplication->setMonthlyPayment($calculation['monthly_payment']);
        $loanApplication->setInterestRate($calculation['interest_rate']);
        $loanApplication->setTotalAmount($calculation['total_amount']);
    }

    private function validateApplicationForSubmission(LoanApplication $loanApplication): bool
    {
        // Vérifier les champs obligatoires
        $requiredFields = [
            $loanApplication->getRequestedAmount(),
            $loanApplication->getDuration(),
            $loanApplication->getPurpose(),
            $loanApplication->getPersonalInfo(),
            $loanApplication->getFinancialInfo(),
        ];

        foreach ($requiredFields as $field) {
            if (empty($field)) {
                return false;
            }
        }

        // Vérifier les informations personnelles
        $personalInfo = $loanApplication->getPersonalInfo();
        $requiredPersonalFields = ['full_name', 'email', 'phone', 'birth_date', 'marital_status'];
        
        foreach ($requiredPersonalFields as $field) {
            if (empty($personalInfo[$field])) {
                return false;
            }
        }

        // Vérifier les informations financières
        $financialInfo = $loanApplication->getFinancialInfo();
        $requiredFinancialFields = ['monthly_income', 'employment_industry', 'employer_name'];
        
        foreach ($requiredFinancialFields as $field) {
            if (empty($financialInfo[$field])) {
                return false;
            }
        }

        return true;
    }

    private function processFormData(LoanApplication $loanApplication, $form): void
    {
        // Traiter les informations personnelles
        $personalInfoData = $form->get('personalInfo')->getData();
        if ($personalInfoData) {
            // Convertir la date de naissance en string si c'est un objet DateTime
            if (isset($personalInfoData['birth_date']) && $personalInfoData['birth_date'] instanceof \DateTime) {
                $personalInfoData['birth_date'] = $personalInfoData['birth_date']->format('Y-m-d');
            }
            $loanApplication->setPersonalInfo($personalInfoData);
        }

        // Traiter les informations financières
        $financialInfoData = $form->get('financialInfo')->getData();
        if ($financialInfoData) {
            // S'assurer que les montants sont en string pour Doctrine
            if (isset($financialInfoData['monthly_income'])) {
                $financialInfoData['monthly_income'] = (string) $financialInfoData['monthly_income'];
            }
            if (isset($financialInfoData['monthly_expenses'])) {
                $financialInfoData['monthly_expenses'] = (string) $financialInfoData['monthly_expenses'];
            }
            if (isset($financialInfoData['existing_loans'])) {
                $financialInfoData['existing_loans'] = (string) $financialInfoData['existing_loans'];
            }
            $loanApplication->setFinancialInfo($financialInfoData);
        }
    }
}