<?php

namespace App\Bundle\CoreBundle\Entity;

use App\Bundle\MediaBundle\Entity\Media;
use App\Bundle\CoreBundle\Repository\PageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PageRepository::class)]
#[ORM\Table(name: 'core_pages')]
#[ORM\HasLifecycleCallbacks]
class Page
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-z0-9\-]+$/', message: 'Le slug ne peut contenir que des lettres minuscules, chiffres et tirets')]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $metaTitle = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $metaDescription = null;

    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Media $featuredImage = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $isHomepage = false;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $template = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getTitle(): ?string
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

    public function getFeaturedImage(): ?Media
    {
        return $this->featuredImage;
    }

    public function setFeaturedImage(?Media $featuredImage): static
    {
        $this->featuredImage = $featuredImage;
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

    public function isHomepage(): bool
    {
        return $this->isHomepage;
    }

    public function setIsHomepage(bool $isHomepage): static
    {
        $this->isHomepage = $isHomepage;
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

    public function getTemplate(): ?string
    {
        return $this->template;
    }

    public function setTemplate(?string $template): static
    {
        $this->template = $template;
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

    public function getExcerpt(int $length = 150): string
    {
        if (!$this->content) {
            return '';
        }

        $text = strip_tags($this->content);
        if (strlen($text) <= $length) {
            return $text;
        }

        return substr($text, 0, $length) . '...';
    }

    public function __toString(): string
    {
        return $this->title ?? 'Page #' . $this->id;
    }
}