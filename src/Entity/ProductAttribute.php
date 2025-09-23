<?php

namespace App\Entity;

use App\Repository\ProductAttributeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductAttributeRepository::class)]
#[ORM\Table(name: 'product_attributes')]
#[ORM\UniqueConstraint(name: 'UNIQ_PRODUCT_ATTRIBUTE_VALUE', columns: ['product_id', 'attribute_value_id'])]
class ProductAttribute
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'productAttributes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\ManyToOne(targetEntity: AttributeValue::class, inversedBy: 'productAttributes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?AttributeValue $attributeValue = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $customValue = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAttributeValue(): ?AttributeValue
    {
        return $this->attributeValue;
    }

    public function setAttributeValue(?AttributeValue $attributeValue): static
    {
        $this->attributeValue = $attributeValue;
        return $this;
    }

    public function getCustomValue(): ?string
    {
        return $this->customValue;
    }

    public function setCustomValue(?string $customValue): static
    {
        $this->customValue = $customValue;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Get the attribute associated with this product attribute
     */
    public function getAttribute(): ?Attribute
    {
        return $this->attributeValue?->getAttribute();
    }

    /**
     * Get display value (custom value or translated attribute value)
     */
    public function getDisplayValue(string $languageCode = 'fr'): string
    {
        if ($this->customValue !== null) {
            return $this->customValue;
        }

        return $this->attributeValue ? $this->attributeValue->getValue($languageCode) : '';
    }

    public function __toString(): string
    {
        return ($this->getAttribute()?->getName() ?? 'Attribute') . ': ' . $this->getDisplayValue();
    }
}
