<?php

namespace App\Entity;

use App\Enum\AccountType;
use App\Repository\LoanTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoanTypeRepository::class)]
#[ORM\Table(name: 'loan_types')]
#[ORM\HasLifecycleCallbacks]
class LoanType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $slug = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Media $image = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    // Propriétés spécifiques aux prêts
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private string $minAmount = '1000.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private string $maxAmount = '100000.00';

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private int $minDuration = 12; // en mois

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private int $maxDuration = 84; // en mois

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\Positive]
    private string $baseInterestRate = '3.50';

    #[ORM\Column(type: 'json')]
    private array $allowedAccountTypes = ['INDIVIDUAL', 'BUSINESS'];

    #[ORM\Column(type: 'json')]
    private array $requiredDocuments = [];

    #[ORM\Column(type: 'boolean')]
    private bool $requiresGuarantee = false;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $processingFees = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    // Relations
    #[ORM\OneToMany(mappedBy: 'loanType', targetEntity: LoanTypeTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    #[ORM\OneToMany(mappedBy: 'loanType', targetEntity: LoanApplication::class)]
    private Collection $loanApplications;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->loanApplications = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters et Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getImage(): ?Media
    {
        return $this->image;
    }

    public function setImage(?Media $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;
        return $this;
    }

    public function getMinAmount(): string
    {
        return $this->minAmount;
    }

    public function setMinAmount(string $minAmount): static
    {
        $this->minAmount = $minAmount;
        return $this;
    }

    public function getMaxAmount(): string
    {
        return $this->maxAmount;
    }

    public function setMaxAmount(string $maxAmount): static
    {
        $this->maxAmount = $maxAmount;
        return $this;
    }

    public function getMinDuration(): int
    {
        return $this->minDuration;
    }

    public function setMinDuration(int $minDuration): static
    {
        $this->minDuration = $minDuration;
        return $this;
    }

    public function getMaxDuration(): int
    {
        return $this->maxDuration;
    }

    public function setMaxDuration(int $maxDuration): static
    {
        $this->maxDuration = $maxDuration;
        return $this;
    }

    public function getBaseInterestRate(): string
    {
        return $this->baseInterestRate;
    }

    public function setBaseInterestRate(string $baseInterestRate): static
    {
        $this->baseInterestRate = $baseInterestRate;
        return $this;
    }

    public function getAllowedAccountTypes(): array
    {
        return $this->allowedAccountTypes;
    }

    public function setAllowedAccountTypes(array $allowedAccountTypes): static
    {
        $this->allowedAccountTypes = $allowedAccountTypes;
        return $this;
    }

    public function isAvailableForAccountType(AccountType $accountType): bool
    {
        return in_array($accountType->value, $this->allowedAccountTypes);
    }

    public function getRequiredDocuments(): array
    {
        return $this->requiredDocuments;
    }

    public function setRequiredDocuments(array $requiredDocuments): static
    {
        $this->requiredDocuments = $requiredDocuments;
        return $this;
    }

    public function requiresGuarantee(): bool
    {
        return $this->requiresGuarantee;
    }

    public function setRequiresGuarantee(bool $requiresGuarantee): static
    {
        $this->requiresGuarantee = $requiresGuarantee;
        return $this;
    }

    public function getProcessingFees(): ?string
    {
        return $this->processingFees;
    }

    public function setProcessingFees(?string $processingFees): static
    {
        $this->processingFees = $processingFees;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(): static
    {
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * @return Collection<int, LoanTypeTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(LoanTypeTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setLoanType($this);
        }

        return $this;
    }

    public function removeTranslation(LoanTypeTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getLoanType() === $this) {
                $translation->setLoanType(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, LoanApplication>
     */
    public function getLoanApplications(): Collection
    {
        return $this->loanApplications;
    }

    public function addLoanApplication(LoanApplication $loanApplication): static
    {
        if (!$this->loanApplications->contains($loanApplication)) {
            $this->loanApplications->add($loanApplication);
            $loanApplication->setLoanType($this);
        }

        return $this;
    }

    public function removeLoanApplication(LoanApplication $loanApplication): static
    {
        if ($this->loanApplications->removeElement($loanApplication)) {
            if ($loanApplication->getLoanType() === $this) {
                $loanApplication->setLoanType(null);
            }
        }

        return $this;
    }

    // Méthodes de traduction (réutilisées de Service.php)
    public function getTranslation(?string $languageCode = null): ?LoanTypeTranslation
    {
        if ($languageCode === null) {
            return $this->translations->first() ?: null;
        }

        foreach ($this->translations as $translation) {
            if ($translation->getLanguage()->getCode() === $languageCode) {
                return $translation;
            }
        }

        return null;
    }

    public function getTranslationWithFallback(string $languageCode, string $fallbackLanguageCode = 'fr'): ?LoanTypeTranslation
    {
        $translation = $this->getTranslation($languageCode);
        
        if (!$translation) {
            $translation = $this->getTranslation($fallbackLanguageCode);
        }

        return $translation;
    }

    public function getTitle(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslationWithFallback($languageCode, $fallbackLanguageCode);
        return $translation ? $translation->getTitle() : 'Type de prêt sans nom';
    }

    public function getDescription(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslationWithFallback($languageCode, $fallbackLanguageCode);
        return $translation ? $translation->getDescription() : '';
    }

    public function hasTranslation(string $languageCode): bool
    {
        return $this->getTranslation($languageCode) !== null;
    }

    // Méthodes utilitaires
    public function getAmountRange(): string
    {
        return number_format((float)$this->minAmount, 0, ',', ' ') . '€ - ' . 
               number_format((float)$this->maxAmount, 0, ',', ' ') . '€';
    }

    public function getDurationRange(): string
    {
        return $this->minDuration . ' - ' . $this->maxDuration . ' mois';
    }

    public function calculateMonthlyPayment(float $amount, int $duration): float
    {
        $monthlyRate = (float)$this->baseInterestRate / 100 / 12;
        if ($monthlyRate == 0) {
            return $amount / $duration;
        }
        return $amount * ($monthlyRate * pow(1 + $monthlyRate, $duration)) / (pow(1 + $monthlyRate, $duration) - 1);
    }

    public function calculateTotalAmount(float $amount, int $duration): float
    {
        return $this->calculateMonthlyPayment($amount, $duration) * $duration;
    }

    public function __toString(): string
    {
        return $this->getTitle();
    }
}