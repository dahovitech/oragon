<?php

namespace App\Entity;

use App\Repository\LoanTypeTranslationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LoanTypeTranslationRepository::class)]
#[ORM\Table(name: 'loan_type_translations')]
class LoanTypeTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $conditions = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $benefits = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $shortDescription = null;

    #[ORM\ManyToOne(targetEntity: Language::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Language $language;

    #[ORM\ManyToOne(targetEntity: LoanType::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private LoanType $loanType;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;
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

    public function getConditions(): ?string
    {
        return $this->conditions;
    }

    public function setConditions(?string $conditions): static
    {
        $this->conditions = $conditions;
        return $this;
    }

    public function getBenefits(): ?string
    {
        return $this->benefits;
    }

    public function setBenefits(?string $benefits): static
    {
        $this->benefits = $benefits;
        return $this;
    }

    public function getShortDescription(): ?string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(?string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;
        return $this;
    }

    public function getLanguage(): Language
    {
        return $this->language;
    }

    public function setLanguage(Language $language): static
    {
        $this->language = $language;
        return $this;
    }

    public function getLoanType(): LoanType
    {
        return $this->loanType;
    }

    public function setLoanType(LoanType $loanType): static
    {
        $this->loanType = $loanType;
        return $this;
    }

    /**
     * Check if translation is complete
     */
    public function isComplete(): bool
    {
        return !empty($this->title) && !empty($this->description);
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercentage(): int
    {
        $fields = ['title', 'description', 'conditions', 'benefits', 'shortDescription'];
        $completed = 0;
        
        foreach ($fields as $field) {
            if (!empty($this->$field)) {
                $completed++;
            }
        }
        
        return (int) ($completed / count($fields) * 100);
    }

    public function __toString(): string
    {
        return $this->title ?: 'Translation sans titre';
    }
}