<?php

namespace App\Entity;

use App\Repository\LoanTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoanTypeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class LoanType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?string $minAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?string $maxAmount = null;

    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?int $minDuration = null; // in months

    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?int $maxDuration = null; // in months

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private ?string $baseInterestRate = null;

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::JSON)]
    private array $allowedAccountTypes = ['INDIVIDUAL', 'BUSINESS'];

    #[ORM\Column(type: Types::JSON)]
    private array $requiredDocuments = [];

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Media $image = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'loanType', targetEntity: LoanApplication::class)]
    private Collection $loanApplications;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->loanApplications = new ArrayCollection();
        $this->isActive = true;
        $this->allowedAccountTypes = ['INDIVIDUAL', 'BUSINESS'];
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getMinAmount(): ?string
    {
        return $this->minAmount;
    }

    public function setMinAmount(string $minAmount): static
    {
        $this->minAmount = $minAmount;
        return $this;
    }

    public function getMaxAmount(): ?string
    {
        return $this->maxAmount;
    }

    public function setMaxAmount(string $maxAmount): static
    {
        $this->maxAmount = $maxAmount;
        return $this;
    }

    public function getMinDuration(): ?int
    {
        return $this->minDuration;
    }

    public function setMinDuration(int $minDuration): static
    {
        $this->minDuration = $minDuration;
        return $this;
    }

    public function getMaxDuration(): ?int
    {
        return $this->maxDuration;
    }

    public function setMaxDuration(int $maxDuration): static
    {
        $this->maxDuration = $maxDuration;
        return $this;
    }

    public function getBaseInterestRate(): ?string
    {
        return $this->baseInterestRate;
    }

    public function setBaseInterestRate(string $baseInterestRate): static
    {
        $this->baseInterestRate = $baseInterestRate;
        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
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

    public function getRequiredDocuments(): array
    {
        return $this->requiredDocuments;
    }

    public function setRequiredDocuments(array $requiredDocuments): static
    {
        $this->requiredDocuments = $requiredDocuments;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
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
}