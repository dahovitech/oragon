<?php

namespace App\Entity;

use App\Repository\PageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PageRepository::class)]
#[ORM\Table(name: 'pages')]
#[ORM\HasLifecycleCallbacks]
class Page
{
    public const TYPE_ABOUT = 'about';
    public const TYPE_PRIVACY = 'privacy';
    public const TYPE_TERMS = 'terms';
    public const TYPE_CONTACT = 'contact';
    public const TYPE_FAQ = 'faq';
    public const TYPE_SHIPPING = 'shipping';
    public const TYPE_RETURNS = 'returns';
    public const TYPE_CUSTOM = 'custom';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [
        self::TYPE_ABOUT,
        self::TYPE_PRIVACY,
        self::TYPE_TERMS,
        self::TYPE_CONTACT,
        self::TYPE_FAQ,
        self::TYPE_SHIPPING,
        self::TYPE_RETURNS,
        self::TYPE_CUSTOM
    ])]
    private string $type;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'page', targetEntity: PageTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    public function __construct()
    {
        $this->translations = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, PageTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(PageTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setPage($this);
        }

        return $this;
    }

    public function removeTranslation(PageTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getPage() === $this) {
                $translation->setPage(null);
            }
        }

        return $this;
    }

    /**
     * Get translation for a specific language
     */
    public function getTranslation(?string $languageCode = null): ?PageTranslation
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

    /**
     * Get translation with fallback
     */
    public function getTranslationWithFallback(string $languageCode, string $fallbackLanguageCode = 'fr'): ?PageTranslation
    {
        $translation = $this->getTranslation($languageCode);
        
        if (!$translation) {
            $translation = $this->getTranslation($fallbackLanguageCode);
        }

        return $translation;
    }

    /**
     * Get title for a specific language with fallback
     */
    public function getTitle(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslationWithFallback($languageCode, $fallbackLanguageCode);
        return $translation ? $translation->getTitle() : 'Untitled Page';
    }

    /**
     * Get content for a specific language with fallback
     */
    public function getContent(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslationWithFallback($languageCode, $fallbackLanguageCode);
        return $translation ? ($translation->getContent() ?? '') : '';
    }

    /**
     * Get slug for a specific language with fallback
     */
    public function getSlug(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslationWithFallback($languageCode, $fallbackLanguageCode);
        return $translation ? ($translation->getSlug() ?? '') : '';
    }

    /**
     * Check if page has translation for a specific language
     */
    public function hasTranslation(string $languageCode): bool
    {
        return $this->getTranslation($languageCode) !== null;
    }

    /**
     * Get type label
     */
    public function getTypeLabel(): string
    {
        return match($this->type) {
            self::TYPE_ABOUT => 'About Us',
            self::TYPE_PRIVACY => 'Privacy Policy',
            self::TYPE_TERMS => 'Terms of Service',
            self::TYPE_CONTACT => 'Contact Us',
            self::TYPE_FAQ => 'FAQ',
            self::TYPE_SHIPPING => 'Shipping Info',
            self::TYPE_RETURNS => 'Returns Policy',
            self::TYPE_CUSTOM => 'Custom Page',
            default => 'Unknown'
        };
    }

    public static function getTypeChoices(): array
    {
        return [
            'About Us' => self::TYPE_ABOUT,
            'Privacy Policy' => self::TYPE_PRIVACY,
            'Terms of Service' => self::TYPE_TERMS,
            'Contact Us' => self::TYPE_CONTACT,
            'FAQ' => self::TYPE_FAQ,
            'Shipping Info' => self::TYPE_SHIPPING,
            'Returns Policy' => self::TYPE_RETURNS,
            'Custom Page' => self::TYPE_CUSTOM,
        ];
    }

    public function __toString(): string
    {
        return $this->getTitle();
    }
}