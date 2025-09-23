<?php

namespace App\Controller\Frontend;

use App\Entity\LoanApplication;
use App\Entity\LoanType;
use App\Entity\LoanDocument;
use App\Form\LoanApplicationFormType;
use App\Enum\LoanApplicationStatus;
use App\Service\FileUploadService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/demande', name: 'app_loan_application_')]
#[IsGranted('ROLE_USER')]
class LoanApplicationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileUploadService $fileUploadService,
        private NotificationService $notificationService
    ) {}

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        // Récupérer les demandes de l'utilisateur
        $applications = $this->entityManager
            ->getRepository(LoanApplication::class)
            ->findBy(['user' => $user], ['submittedAt' => 'DESC']);

        return $this->render('frontend/loan_application/index.html.twig', [
            'applications' => $applications,
            'user' => $user
        ]);
    }

    #[Route('/nouvelle', name: 'new')]
    public function new(Request $request): Response
    {
        $user = $this->getUser();
        
        // Vérifier que l'utilisateur est vérifié
        if (!$user->isVerified()) {
            $this->addFlash('warning', 'Votre compte doit être vérifié pour faire une demande de prêt.');
            return $this->redirectToRoute('app_verification_index');
        }

        // Récupérer le type de prêt depuis les paramètres
        $loanTypeId = $request->query->get('loanType');
        $loanType = null;
        
        if ($loanTypeId) {
            $loanType = $this->entityManager->getRepository(LoanType::class)->find($loanTypeId);
            
            if (!$loanType || !$loanType->isActive()) {
                throw $this->createNotFoundException('Type de prêt non trouvé.');
            }
            
            // Vérifier que l'utilisateur peut accéder à ce type de prêt
            $userAccountType = $user->getAccountType()->value;
            if (!in_array($userAccountType, $loanType->getAllowedAccountTypes())) {
                $this->addFlash('error', 'Ce type de prêt n\'est pas accessible avec votre type de compte.');
                return $this->redirectToRoute('app_loan_catalog');
            }
        }

        $application = new LoanApplication();
        $application->setUser($user);
        $application->setStatus(LoanApplicationStatus::DRAFT);
        
        if ($loanType) {
            $application->setLoanType($loanType);
            
            // Pré-remplir avec les paramètres de la simulation
            $amount = $request->query->get('amount');
            $duration = $request->query->get('duration');
            
            if ($amount && $duration) {
                $application->setRequestedAmount((float)$amount);
                $application->setDuration((int)$duration);
                
                // Calculer les autres valeurs
                $this->calculateLoanDetails($application);
            }
        }

        $form = $this->createForm(LoanApplicationFormType::class, $application);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $application->setSubmittedAt(new \DateTime());
            $application->setStatus(LoanApplicationStatus::SUBMITTED);
            
            // Calculer les détails du prêt
            $this->calculateLoanDetails($application);
            
            // Traitement des fichiers uploadés
            $uploadedFiles = $form->get('documents')->getData();
            
            if ($uploadedFiles) {
                foreach ($uploadedFiles as $uploadedFile) {
                    $fileName = $this->fileUploadService->upload($uploadedFile, 'loan_applications');
                    
                    $document = new LoanDocument();
                    $document->setFileName($fileName);
                    $document->setOriginalName($uploadedFile->getClientOriginalName());
                    $document->setFilePath('uploads/loan_applications/' . $fileName);
                    $document->setFileSize($uploadedFile->getSize());
                    $document->setMimeType($uploadedFile->getMimeType());
                    $document->setUploadedAt(new \DateTime());
                    $document->setDocumentType(\App\Enum\DocumentType::OTHER); // Défaut
                    
                    $application->addDocument($document);
                    $this->entityManager->persist($document);
                }
            }

            $this->entityManager->persist($application);
            $this->entityManager->flush();

            // Notification
            $this->notificationService->sendLoanApplicationSubmitted($application);

            $this->addFlash('success', 'Votre demande de prêt a été soumise avec succès. Vous recevrez une réponse sous 48-72 heures.');

            return $this->redirectToRoute('app_loan_application_detail', ['id' => $application->getId()]);
        }

        return $this->render('frontend/loan_application/new.html.twig', [
            'form' => $form,
            'application' => $application,
            'loanType' => $loanType,
            'user' => $user
        ]);
    }

    #[Route('/{id}', name: 'detail')]
    public function detail(LoanApplication $application): Response
    {
        // Vérifier que l'utilisateur peut voir cette demande
        if ($application->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('frontend/loan_application/detail.html.twig', [
            'application' => $application
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit')]
    public function edit(LoanApplication $application, Request $request): Response
    {
        // Vérifier que l'utilisateur peut modifier cette demande
        if ($application->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Seules les demandes en brouillon peuvent être modifiées
        if ($application->getStatus() !== LoanApplicationStatus::DRAFT) {
            $this->addFlash('error', 'Cette demande ne peut plus être modifiée.');
            return $this->redirectToRoute('app_loan_application_detail', ['id' => $application->getId()]);
        }

        $form = $this->createForm(LoanApplicationFormType::class, $application);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->calculateLoanDetails($application);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre demande a été mise à jour.');

            return $this->redirectToRoute('app_loan_application_detail', ['id' => $application->getId()]);
        }

        return $this->render('frontend/loan_application/edit.html.twig', [
            'form' => $form,
            'application' => $application
        ]);
    }

    #[Route('/{id}/soumettre', name: 'submit', methods: ['POST'])]
    public function submit(LoanApplication $application): Response
    {
        // Vérifier que l'utilisateur peut soumettre cette demande
        if ($application->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Seules les demandes en brouillon peuvent être soumises
        if ($application->getStatus() !== LoanApplicationStatus::DRAFT) {
            $this->addFlash('error', 'Cette demande a déjà été soumise.');
            return $this->redirectToRoute('app_loan_application_detail', ['id' => $application->getId()]);
        }

        // Vérifier que tous les champs requis sont remplis
        if (!$application->getLoanType() || !$application->getRequestedAmount() || !$application->getDuration()) {
            $this->addFlash('error', 'Veuillez compléter tous les champs requis avant de soumettre.');
            return $this->redirectToRoute('app_loan_application_edit', ['id' => $application->getId()]);
        }

        $application->setStatus(LoanApplicationStatus::SUBMITTED);
        $application->setSubmittedAt(new \DateTime());
        
        $this->entityManager->flush();

        // Notification
        $this->notificationService->sendLoanApplicationSubmitted($application);

        $this->addFlash('success', 'Votre demande de prêt a été soumise avec succès.');

        return $this->redirectToRoute('app_loan_application_detail', ['id' => $application->getId()]);
    }

    #[Route('/calcul-eligibilite', name: 'eligibility_check', methods: ['POST'])]
    public function eligibilityCheck(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $user = $this->getUser();
        
        $monthlyIncome = (float)$data['monthlyIncome'];
        $monthlyExpenses = (float)$data['monthlyExpenses'];
        $requestedAmount = (float)$data['requestedAmount'];
        $duration = (int)$data['duration'];
        $loanTypeId = (int)$data['loanTypeId'];
        
        $loanType = $this->entityManager->getRepository(LoanType::class)->find($loanTypeId);
        
        if (!$loanType) {
            return new JsonResponse(['error' => 'Type de prêt non trouvé'], 400);
        }

        // Calcul de la capacité d'endettement (33% des revenus)
        $maxDebtCapacity = $monthlyIncome * 0.33;
        $availableCapacity = $maxDebtCapacity - $monthlyExpenses;
        
        // Calcul de la mensualité
        $monthlyRate = $loanType->getBaseInterestRate() / 100 / 12;
        
        if ($monthlyRate > 0) {
            $monthlyPayment = $requestedAmount * ($monthlyRate * pow(1 + $monthlyRate, $duration)) / 
                             (pow(1 + $monthlyRate, $duration) - 1);
        } else {
            $monthlyPayment = $requestedAmount / $duration;
        }
        
        $isEligible = $monthlyPayment <= $availableCapacity;
        $debtRatio = ($monthlyExpenses + $monthlyPayment) / $monthlyIncome * 100;
        
        return new JsonResponse([
            'eligible' => $isEligible,
            'monthlyPayment' => round($monthlyPayment, 2),
            'availableCapacity' => round($availableCapacity, 2),
            'debtRatio' => round($debtRatio, 2),
            'maxDebtRatio' => 33,
            'recommendations' => $this->getRecommendations($isEligible, $debtRatio, $availableCapacity, $monthlyPayment)
        ]);
    }

    private function calculateLoanDetails(LoanApplication $application): void
    {
        $loanType = $application->getLoanType();
        $amount = $application->getRequestedAmount();
        $duration = $application->getDuration();
        
        if (!$loanType || !$amount || !$duration) {
            return;
        }

        // Calcul des mensualités
        $monthlyRate = $loanType->getBaseInterestRate() / 100 / 12;
        $application->setInterestRate($loanType->getBaseInterestRate());
        
        if ($monthlyRate > 0) {
            $monthlyPayment = $amount * ($monthlyRate * pow(1 + $monthlyRate, $duration)) / 
                             (pow(1 + $monthlyRate, $duration) - 1);
        } else {
            $monthlyPayment = $amount / $duration;
        }

        $application->setMonthlyPayment($monthlyPayment);
        $application->setTotalAmount($monthlyPayment * $duration);
    }

    private function getRecommendations(bool $isEligible, float $debtRatio, float $availableCapacity, float $monthlyPayment): array
    {
        $recommendations = [];
        
        if (!$isEligible) {
            $recommendations[] = "Votre capacité d'endettement est insuffisante pour ce montant.";
            
            if ($debtRatio > 33) {
                $recommendations[] = "Votre taux d'endettement dépasse les 33% recommandés.";
            }
            
            if ($availableCapacity > 0) {
                $maxLoanAmount = $availableCapacity * 60; // Estimation sur 60 mois
                $recommendations[] = "Montant maximum recommandé : " . number_format($maxLoanAmount, 0, ',', ' ') . "€";
            }
            
            $recommendations[] = "Considérez augmenter la durée du prêt ou réduire le montant.";
        } else {
            $recommendations[] = "Félicitations ! Votre dossier semble éligible.";
            
            if ($debtRatio < 25) {
                $recommendations[] = "Votre taux d'endettement est excellent.";
            } elseif ($debtRatio < 30) {
                $recommendations[] = "Votre taux d'endettement est très bon.";
            } else {
                $recommendations[] = "Votre taux d'endettement est à la limite, soyez prudent.";
            }
        }
        
        return $recommendations;
    }
}