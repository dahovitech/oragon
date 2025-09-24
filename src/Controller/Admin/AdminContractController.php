<?php

namespace App\Controller\Admin;

use App\Entity\LoanContract;
use App\Entity\Payment;
use App\Repository\LoanContractRepository;
use App\Service\ContractPdfGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/contracts')]
#[IsGranted('ROLE_ADMIN')]
class AdminContractController extends AbstractController
{
    public function __construct(
        private LoanContractRepository $contractRepository,
        private EntityManagerInterface $entityManager,
        private ContractPdfGenerator $pdfGenerator
    ) {
    }

    #[Route('/', name: 'admin_contracts_index')]
    public function index(Request $request): Response
    {
        $status = $request->query->get('status', 'all');
        $search = $request->query->get('search', '');
        
        $queryBuilder = $this->contractRepository->createQueryBuilder('c')
            ->leftJoin('c.loanApplication', 'la')
            ->leftJoin('la.user', 'u')
            ->leftJoin('la.loanType', 'lt')
            ->orderBy('c.createdAt', 'DESC');

        // Filter by status
        if ($status === 'signed') {
            $queryBuilder->andWhere('c.signedAt IS NOT NULL');
        } elseif ($status === 'pending') {
            $queryBuilder->andWhere('c.signedAt IS NULL');
        } elseif ($status === 'active') {
            $queryBuilder->andWhere('c.isActive = :active')
                        ->setParameter('active', true);
        }

        // Search filter
        if ($search) {
            $queryBuilder->andWhere('u.email LIKE :search OR u.firstName LIKE :search OR u.lastName LIKE :search OR c.contractNumber LIKE :search')
                        ->setParameter('search', '%' . $search . '%');
        }

        $contracts = $queryBuilder->getQuery()->getResult();

        return $this->render('admin/contracts/index.html.twig', [
            'contracts' => $contracts,
            'current_status' => $status,
            'search' => $search,
        ]);
    }

    #[Route('/{id}', name: 'admin_contract_detail', requirements: ['id' => '\d+'])]
    public function detail(LoanContract $contract): Response
    {
        return $this->render('admin/contracts/detail.html.twig', [
            'contract' => $contract,
        ]);
    }

    #[Route('/{id}/generate-pdf', name: 'admin_contract_generate_pdf', requirements: ['id' => '\d+'])]
    public function generatePdf(LoanContract $contract): Response
    {
        try {
            $pdfPath = $this->pdfGenerator->generateContract($contract);
            $contract->setContractPdf($pdfPath);
            $this->entityManager->flush();

            $this->addFlash('success', 'Le PDF du contrat a été généré avec succès.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la génération du PDF: ' . $e->getMessage());
        }

        return $this->redirectToRoute('admin_contract_detail', ['id' => $contract->getId()]);
    }

    #[Route('/{id}/generate-payments', name: 'admin_contract_generate_payments', requirements: ['id' => '\d+'])]
    public function generatePayments(LoanContract $contract): Response
    {
        // Remove existing payments
        foreach ($contract->getPayments() as $payment) {
            $this->entityManager->remove($payment);
        }

        // Generate new payments from schedule
        $paymentSchedule = $contract->getPaymentSchedule();
        
        if ($paymentSchedule) {
            foreach ($paymentSchedule as $scheduleItem) {
                $payment = new Payment();
                $payment->setLoanContract($contract);
                $payment->setPaymentNumber($scheduleItem['payment_number']);
                $payment->setDueDate(\DateTime::createFromFormat('Y-m-d', $scheduleItem['due_date']));
                $payment->setAmount($scheduleItem['total_amount']);
                $payment->setPrincipalAmount($scheduleItem['principal_amount']);
                $payment->setInterestAmount($scheduleItem['interest_amount']);
                $payment->setStatus('PENDING');

                $this->entityManager->persist($payment);
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'L\'échéancier a été généré avec succès.');
        } else {
            $this->addFlash('error', 'Aucun échéancier défini pour ce contrat.');
        }

        return $this->redirectToRoute('admin_contract_detail', ['id' => $contract->getId()]);
    }

    #[Route('/{id}/activate', name: 'admin_contract_activate', requirements: ['id' => '\d+'])]
    public function activate(LoanContract $contract): Response
    {
        $contract->setIsActive(true);
        $contract->setSignedAt(new \DateTimeImmutable());
        
        // Update loan application status
        $loanApplication = $contract->getLoanApplication();
        $loanApplication->setStatus('DISBURSED');

        $this->entityManager->flush();

        $this->addFlash('success', 'Le contrat a été activé et les fonds sont maintenant débloqués.');

        return $this->redirectToRoute('admin_contract_detail', ['id' => $contract->getId()]);
    }

    #[Route('/payments', name: 'admin_payments_index')]
    public function payments(Request $request): Response
    {
        $status = $request->query->get('status', 'all');
        $overdue = $request->query->get('overdue', false);
        
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Payment::class, 'p')
            ->leftJoin('p.loanContract', 'c')
            ->leftJoin('c.loanApplication', 'la')
            ->leftJoin('la.user', 'u')
            ->orderBy('p.dueDate', 'ASC');

        // Filter by status
        if ($status !== 'all') {
            $queryBuilder->andWhere('p.status = :status')
                        ->setParameter('status', $status);
        }

        // Filter overdue payments
        if ($overdue) {
            $queryBuilder->andWhere('p.dueDate < :today AND p.status = :pending')
                        ->setParameter('today', new \DateTime())
                        ->setParameter('pending', 'PENDING');
        }

        $payments = $queryBuilder->getQuery()->getResult();

        // Calculate stats
        $stats = [
            'pending' => $this->entityManager->getRepository(Payment::class)->count(['status' => 'PENDING']),
            'paid' => $this->entityManager->getRepository(Payment::class)->count(['status' => 'PAID']),
            'late' => $this->entityManager->getRepository(Payment::class)->count(['status' => 'LATE']),
            'missed' => $this->entityManager->getRepository(Payment::class)->count(['status' => 'MISSED']),
        ];

        return $this->render('admin/payments/index.html.twig', [
            'payments' => $payments,
            'stats' => $stats,
            'current_status' => $status,
            'show_overdue' => $overdue,
        ]);
    }

    #[Route('/payments/{id}/mark-paid', name: 'admin_payment_mark_paid', requirements: ['id' => '\d+'])]
    public function markPaymentPaid(Request $request): Response
    {
        $paymentId = $request->get('id');
        $payment = $this->entityManager->getRepository(Payment::class)->find($paymentId);

        if (!$payment) {
            throw $this->createNotFoundException('Payment not found');
        }

        $payment->setStatus('PAID');
        $payment->setPaidAt(new \DateTime());
        $payment->setPaymentMethod($request->request->get('payment_method', 'BANK_TRANSFER'));

        $this->entityManager->flush();

        $this->addFlash('success', 'Le paiement a été marqué comme payé.');

        return $this->redirectToRoute('admin_payments_index');
    }
}