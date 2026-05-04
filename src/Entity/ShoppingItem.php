<?php

namespace App\Entity;

use App\Repository\ShoppingItemRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents one item inside a shopping list.
 */
#[ORM\Entity(repositoryClass: ShoppingItemRepository::class)]
#[ORM\Table(name: 'shopping_item')]
#[ORM\HasLifecycleCallbacks]
class ShoppingItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $name;

    #[ORM\Column]
    private float $quantity;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $category = null;

    #[ORM\Column]
    private bool $checked = false;

    // Deleting the parent list deletes its items through the database relation.
    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ShoppingList $shoppingList;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(string $name, float $quantity, ShoppingList $shoppingList)
    {
        $this->name = $name;
        $this->quantity = $quantity;
        $this->shoppingList = $shoppingList;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function setQuantity(float $quantity): self
    {
        $this->quantity = $quantity;
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): self
    {
        $this->unit = $unit;
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }

    public function isChecked(): bool
    {
        return $this->checked;
    }

    public function setChecked(bool $checked): self
    {
        $this->checked = $checked;
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }

    public function getShoppingList(): ShoppingList
    {
        return $this->shoppingList;
    }

    public function setShoppingList(ShoppingList $shoppingList): self
    {
        $this->shoppingList = $shoppingList;
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        // Doctrine calls this before updates so item changes are timestamped.
        $this->updatedAt = new DateTimeImmutable();
    }
}
