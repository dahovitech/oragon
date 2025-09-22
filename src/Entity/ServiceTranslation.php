<?php

namespace App\Entity;

use App\Entity\Trait\TranslationEntityTrait;
use App\Repository\ServiceTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité de traduction pour Service
 * Contient toutes les propriétés traduisibles d'un service
 */
#[ORM\Entity(repositoryClass: ServiceTranslationRepository::class)]
#[ORM\Table(name: 'service_translations')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['translatable', 'language'], message: 'Une traduction pour cette langue existe déjà.')]
class ServiceTranslation
{
    use TranslationEntityTrait;

    #[ORM\ManyToOne(targetEntity: Service::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Service $translatable = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    private string $title = '';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $metaTitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 160)]
    private ?string $metaDescription = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metaKeywords = null;

    public function getTranslatable(): ?Service
    {
        return $this->translatable;
    }

    public function setTranslatable($translatable): static
    {
        $this->translatable = $translatable;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): static
    {
        $this->metaTitle = $metaTitle;
        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): static
    {
        $this->metaDescription = $metaDescription;
        return $this;
    }

    public function getMetaKeywords(): ?array
    {
        return $this->metaKeywords;
    }

    public function setMetaKeywords(?array $metaKeywords): static
    {
        $this->metaKeywords = $metaKeywords;
        return $this;
    }

    /**
     * Vérifier si la traduction est complète
     * Une traduction est considérée comme complète si elle a au minimum un titre et une description
     */
    public function isComplete(): bool
    {
        return !empty($this->title) && !empty($this->description);
    }

    /**
     * Calculer le pourcentage de complétion de la traduction
     * Basé sur les champs remplis vs les champs disponibles
     */
    public function getCompletionPercentage(): int
    {
        $fields = [
            'title' => $this->title,
            'description' => $this->description,
            'content' => $this->content,
            'metaTitle' => $this->metaTitle,
            'metaDescription' => $this->metaDescription,
        ];

        $totalFields = count($fields);
        $completedFields = 0;

        foreach ($fields as $value) {
            if (!empty($value)) {
                $completedFields++;
            }
        }

        return $totalFields > 0 ? intval(($completedFields / $totalFields) * 100) : 0;
    }

    /**
     * Obtenir un résumé du statut de la traduction
     */
    public function getTranslationStatus(): string
    {
        $completion = $this->getCompletionPercentage();

        if ($completion === 100) {
            return 'complete';
        } elseif ($completion >= 50) {
            return 'partial';
        } elseif ($completion > 0) {
            return 'started';
        } else {
            return 'empty';
        }
    }

    /**
     * Copier le contenu d'une autre traduction (utile pour initialiser une nouvelle traduction)
     */
    public function copyFrom(ServiceTranslation $source): static
    {
        $this->title = $source->getTitle();
        $this->description = $source->getDescription();
        $this->content = $source->getContent();
        $this->metaTitle = $source->getMetaTitle();
        $this->metaDescription = $source->getMetaDescription();
        $this->metaKeywords = $source->getMetaKeywords();

        return $this;
    }

    /**
     * Obtenir un tableau des champs non traduits
     */
    public function getMissingFields(): array
    {
        $missing = [];

        if (empty($this->title)) {
            $missing[] = 'title';
        }
        if (empty($this->description)) {
            $missing[] = 'description';
        }
        if (empty($this->content)) {
            $missing[] = 'content';
        }
        if (empty($this->metaTitle)) {
            $missing[] = 'metaTitle';
        }
        if (empty($this->metaDescription)) {
            $missing[] = 'metaDescription';
        }

        return $missing;
    }

    public function __toString(): string
    {
        $languageName = $this->language ? $this->language->getName() : 'Unknown';
        return sprintf('%s (%s)', $this->title ?: 'Sans titre', $languageName);
    }
}
