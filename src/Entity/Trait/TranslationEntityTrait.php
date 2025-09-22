<?php

namespace App\Entity\Trait;

use App\Entity\Language;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Trait pour les entités de traduction
 * Fournit les propriétés et méthodes de base pour toutes les traductions
 */
trait TranslationEntityTrait
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Language::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?Language $language = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLanguage(): ?Language
    {
        return $this->language;
    }

    public function setLanguage(?Language $language): static
    {
        $this->language = $language;
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
     * Vérifier si la traduction est complète
     * Doit être implémentée dans chaque entité de traduction
     */
    abstract public function isComplete(): bool;

    /**
     * Obtenir le pourcentage de complétion de la traduction
     * Doit être implémentée dans chaque entité de traduction
     */
    abstract public function getCompletionPercentage(): int;

    /**
     * Obtenir l'entité principale traduite
     * Doit être implémentée dans chaque entité de traduction
     */
    abstract public function getTranslatable();

    /**
     * Définir l'entité principale traduite
     * Doit être implémentée dans chaque entité de traduction
     */
    abstract public function setTranslatable($translatable): static;
}
