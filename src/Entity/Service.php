<?php

namespace App\Entity;

use App\Entity\Trait\TranslatableEntityTrait;
use App\Repository\ServiceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité Service avec support multilingue
 * Entité pilote pour tester et valider le système de traduction
 */
#[ORM\Entity(repositoryClass: ServiceRepository::class)]
#[ORM\Table(name: 'services')]
#[ORM\HasLifecycleCallbacks]
class Service
{
    use TranslatableEntityTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    private string $slug;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Media $image = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Collection des traductions (redéfinition pour typage)
     * @var Collection<int, ServiceTranslation>
     */
    #[ORM\OneToMany(mappedBy: 'translatable', targetEntity: ServiceTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    protected Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
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

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    #[ORM\PreUpdate]
    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Retourne la classe de traduction utilisée
     */
    public static function getTranslationClass(): string
    {
        return ServiceTranslation::class;
    }

    /**
     * Collection typée des traductions
     * @return Collection<int, ServiceTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    // Méthodes de convenance pour accéder aux propriétés traduites

    /**
     * Obtenir le titre dans la langue courante ou une langue de fallback
     */
    public function getTitle(?string $locale = null): ?string
    {
        $locale = $locale ?? $this->currentLocale;
        
        if ($locale) {
            $translation = $this->getTranslation($locale);
            if ($translation && $translation->getTitle()) {
                return $translation->getTitle();
            }
        }

        // Fallback : première traduction disponible
        $firstTranslation = $this->translations->first();
        return $firstTranslation ? $firstTranslation->getTitle() : null;
    }

    /**
     * Obtenir la description dans la langue courante ou une langue de fallback
     */
    public function getDescription(?string $locale = null): ?string
    {
        $locale = $locale ?? $this->currentLocale;
        
        if ($locale) {
            $translation = $this->getTranslation($locale);
            if ($translation && $translation->getDescription()) {
                return $translation->getDescription();
            }
        }

        // Fallback : première traduction disponible
        $firstTranslation = $this->translations->first();
        return $firstTranslation ? $firstTranslation->getDescription() : null;
    }

    /**
     * Obtenir le contenu dans la langue courante ou une langue de fallback
     */
    public function getContent(?string $locale = null): ?string
    {
        $locale = $locale ?? $this->currentLocale;
        
        if ($locale) {
            $translation = $this->getTranslation($locale);
            if ($translation && $translation->getContent()) {
                return $translation->getContent();
            }
        }

        // Fallback : première traduction disponible
        $firstTranslation = $this->translations->first();
        return $firstTranslation ? $firstTranslation->getContent() : null;
    }

    /**
     * Obtenir le meta title dans la langue courante ou une langue de fallback
     */
    public function getMetaTitle(?string $locale = null): ?string
    {
        $locale = $locale ?? $this->currentLocale;
        
        if ($locale) {
            $translation = $this->getTranslation($locale);
            if ($translation && $translation->getMetaTitle()) {
                return $translation->getMetaTitle();
            }
        }

        // Fallback : utiliser le titre
        return $this->getTitle($locale);
    }

    /**
     * Obtenir la meta description dans la langue courante ou une langue de fallback
     */
    public function getMetaDescription(?string $locale = null): ?string
    {
        $locale = $locale ?? $this->currentLocale;
        
        if ($locale) {
            $translation = $this->getTranslation($locale);
            if ($translation && $translation->getMetaDescription()) {
                return $translation->getMetaDescription();
            }
        }

        // Fallback : utiliser la description
        return $this->getDescription($locale);
    }

    /**
     * Vérifier si le service a une traduction complète pour une langue
     */
    public function hasCompleteTranslation(string $locale): bool
    {
        $translation = $this->getTranslation($locale);
        return $translation && $translation->isComplete();
    }

    /**
     * Obtenir les statistiques de traduction pour ce service
     */
    public function getTranslationStats(): array
    {
        $stats = [];
        
        foreach ($this->translations as $translation) {
            $locale = $translation->getLanguage()->getCode();
            $stats[$locale] = [
                'completion' => $translation->getCompletionPercentage(),
                'isComplete' => $translation->isComplete(),
                'language' => $translation->getLanguage(),
                'lastUpdate' => $translation->getUpdatedAt()
            ];
        }

        return $stats;
    }

    public function __toString(): string
    {
        return $this->getTitle() ?? $this->slug ?? 'Service #' . $this->id;
    }
}
