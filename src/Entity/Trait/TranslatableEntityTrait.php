<?php

namespace App\Entity\Trait;

use App\Entity\Language;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trait pour les entités traduisibles
 * Fournit les méthodes de base pour la gestion des traductions
 */
trait TranslatableEntityTrait
{
    /**
     * @var Collection<int, mixed> Collection des traductions
     */
    #[ORM\OneToMany(mappedBy: 'translatable', targetEntity: self::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    protected Collection $translations;

    /**
     * @var string|null Langue courante pour les getters
     */
    protected ?string $currentLocale = null;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
    }

    /**
     * @return Collection<int, mixed>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    /**
     * Ajouter une traduction
     */
    public function addTranslation($translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setTranslatable($this);
        }

        return $this;
    }

    /**
     * Supprimer une traduction
     */
    public function removeTranslation($translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getTranslatable() === $this) {
                $translation->setTranslatable(null);
            }
        }

        return $this;
    }

    /**
     * Obtenir une traduction pour une langue donnée
     */
    public function getTranslation(string $locale)
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLanguage()->getCode() === $locale) {
                return $translation;
            }
        }

        return null;
    }

    /**
     * Obtenir une traduction pour une langue ou créer une nouvelle
     */
    public function getOrCreateTranslation(string $locale)
    {
        $translation = $this->getTranslation($locale);
        
        if (!$translation) {
            $translationClass = static::getTranslationClass();
            $translation = new $translationClass();
            
            // Ici, on assumera que la Language sera définie par le service
            $this->addTranslation($translation);
        }

        return $translation;
    }

    /**
     * Vérifier si une traduction existe pour une langue
     */
    public function hasTranslation(string $locale): bool
    {
        return $this->getTranslation($locale) !== null;
    }

    /**
     * Obtenir toutes les langues disponibles pour cette entité
     * @return array<string>
     */
    public function getAvailableLocales(): array
    {
        $locales = [];
        foreach ($this->translations as $translation) {
            $locales[] = $translation->getLanguage()->getCode();
        }

        return array_unique($locales);
    }

    /**
     * Définir la langue courante pour les getters
     */
    public function setCurrentLocale(?string $locale): static
    {
        $this->currentLocale = $locale;
        return $this;
    }

    /**
     * Obtenir la langue courante
     */
    public function getCurrentLocale(): ?string
    {
        return $this->currentLocale;
    }

    /**
     * Vérifier si l'entité est complètement traduite pour une langue
     */
    public function isTranslatedInto(string $locale): bool
    {
        $translation = $this->getTranslation($locale);
        
        if (!$translation) {
            return false;
        }

        return $translation->isComplete();
    }

    /**
     * Obtenir le pourcentage de complétion pour une langue
     */
    public function getTranslationCompletion(string $locale): int
    {
        $translation = $this->getTranslation($locale);
        
        if (!$translation) {
            return 0;
        }

        return $translation->getCompletionPercentage();
    }

    /**
     * Méthode abstraite pour obtenir la classe de traduction
     * Doit être implémentée dans chaque entité utilisant ce trait
     */
    abstract public static function getTranslationClass(): string;
}
