<?php

namespace App\Controller;

use App\Entity\LoanApplication;
use App\Entity\LoanContract;
use App\Service\ContractGeneratorService;
use App\Repository\LoanContractRepository;
use App\Repository\LoanApplicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/contracts')]
class ContractController extends AbstractController
{
    public function __construct(
        private ContractGeneratorService $contractService,
        private LoanContractRepository $contractRepository,
        private LoanApplicationRepository $loanApplicationRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * List user contracts
     */
    #[Route('/', name: 'app_contracts_index', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        $user = $this->getUser();
        $contracts = $this->contractService->getUserContracts($user->getId());

        return $this->render('contracts/index.html.twig', [
            'contracts' => $contracts,
        ]);
    }

    /**
     * Show contract details
     */
    #[Route('/{id}', name: 'app_contracts_show', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function show(LoanContract $contract): Response
    {
        // Check if user can access this contract
        if ($contract->getLoanApplication()->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('contracts/show.html.twig', [
            'contract' => $contract,
            'loan_application' => $contract->getLoanApplication(),
        ]);
    }

    /**
     * Download contract PDF
     */
    #[Route('/{id}/download', name: 'app_contracts_download', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function download(LoanContract $contract): Response
    {
        // Check if user can access this contract
        if ($contract->getLoanApplication()->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $filePath = $this->contractService->getContractFilePath($contract);
        
        if (!$filePath || !file_exists($filePath)) {
            $this->addFlash('error', 'Le fichier du contrat est introuvable.');
            return $this->redirectToRoute('app_contracts_show', ['id' => $contract->getId()]);
        }

        return new BinaryFileResponse(
            $filePath,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $contract->getFileName() . '"'
            ]
        );
    }

    /**
     * Sign contract (digital signature)
     */
    #[Route('/{id}/sign', name: 'app_contracts_sign', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function sign(LoanContract $contract, Request $request): Response
    {
        // Check if user can access this contract
        if ($contract->getLoanApplication()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($contract->isSigned()) {
            $this->addFlash('warning', 'Ce contrat est déjà signé.');
            return $this->redirectToRoute('app_contracts_show', ['id' => $contract->getId()]);
        }

        $signature = $request->request->get('signature');
        
        if (empty($signature)) {
            $this->addFlash('error', 'La signature est requise.');
            return $this->redirectToRoute('app_contracts_show', ['id' => $contract->getId()]);
        }

        $this->contractService->signContract($contract, $signature);
        
        $this->addFlash('success', 'Contrat signé avec succès ! Il sera activé sous 24-48h.');
        
        return $this->redirectToRoute('app_contracts_show', ['id' => $contract->getId()]);
    }
}

/**
 * Admin contract management controller
 */
#[Route('/admin/contracts')]
#[IsGranted('ROLE_ADMIN')]
class AdminContractController extends AbstractController
{
    public function __construct(
        private ContractGeneratorService $contractService,
        private LoanContractRepository $contractRepository,
        private LoanApplicationRepository $loanApplicationRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * List all contracts (admin)
     */
    #[Route('/', name: 'app_admin_contracts_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status');
        $search = $request->query->get('search');

        $queryBuilder = $this->contractRepository->createQueryBuilder('c')
            ->leftJoin('c.loanApplication', 'la')
            ->leftJoin('la.user', 'u')
            ->addSelect('la', 'u');

        if ($status) {
            $queryBuilder->andWhere('c.status = :status')
                ->setParameter('status', $status);
        }

        if ($search) {
            $queryBuilder->andWhere(
                'c.contractNumber LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search OR u.email LIKE :search'
            )->setParameter('search', '%' . $search . '%');
        }

        $contracts = $queryBuilder
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult();

        $statistics = $this->contractService->getContractStatistics();

        return $this->render('admin/contracts/index.html.twig', [
            'contracts' => $contracts,
            'statistics' => $statistics,
            'current_status' => $status,
            'current_search' => $search,
        ]);
    }

    /**
     * Show contract details (admin)
     */
    #[Route('/{id}', name: 'app_admin_contracts_show', methods: ['GET'])]
    public function show(LoanContract $contract): Response
    {
        return $this->render('admin/contracts/show.html.twig', [
            'contract' => $contract,
            'loan_application' => $contract->getLoanApplication(),
        ]);
    }

    /**
     * Generate contract for approved loan application
     */
    #[Route('/generate/{id}', name: 'app_admin_contracts_generate', methods: ['POST'])]
    public function generate(LoanApplication $loanApplication): Response
    {
        // Check if loan application is approved
        if ($loanApplication->getStatus() !== \App\Enum\LoanApplicationStatus::APPROVED) {
            $this->addFlash('error', 'Seules les demandes approuvées peuvent générer un contrat.');
            return $this->redirectToRoute('app_admin_loan_applications');
        }

        try {
            $contract = $this->contractService->generateContract($loanApplication);
            $this->addFlash('success', 'Contrat généré avec succès : ' . $contract->getContractNumber());
            
            return $this->redirectToRoute('app_admin_contracts_show', ['id' => $contract->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du contrat : ' . $e->getMessage());
            return $this->redirectToRoute('app_admin_loan_applications');
        }
    }

    /**
     * Send contract to user
     */
    #[Route('/{id}/send', name: 'app_admin_contracts_send', methods: ['POST'])]
    public function send(LoanContract $contract): Response
    {
        if ($contract->getStatus() === 'sent') {
            $this->addFlash('warning', 'Ce contrat a déjà été envoyé.');
            return $this->redirectToRoute('app_admin_contracts_show', ['id' => $contract->getId()]);
        }

        try {
            $this->contractService->sendContract($contract);
            $this->addFlash('success', 'Contrat envoyé avec succès à l\'utilisateur.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'envoi : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_contracts_show', ['id' => $contract->getId()]);
    }

    /**
     * Activate signed contract
     */
    #[Route('/{id}/activate', name: 'app_admin_contracts_activate', methods: ['POST'])]
    public function activate(LoanContract $contract): Response
    {
        if (!$contract->isSigned()) {
            $this->addFlash('error', 'Le contrat doit être signé avant d\'être activé.');
            return $this->redirectToRoute('app_admin_contracts_show', ['id' => $contract->getId()]);
        }

        if ($contract->isActive()) {
            $this->addFlash('warning', 'Ce contrat est déjà actif.');
            return $this->redirectToRoute('app_admin_contracts_show', ['id' => $contract->getId()]);
        }

        try {
            $this->contractService->activateContract($contract);
            $this->addFlash('success', 'Contrat activé avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'activation : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_contracts_show', ['id' => $contract->getId()]);
    }

    /**
     * Regenerate contract
     */
    #[Route('/{id}/regenerate', name: 'app_admin_contracts_regenerate', methods: ['POST'])]
    public function regenerate(LoanContract $contract): Response
    {
        if ($contract->isActive()) {
            $this->addFlash('error', 'Impossible de régénérer un contrat actif.');
            return $this->redirectToRoute('app_admin_contracts_show', ['id' => $contract->getId()]);
        }

        try {
            $this->contractService->regenerateContract($contract);
            $this->addFlash('success', 'Contrat régénéré avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la régénération : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_admin_contracts_show', ['id' => $contract->getId()]);
    }

    /**
     * Download contract (admin)
     */
    #[Route('/{id}/download', name: 'app_admin_contracts_download', methods: ['GET'])]
    public function download(LoanContract $contract): Response
    {
        $filePath = $this->contractService->getContractFilePath($contract);
        
        if (!$filePath || !file_exists($filePath)) {
            $this->addFlash('error', 'Le fichier du contrat est introuvable.');
            return $this->redirectToRoute('app_admin_contracts_show', ['id' => $contract->getId()]);
        }

        return new BinaryFileResponse(
            $filePath,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $contract->getFileName() . '"'
            ]
        );
    }

    /**
     * Update contract status
     */
    #[Route('/{id}/status', name: 'app_admin_contracts_update_status', methods: ['POST'])]
    public function updateStatus(LoanContract $contract, Request $request): Response
    {
        $newStatus = $request->request->get('status');
        
        $allowedStatuses = ['generated', 'sent', 'signed', 'active', 'completed', 'cancelled'];
        
        if (!in_array($newStatus, $allowedStatuses)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('app_admin_contracts_show', ['id' => $contract->getId()]);
        }

        $contract->setStatus($newStatus);
        $contract->setUpdatedAt(new \DateTime());
        
        $this->entityManager->flush();
        
        $this->addFlash('success', 'Statut du contrat mis à jour.');
        
        return $this->redirectToRoute('app_admin_contracts_show', ['id' => $contract->getId()]);
    }

    /**
     * Bulk actions on contracts
     */
    #[Route('/bulk-action', name: 'app_admin_contracts_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        $action = $request->request->get('action');
        $contractIds = $request->request->get('contract_ids', []);

        if (empty($contractIds)) {
            $this->addFlash('error', 'Aucun contrat sélectionné.');
            return $this->redirectToRoute('app_admin_contracts_index');
        }

        $contracts = $this->contractRepository->findBy(['id' => $contractIds]);
        $count = 0;

        foreach ($contracts as $contract) {
            try {
                switch ($action) {
                    case 'send':
                        if ($contract->getStatus() === 'generated') {
                            $this->contractService->sendContract($contract);
                            $count++;
                        }
                        break;
                    
                    case 'activate':
                        if ($contract->isSigned() && !$contract->isActive()) {
                            $this->contractService->activateContract($contract);
                            $count++;
                        }
                        break;
                    
                    case 'cancel':
                        if (!$contract->isActive()) {
                            $contract->setStatus('cancelled');
                            $count++;
                        }
                        break;
                }
            } catch (\Exception $e) {
                // Log error but continue with other contracts
                continue;
            }
        }

        if ($count > 0) {
            $this->entityManager->flush();
            $this->addFlash('success', "$count contrats traités avec succès.");
        } else {
            $this->addFlash('warning', 'Aucun contrat n\'a pu être traité.');
        }

        return $this->redirectToRoute('app_admin_contracts_index');
    }
}