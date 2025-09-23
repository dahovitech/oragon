<?php

namespace App\Entity;

use App\Repository\LanguageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LanguageRepository::class)]
#[ORM\Table(name: 'languages')]
class Language
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 10, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 10)]
    private string $code;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private string $name;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    private string $nativeName;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'boolean')]
    private bool $isDefault = false;

    #[ORM\Column(type: 'integer')]
    private int $sortOrder = 0;

    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    private ?string $currency = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $dateFormat = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isRtl = false;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $region = null;

    public function __construct()
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getNativeName(): string
    {
        return $this->nativeName;
    }

    public function setNativeName(string $nativeName): static
    {
        $this->nativeName = $nativeName;
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

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;
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

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getDateFormat(): ?string
    {
        return $this->dateFormat;
    }

    public function setDateFormat(?string $dateFormat): static
    {
        $this->dateFormat = $dateFormat;
        return $this;
    }

    public function isRtl(): bool
    {
        return $this->isRtl;
    }

    public function setIsRtl(bool $isRtl): static
    {
        $this->isRtl = $isRtl;
        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): static
    {
        $this->region = $region;
        return $this;
    }

    /**
     * Get text direction (rtl or ltr)
     */
    public function getTextDirection(): string
    {
        return $this->isRtl ? 'rtl' : 'ltr';
    }

    /**
     * Get locale for internationalization (combines code and region)
     */
    public function getLocale(): string
    {
        if ($this->region) {
            return $this->code . '_' . strtoupper($this->region);
        }
        return $this->code;
    }

    /**
     * Get full display name (name + native name)
     */
    public function getFullDisplayName(): string
    {
        if ($this->name !== $this->nativeName) {
            return $this->name . ' (' . $this->nativeName . ')';
        }
        return $this->name;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
