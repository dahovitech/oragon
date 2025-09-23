<?php

namespace App\Entity;

use App\Repository\BlogTranslationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BlogTranslationRepository::class)]
#[ORM\Table(name: 'blog_translations')]
#[ORM\UniqueConstraint(name: 'UNIQ_BLOG_LANGUAGE', columns: ['blog_id', 'language_id'])]
class BlogTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Blog::class, inversedBy: 'translations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Blog $blog = null;

    #[ORM\ManyToOne(targetEntity: Language::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Language $language = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $excerpt = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $metaTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $metaDescription = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBlog(): ?Blog
    {
        return $this->blog;
    }

    public function setBlog(?Blog $blog): static
    {
        $this->blog = $blog;
        return $this;
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
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

    public function getExcerpt(): ?string
    {
        return $this->excerpt;
    }

    public function setExcerpt(?string $excerpt): static
    {
        $this->excerpt = $excerpt;
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;
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
     * Check if translation is complete
     */
    public function isComplete(): bool
    {
        return !empty($this->title) && !empty($this->content) && !empty($this->excerpt);
    }

    /**
     * Check if translation is partial (has some content)
     */
    public function isPartial(): bool
    {
        return !empty($this->title) || !empty($this->content) || !empty($this->excerpt);
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercentage(): int
    {
        $fields = [
            !empty($this->title),
            !empty($this->content),
            !empty($this->excerpt),
            !empty($this->metaTitle),
            !empty($this->metaDescription),
            !empty($this->slug)
        ];
        
        $completed = array_sum($fields);
        return (int) round(($completed / count($fields)) * 100);
    }

    /**
     * Get content excerpt from content if excerpt is not set
     */
    public function getAutoExcerpt(int $length = 200): string
    {
        $excerpt = $this->excerpt;
        
        if (empty($excerpt) && !empty($this->content)) {
            $content = strip_tags($this->content);
            $excerpt = strlen($content) > $length 
                ? substr($content, 0, $length) . '...'
                : $content;
        }
        
        return $excerpt ?? '';
    }

    /**
     * Get reading time estimate (based on content)
     */
    public function getReadingTime(): int
    {
        if (empty($this->content)) {
            return 0;
        }
        
        $wordCount = str_word_count(strip_tags($this->content));
        return max(1, (int) ceil($wordCount / 200)); // Assuming 200 words per minute
    }

    /**
     * Auto-generate excerpt from content if not provided
     */
    public function autoGenerateExcerpt(int $length = 200): static
    {
        if (empty($this->excerpt) && !empty($this->content)) {
            $content = strip_tags($this->content);
            $this->excerpt = strlen($content) > $length 
                ? substr($content, 0, $length) . '...'
                : $content;
        }
        
        return $this;
    }

    /**
     * Auto-generate meta title from title if not provided
     */
    public function autoGenerateMetaTitle(): static
    {
        if (empty($this->metaTitle) && !empty($this->title)) {
            $this->metaTitle = strlen($this->title) > 60 
                ? substr($this->title, 0, 57) . '...'
                : $this->title;
        }
        
        return $this;
    }

    /**
     * Auto-generate meta description from excerpt if not provided
     */
    public function autoGenerateMetaDescription(): static
    {
        if (empty($this->metaDescription)) {
            $source = $this->excerpt ?: strip_tags($this->content ?? '');
            if (!empty($source)) {
                $this->metaDescription = strlen($source) > 160 
                    ? substr($source, 0, 157) . '...'
                    : $source;
            }
        }
        
        return $this;
    }

    /**
     * Auto-generate all SEO fields
     */
    public function autoGenerateSeoFields(): static
    {
        $this->autoGenerateExcerpt();
        $this->autoGenerateMetaTitle();
        $this->autoGenerateMetaDescription();
        
        return $this;
    }

    public function __toString(): string
    {
        return $this->title . ' (' . $this->language?->getCode() . ')';
    }
}