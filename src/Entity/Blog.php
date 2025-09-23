<?php

namespace App\Entity;

use App\Repository\BlogRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BlogRepository::class)]
#[ORM\Table(name: 'blogs')]
#[ORM\HasLifecycleCallbacks]
class Blog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isPublished = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'blog', targetEntity: BlogTranslation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $translations;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $featuredImage = null;

    #[ORM\Column(type: 'integer')]
    private int $viewCount = 0;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;
        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;
        
        // Auto-set publishedAt when publishing
        if ($isPublished && !$this->publishedAt) {
            $this->publishedAt = new \DateTimeImmutable();
        } elseif (!$isPublished) {
            $this->publishedAt = null;
        }
        
        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;
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

    public function getFeaturedImage(): ?string
    {
        return $this->featuredImage;
    }

    public function setFeaturedImage(?string $featuredImage): static
    {
        $this->featuredImage = $featuredImage;
        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function setViewCount(int $viewCount): static
    {
        $this->viewCount = $viewCount;
        return $this;
    }

    public function incrementViewCount(): static
    {
        $this->viewCount++;
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

    /**
     * @return Collection<int, BlogTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(BlogTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setBlog($this);
        }

        return $this;
    }

    public function removeTranslation(BlogTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            if ($translation->getBlog() === $this) {
                $translation->setBlog(null);
            }
        }

        return $this;
    }

    /**
     * Get translation for a specific language
     */
    public function getTranslation(?string $languageCode = null): ?BlogTranslation
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
    public function getTranslationWithFallback(string $languageCode, string $fallbackLanguageCode = 'fr'): ?BlogTranslation
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
        return $translation ? $translation->getTitle() : 'Untitled Blog Post';
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
     * Get excerpt for a specific language with fallback
     */
    public function getExcerpt(string $languageCode = 'fr', string $fallbackLanguageCode = 'fr'): string
    {
        $translation = $this->getTranslationWithFallback($languageCode, $fallbackLanguageCode);
        return $translation ? ($translation->getExcerpt() ?? '') : '';
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
     * Check if blog has translation for a specific language
     */
    public function hasTranslation(string $languageCode): bool
    {
        return $this->getTranslation($languageCode) !== null;
    }

    /**
     * Check if blog is published and published date is in the past
     */
    public function isVisible(): bool
    {
        return $this->isPublished && 
               $this->publishedAt !== null && 
               $this->publishedAt <= new \DateTimeImmutable();
    }

    /**
     * Publish the blog post
     */
    public function publish(): static
    {
        $this->setIsPublished(true);
        if (!$this->publishedAt) {
            $this->publishedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    /**
     * Unpublish the blog post
     */
    public function unpublish(): static
    {
        $this->setIsPublished(false);
        $this->publishedAt = null;
        return $this;
    }

    public function __toString(): string
    {
        return $this->getTitle();
    }
}