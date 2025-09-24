<?php

namespace App\Service;

use App\Entity\LoanContract;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ContractPdfGenerator
{
    public function __construct(
        private ParameterBagInterface $parameterBag
    ) {
    }

    public function generateContract(LoanContract $contract): string
    {
        $projectDir = $this->parameterBag->get('kernel.project_dir');
        $uploadsDir = $projectDir . '/public/uploads/contracts';
        
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $fileName = 'contract_' . $contract->getContractNumber() . '_' . date('Y-m-d-H-i-s') . '.pdf';
        $filePath = $uploadsDir . '/' . $fileName;

        // Generate HTML content
        $htmlContent = $this->generateHtmlContent($contract);
        
        // For now, we'll create a simple HTML-to-PDF conversion
        // In production, you would use a library like wkhtmltopdf or mPDF
        $this->createPdf($htmlContent, $filePath);

        return '/uploads/contracts/' . $fileName;
    }

    private function generateHtmlContent(LoanContract $contract): string
    {
        $loanApplication = $contract->getLoanApplication();
        $user = $loanApplication->getUser();
        $loanType = $loanApplication->getLoanType();

        $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat de Prêt - ' . $contract->getContractNumber() . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .header { text-align: center; margin-bottom: 40px; }
        .header h1 { color: #333; margin-bottom: 10px; }
        .section { margin-bottom: 30px; }
        .section h2 { color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
        .info-item { margin-bottom: 10px; }
        .info-item strong { color: #333; }
        .signature-box { border: 1px solid #ccc; padding: 20px; margin-top: 40px; }
        .footer { margin-top: 50px; font-size: 12px; color: #666; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th, table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        table th { background-color: #f8f9fa; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONTRAT DE PRÊT</h1>
        <p><strong>N° ' . $contract->getContractNumber() . '</strong></p>
        <p>Établi le ' . $contract->getCreatedAt()->format('d/m/Y') . '</p>
    </div>

    <div class="section">
        <h2>PARTIES AU CONTRAT</h2>
        <div class="info-grid">
            <div>
                <h3>LE PRÊTEUR</h3>
                <div class="info-item"><strong>Raison sociale :</strong> Easilon Finance</div>
                <div class="info-item"><strong>Adresse :</strong> 123 Avenue des Champs-Élysées, 75008 Paris</div>
                <div class="info-item"><strong>SIRET :</strong> 123 456 789 00001</div>
                <div class="info-item"><strong>Email :</strong> contact@easilon.com</div>
            </div>
            <div>
                <h3>L\'EMPRUNTEUR</h3>
                <div class="info-item"><strong>Nom :</strong> ' . ($user->getFirstName() ?? 'N/A') . ' ' . ($user->getLastName() ?? '') . '</div>
                <div class="info-item"><strong>Email :</strong> ' . $user->getEmail() . '</div>
                <div class="info-item"><strong>Type de compte :</strong> ' . ($user->getAccountType() === 'BUSINESS' ? 'Entreprise' : 'Particulier') . '</div>
                <div class="info-item"><strong>Adresse :</strong> ' . ($user->getAddress() ?? 'Non renseignée') . '</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>OBJET DU PRÊT</h2>
        <div class="info-item"><strong>Type de prêt :</strong> ' . $loanType->getName() . '</div>
        <div class="info-item"><strong>Objet :</strong> ' . $loanApplication->getPurpose() . '</div>
    </div>

    <div class="section">
        <h2>CONDITIONS FINANCIÈRES</h2>
        <div class="info-grid">
            <div>
                <div class="info-item"><strong>Montant du prêt :</strong> ' . number_format($loanApplication->getRequestedAmount(), 2, ',', ' ') . ' €</div>
                <div class="info-item"><strong>Durée :</strong> ' . $loanApplication->getDuration() . ' mois</div>
                <div class="info-item"><strong>Taux d\'intérêt annuel :</strong> ' . $loanApplication->getInterestRate() . ' %</div>
            </div>
            <div>
                <div class="info-item"><strong>Mensualité :</strong> ' . number_format($loanApplication->getMonthlyPayment(), 2, ',', ' ') . ' €</div>
                <div class="info-item"><strong>Coût total du crédit :</strong> ' . number_format($loanApplication->getTotalAmount() - $loanApplication->getRequestedAmount(), 2, ',', ' ') . ' €</div>
                <div class="info-item"><strong>Montant total à rembourser :</strong> ' . number_format($loanApplication->getTotalAmount(), 2, ',', ' ') . ' €</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>DATES ET ÉCHÉANCES</h2>
        <div class="info-item"><strong>Date de début :</strong> ' . $contract->getStartDate()->format('d/m/Y') . '</div>
        <div class="info-item"><strong>Date de fin :</strong> ' . $contract->getEndDate()->format('d/m/Y') . '</div>
        <div class="info-item"><strong>Première échéance :</strong> ' . $contract->getStartDate()->modify('+1 month')->format('d/m/Y') . '</div>
    </div>

    <div class="section">
        <h2>CONDITIONS GÉNÉRALES</h2>
        <p><strong>Article 1 - Objet du contrat</strong></p>
        <p>Le présent contrat a pour objet l\'octroi d\'un prêt de ' . number_format($loanApplication->getRequestedAmount(), 0, ',', ' ') . ' euros par le Prêteur à l\'Emprunteur, aux conditions définies ci-après.</p>
        
        <p><strong>Article 2 - Modalités de remboursement</strong></p>
        <p>Le remboursement s\'effectuera par mensualités constantes de ' . number_format($loanApplication->getMonthlyPayment(), 2, ',', ' ') . ' euros, payables à terme échu le 5 de chaque mois.</p>
        
        <p><strong>Article 3 - Remboursement anticipé</strong></p>
        <p>L\'Emprunteur peut à tout moment rembourser par anticipation, partiellement ou totalement, le capital restant dû, sans pénalité.</p>
        
        <p><strong>Article 4 - Défaillance</strong></p>
        <p>En cas de retard de paiement supérieur à 30 jours, des pénalités de retard de 1,5% par mois seront appliquées sur les sommes dues.</p>
        
        <p><strong>Article 5 - Résiliation</strong></p>
        <p>Le contrat peut être résilié par le Prêteur en cas de non-respect des obligations de l\'Emprunteur après mise en demeure restée sans effet pendant 15 jours.</p>
    </div>';

        // Add payment schedule if available
        if ($contract->getPaymentSchedule()) {
            $html .= '
    <div class="section">
        <h2>ÉCHÉANCIER DE REMBOURSEMENT</h2>
        <table>
            <thead>
                <tr>
                    <th>N°</th>
                    <th>Date d\'échéance</th>
                    <th>Montant total</th>
                    <th>Capital</th>
                    <th>Intérêts</th>
                    <th>Capital restant</th>
                </tr>
            </thead>
            <tbody>';
            
            foreach (array_slice($contract->getPaymentSchedule(), 0, 12) as $payment) { // Show first 12 payments
                $html .= '
                <tr>
                    <td>' . $payment['payment_number'] . '</td>
                    <td>' . date('d/m/Y', strtotime($payment['due_date'])) . '</td>
                    <td>' . number_format($payment['total_amount'], 2, ',', ' ') . ' €</td>
                    <td>' . number_format($payment['principal_amount'], 2, ',', ' ') . ' €</td>
                    <td>' . number_format($payment['interest_amount'], 2, ',', ' ') . ' €</td>
                    <td>' . number_format($payment['remaining_principal'], 2, ',', ' ') . ' €</td>
                </tr>';
            }
            
            if (count($contract->getPaymentSchedule()) > 12) {
                $html .= '<tr><td colspan="6" style="text-align: center; font-style: italic;">... et ' . (count($contract->getPaymentSchedule()) - 12) . ' autres échéances</td></tr>';
            }
            
            $html .= '
            </tbody>
        </table>
    </div>';
        }

        $html .= '
    <div class="section">
        <h2>SIGNATURES</h2>
        <div class="info-grid">
            <div class="signature-box">
                <p><strong>L\'Emprunteur</strong></p>
                <p>Nom : ' . ($user->getFirstName() ?? 'N/A') . ' ' . ($user->getLastName() ?? '') . '</p>
                <p>Date : ____________________</p>
                <p>Signature :</p>
                <br><br><br>
                <p style="border-top: 1px solid #ccc; padding-top: 10px;">
                    Lu et approuvé<br>
                    Bon pour accord
                </p>
            </div>
            <div class="signature-box">
                <p><strong>Le Prêteur</strong></p>
                <p>Easilon Finance</p>
                <p>Date : ____________________</p>
                <p>Signature :</p>
                <br><br><br>
                <p style="border-top: 1px solid #ccc; padding-top: 10px;">
                    Cachet de l\'entreprise
                </p>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>Contrat généré le ' . date('d/m/Y à H:i') . ' - Document confidentiel</p>
        <p>Easilon Finance - 123 Avenue des Champs-Élysées, 75008 Paris - contact@easilon.com</p>
    </div>
</body>
</html>';

        return $html;
    }

    private function createPdf(string $htmlContent, string $filePath): void
    {
        // For this demo, we'll save as HTML. In production, use a proper PDF library
        // like mPDF, TCPDF, or wkhtmltopdf
        
        // Simple conversion: save HTML content with PDF-like formatting
        $pdfContent = $htmlContent;
        
        // Add some basic PDF metadata
        $pdfContent = '<!-- PDF Generated on ' . date('Y-m-d H:i:s') . ' -->' . "\n" . $pdfContent;
        
        file_put_contents($filePath, $pdfContent);
        
        // Note: In a real implementation, you would use something like:
        // $mpdf = new \Mpdf\Mpdf();
        // $mpdf->WriteHTML($htmlContent);
        // $mpdf->Output($filePath, 'F');
    }
}