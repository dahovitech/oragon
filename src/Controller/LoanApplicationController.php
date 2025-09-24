<?php

namespace App\Controller;

use App\Entity\LoanApplication;
use App\Entity\LoanDocument;
use App\Entity\LoanType;
use App\Form\LoanApplicationType;
use App\Repository\LoanApplicationRepository;
use App\Repository\LoanTypeRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/loan-application')]
#[IsGranted('ROLE_USER')]
class LoanApplicationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoanApplicationRepository $loanApplicationRepository,
        private LoanTypeRepository $loanTypeRepository,
        private SluggerInterface $slugger,
        private NotificationService $notificationService
    ) {
    }

    #[Route('/', name: 'loan_application_index')]
    public function index(): Response
    {
        $user = $this->getUser();
        
        // Check if user account is verified
        if (!$user->isVerified()) {
            $this->addFlash('warning', 'Votre compte doit être vérifié avant de pouvoir faire une demande de prêt.');
            return $this->redirectToRoute('user_verification_index');
        }

        $applications = $this->loanApplicationRepository->findBy(
            ['user' => $user],
            ['submittedAt' => 'DESC']
        );

        return $this->render('loan_application/index.html.twig', [
            'applications' => $applications,
        ]);
    }

    #[Route('/new/{loanTypeSlug?}', name: 'loan_application_new')]
    public function new(Request $request, ?string $loanTypeSlug = null): Response
    {
        $user = $this->getUser();
        
        // Check if user account is verified
        if (!$user->isVerified()) {
            $this->addFlash('warning', 'Votre compte doit être vérifié avant de pouvoir faire une demande de prêt.');
            return $this->redirectToRoute('user_verification_index');
        }

        $loanApplication = new LoanApplication();
        $loanApplication->setUser($user);
        $loanApplication->setStatus('DRAFT');

        // Pre-select loan type if provided
        if ($loanTypeSlug) {
            $loanType = $this->loanTypeRepository->findOneBy(['slug' => $loanTypeSlug]);
            if ($loanType) {
                $loanApplication->setLoanType($loanType);
            }
        }

        $form = $this->createForm(LoanApplicationType::class, $loanApplication, [
            'account_type' => $user->getAccountType()
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Calculate loan details
            $this->calculateLoanDetails($loanApplication);
            
            // Handle file uploads
            $uploadedFiles = $form->get('documents')->getData();
            if ($uploadedFiles) {
                $this->handleDocumentUploads($loanApplication, $uploadedFiles);
            }

            // Set submission date and status
            $loanApplication->setSubmittedAt(new \DateTime());
            $loanApplication->setStatus('SUBMITTED');

            // Set financial info from form
            $personalInfo = [
                'monthly_income' => $loanApplication->getMonthlyIncome(),
                'monthly_expenses' => $form->get('monthlyExpenses')->getData(),
                'employment_status' => $loanApplication->getEmploymentStatus(),
                'company_name' => $form->get('companyName')->getData(),
            ];
            $loanApplication->setPersonalInfo($personalInfo);

            $this->entityManager->persist($loanApplication);
            $this->entityManager->flush();

            // Créer une notification pour la demande soumise
            $this->notificationService->createLoanApplicationNotification(
                $loanApplication->getUser(),
                'pending',
                $loanApplication->getId()
            );

            $this->addFlash('success', 'Votre demande de prêt a été soumise avec succès. Vous recevrez une réponse sous 48-72 heures.');
            
            return $this->redirectToRoute('loan_application_detail', ['id' => $loanApplication->getId()]);
        }

        return $this->render('loan_application/new.html.twig', [
            'form' => $form,
            'loanApplication' => $loanApplication,
        ]);
    }

    #[Route('/{id}', name: 'loan_application_detail', requirements: ['id' => '\d+'])]
    public function detail(LoanApplication $loanApplication): Response
    {
        // Check if user owns this application
        if ($loanApplication->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('loan_application/detail.html.twig', [
            'application' => $loanApplication,
        ]);
    }

    #[Route('/{id}/edit', name: 'loan_application_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, LoanApplication $loanApplication): Response
    {
        // Check if user owns this application and it's still editable
        if ($loanApplication->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!in_array($loanApplication->getStatus(), ['DRAFT', 'REJECTED'])) {
            $this->addFlash('error', 'Cette demande ne peut plus être modifiée.');
            return $this->redirectToRoute('loan_application_detail', ['id' => $loanApplication->getId()]);
        }

        $form = $this->createForm(LoanApplicationType::class, $loanApplication, [
            'account_type' => $this->getUser()->getAccountType()
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->calculateLoanDetails($loanApplication);
            
            // Handle new file uploads
            $uploadedFiles = $form->get('documents')->getData();
            if ($uploadedFiles) {
                $this->handleDocumentUploads($loanApplication, $uploadedFiles);
            }

            $loanApplication->setStatus('SUBMITTED');
            $loanApplication->setSubmittedAt(new \DateTime());

            $this->entityManager->flush();

            $this->addFlash('success', 'Votre demande a été mise à jour et resoumise.');
            
            return $this->redirectToRoute('loan_application_detail', ['id' => $loanApplication->getId()]);
        }

        return $this->render('loan_application/edit.html.twig', [
            'form' => $form,
            'application' => $loanApplication,
        ]);
    }

    private function calculateLoanDetails(LoanApplication $loanApplication): void
    {
        $amount = $loanApplication->getRequestedAmount();
        $duration = $loanApplication->getDuration();
        $loanType = $loanApplication->getLoanType();
        
        // Validate amount and duration against loan type limits
        if ($amount < $loanType->getMinAmount() || $amount > $loanType->getMaxAmount()) {
            throw new \InvalidArgumentException('Le montant demandé n\'est pas dans les limites autorisées.');
        }
        
        if ($duration < $loanType->getMinDuration() || $duration > $loanType->getMaxDuration()) {
            throw new \InvalidArgumentException('La durée demandée n\'est pas dans les limites autorisées.');
        }

        // Calculate interest rate (could be more sophisticated)
        $baseRate = $loanType->getBaseInterestRate();
        $interestRate = $baseRate; // For now, use base rate
        
        // Calculate monthly payment using standard loan formula
        $monthlyRate = $interestRate / 100 / 12;
        $monthlyPayment = $amount * ($monthlyRate * pow(1 + $monthlyRate, $duration)) / (pow(1 + $monthlyRate, $duration) - 1);
        $totalAmount = $monthlyPayment * $duration;

        $loanApplication->setInterestRate($interestRate);
        $loanApplication->setMonthlyPayment($monthlyPayment);
        $loanApplication->setTotalAmount($totalAmount);
    }

    private function handleDocumentUploads(LoanApplication $loanApplication, array $uploadedFiles): void
    {
        $uploadsDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/documents';
        
        if (!is_dir($uploadsDirectory)) {
            mkdir($uploadsDirectory, 0755, true);
        }

        foreach ($uploadedFiles as $uploadedFile) {
            if ($uploadedFile) {
                $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $this->slugger->slug($originalFilename);
                $fileName = $safeFilename . '-' . uniqid() . '.' . $uploadedFile->guessExtension();

                try {
                    $uploadedFile->move($uploadsDirectory, $fileName);

                    $document = new LoanDocument();
                    $document->setLoanApplication($loanApplication);
                    $document->setDocumentType('GENERAL'); // Could be more specific based on file analysis
                    $document->setFileName($fileName);
                    $document->setOriginalName($uploadedFile->getClientOriginalName());
                    $document->setFilePath('/uploads/documents/' . $fileName);
                    $document->setUploadedAt(new \DateTime());
                    $document->setIsVerified(false);

                    $this->entityManager->persist($document);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Erreur lors du téléchargement du fichier: ' . $originalFilename);
                }
            }
        }
    }
}