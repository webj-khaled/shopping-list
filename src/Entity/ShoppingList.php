<?php

namespace App\Entity;

use App\Repository\ShoppingListRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Represents one shopping list with a cover and an owner.
 */
#[ORM\Entity(repositoryClass: ShoppingListRepository::class)]
#[ORM\Table(name: 'shopping_list')]
#[ORM\HasLifecycleCallbacks]
class ShoppingList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $coverImage;

    // The special "public" user owns public lists; other users own private lists.
    #[ORM\ManyToOne(inversedBy: 'shoppingLists')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AppUser $owner;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /**
     * Items are deleted automatically when their parent list is deleted.
     *
     * @var Collection<int, ShoppingItem>
     */
    #[ORM\OneToMany(targetEntity: ShoppingItem::class, mappedBy: 'shoppingList', orphanRemoval: true)]
    private Collection $items;

    public function __construct(string $name, string $coverImage, AppUser $owner)
    {
        $this->name = $name;
        $this->coverImage = $coverImage;
        $this->owner = $owner;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->items = new ArrayCollection();
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

    public function getCoverImage(): string
    {
        return $this->coverImage;
    }

    public function setCoverImage(string $coverImage): self
    {
        $this->coverImage = $coverImage;
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }

    public function getOwner(): AppUser
    {
        return $this->owner;
    }

    public function setOwner(AppUser $owner): self
    {
        $this->owner = $owner;
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

    /**
     * @return Collection<int, ShoppingItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(ShoppingItem $item): self
    {
        if (!$this->items->contains($item)) {
            // Keep both sides of the Doctrine relationship in sync.
            $this->items->add($item);
            $item->setShoppingList($this);
        }

        return $this;
    }

    public function removeItem(ShoppingItem $item): self
    {
        $this->items->removeElement($item);

        return $this;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        // Doctrine calls this before updates so list ordering can use updatedAt.
        $this->updatedAt = new DateTimeImmutable();
    }
}
