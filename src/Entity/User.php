<?php

namespace App\Entity;

use App\Enum\AccountType;
use App\Enum\VerificationStatus;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Il existe déjà un compte avec cet email')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'L\'email ne peut pas être vide')]
    #[Assert\Email(message: 'L\'email {{ value }} n\'est pas valide')]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom ne peut pas être vide')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le prénom doit faire au moins {{ limit }} caractères', maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères')]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom ne peut pas être vide')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le nom doit faire au moins {{ limit }} caractères', maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')]
    private ?string $lastName = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    // NOUVELLES PROPRIÉTÉS POUR LE SYSTÈME DE PRÊT
    
    #[ORM\Column(type: 'string', enumType: AccountType::class)]
    private AccountType $accountType = AccountType::INDIVIDUAL;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^(\+33|0)[1-9](\d{8})$/', message: 'Numéro de téléphone invalide')]
    private ?string $phoneNumber = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateOfBirth = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $nationalId = null;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(type: 'string', enumType: VerificationStatus::class)]
    private VerificationStatus $verificationStatus = VerificationStatus::PENDING;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $monthlyIncome = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $employmentStatus = null;

    // Propriétés spécifiques aux entreprises
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(length: 14, nullable: true)]
    private ?string $siretNumber = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $businessSector = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $legalForm = null;

    // Relations
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: LoanApplication::class)]
    private Collection $loanApplications;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: AccountVerification::class)]
    private Collection $verifications;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->loanApplications = new ArrayCollection();
        $this->verifications = new ArrayCollection();
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

    public function hasRole($role)
    {
        return in_array(strtoupper($role), $this->getRoles(), true);
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    // GETTERS ET SETTERS POUR LES NOUVELLES PROPRIÉTÉS

    public function getAccountType(): AccountType
    {
        return $this->accountType;
    }

    public function setAccountType(AccountType $accountType): static
    {
        $this->accountType = $accountType;
        return $this;
    }

    public function isIndividual(): bool
    {
        return $this->accountType === AccountType::INDIVIDUAL;
    }

    public function isBusiness(): bool
    {
        return $this->accountType === AccountType::BUSINESS;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;
        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;
        return $this;
    }

    public function getDateOfBirth(): ?\DateTimeInterface
    {
        return $this->dateOfBirth;
    }

    public function setDateOfBirth(?\DateTimeInterface $dateOfBirth): static
    {
        $this->dateOfBirth = $dateOfBirth;
        return $this;
    }

    public function getNationalId(): ?string
    {
        return $this->nationalId;
    }

    public function setNationalId(?string $nationalId): static
    {
        $this->nationalId = $nationalId;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function getVerificationStatus(): VerificationStatus
    {
        return $this->verificationStatus;
    }

    public function setVerificationStatus(VerificationStatus $verificationStatus): static
    {
        $this->verificationStatus = $verificationStatus;
        return $this;
    }

    public function getMonthlyIncome(): ?string
    {
        return $this->monthlyIncome;
    }

    public function setMonthlyIncome(?string $monthlyIncome): static
    {
        $this->monthlyIncome = $monthlyIncome;
        return $this;
    }

    public function getEmploymentStatus(): ?string
    {
        return $this->employmentStatus;
    }

    public function setEmploymentStatus(?string $employmentStatus): static
    {
        $this->employmentStatus = $employmentStatus;
        return $this;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): static
    {
        $this->companyName = $companyName;
        return $this;
    }

    public function getSiretNumber(): ?string
    {
        return $this->siretNumber;
    }

    public function setSiretNumber(?string $siretNumber): static
    {
        $this->siretNumber = $siretNumber;
        return $this;
    }

    public function getBusinessSector(): ?string
    {
        return $this->businessSector;
    }

    public function setBusinessSector(?string $businessSector): static
    {
        $this->businessSector = $businessSector;
        return $this;
    }

    public function getLegalForm(): ?string
    {
        return $this->legalForm;
    }

    public function setLegalForm(?string $legalForm): static
    {
        $this->legalForm = $legalForm;
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
            $loanApplication->setUser($this);
        }

        return $this;
    }

    public function removeLoanApplication(LoanApplication $loanApplication): static
    {
        if ($this->loanApplications->removeElement($loanApplication)) {
            if ($loanApplication->getUser() === $this) {
                $loanApplication->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AccountVerification>
     */
    public function getVerifications(): Collection
    {
        return $this->verifications;
    }

    public function addVerification(AccountVerification $verification): static
    {
        if (!$this->verifications->contains($verification)) {
            $this->verifications->add($verification);
            $verification->setUser($this);
        }

        return $this;
    }

    public function removeVerification(AccountVerification $verification): static
    {
        if ($this->verifications->removeElement($verification)) {
            if ($verification->getUser() === $this) {
                $verification->setUser(null);
            }
        }

        return $this;
    }

    // MÉTHODES UTILITAIRES

    public function getFullAddress(): string
    {
        $parts = array_filter([$this->address, $this->postalCode, $this->city, $this->country]);
        return implode(', ', $parts);
    }

    public function getDisplayName(): string
    {
        if ($this->isBusiness() && $this->companyName) {
            return $this->companyName;
        }
        return $this->getFullName();
    }

    public function getAge(): ?int
    {
        if (!$this->dateOfBirth) {
            return null;
        }
        return $this->dateOfBirth->diff(new \DateTime())->y;
    }

    public function canApplyForLoan(): bool
    {
        return $this->isActive && $this->isVerified;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }
}
