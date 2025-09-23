<?php

namespace App\Entity;

use App\Repository\ReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'reviews')]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 1, max: 5)]
    private int $rating = 5;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $comment = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isApproved = false;

    #[ORM\Column(type: 'integer')]
    private int $helpfulCount = 0;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;
        return $this;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): static
    {
        $this->rating = $rating;
        return $this;
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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function isApproved(): bool
    {
        return $this->isApproved;
    }

    public function setIsApproved(bool $isApproved): static
    {
        $this->isApproved = $isApproved;
        return $this;
    }

    public function getHelpfulCount(): int
    {
        return $this->helpfulCount;
    }

    public function setHelpfulCount(int $helpfulCount): static
    {
        $this->helpfulCount = $helpfulCount;
        return $this;
    }

    public function incrementHelpfulCount(): static
    {
        $this->helpfulCount++;
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
     * Get rating as stars display
     */
    public function getStarsDisplay(): string
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }

    /**
     * Get author name for display
     */
    public function getAuthorName(): string
    {
        return $this->user ? $this->user->getFullName() : 'Anonymous';
    }

    /**
     * Check if review has content
     */
    public function hasContent(): bool
    {
        return !empty($this->title) || !empty($this->comment);
    }

    public function __toString(): string
    {
        return ($this->title ?? 'Review') . ' - ' . $this->rating . ' stars';
    }
}