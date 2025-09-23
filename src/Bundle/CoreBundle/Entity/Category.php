<?php

namespace App\Bundle\CoreBundle\Entity;

use App\Bundle\CoreBundle\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'core_categories')]
#[ORM\HasLifecycleCallbacks]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\Regex(pattern: '/^[a-z0-9\-]+$/', message: 'Le slug ne peut contenir que des lettres minuscules, chiffres et tirets')]
    private ?string $slug = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?self $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'name' => 'ASC'])]
    private Collection $children;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['blog', 'product', 'service', 'page', 'media'])]
    private ?string $type = null;

    #[ORM\Column(length: 7, nullable: true)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'La couleur doit être au format hexadécimal (#RRGGBB)')]
    private ?string $color = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->children = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }
        return $this;
    }

    public function removeChild(self $child): static
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): static
    {
        $this->color = $color;
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
     * Get the full path of the category (parent > child > subchild)
     */
    public function getFullPath(): string
    {
        $path = [$this->name];
        $parent = $this->parent;
        
        while ($parent) {
            array_unshift($path, $parent->getName());
            $parent = $parent->getParent();
        }
        
        return implode(' > ', $path);
    }

    /**
     * Get all ancestors (parent, grandparent, etc.)
     */
    public function getAncestors(): array
    {
        $ancestors = [];
        $parent = $this->parent;
        
        while ($parent) {
            $ancestors[] = $parent;
            $parent = $parent->getParent();
        }
        
        return array_reverse($ancestors);
    }

    /**
     * Get all descendants (children, grandchildren, etc.)
     */
    public function getDescendants(): array
    {
        $descendants = [];
        
        foreach ($this->children as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $child->getDescendants());
        }
        
        return $descendants;
    }

    /**
     * Check if this category is a root category
     */
    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    /**
     * Check if this category is a leaf category (has no children)
     */
    public function isLeaf(): bool
    {
        return $this->children->isEmpty();
    }

    /**
     * Get the depth level of this category
     */
    public function getLevel(): int
    {
        $level = 0;
        $parent = $this->parent;
        
        while ($parent) {
            $level++;
            $parent = $parent->getParent();
        }
        
        return $level;
    }

    public function __toString(): string
    {
        return $this->name ?? 'Category #' . $this->id;
    }
}