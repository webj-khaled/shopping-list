<?php

namespace App\Entity;

use App\Repository\AppUserRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Represents one optional dashboard username.
 */
#[ORM\Entity(repositoryClass: AppUserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\HasLifecycleCallbacks]
class AppUser implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    private string $username;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    /**
     * Shopping lists owned by this username.
     *
     * @var Collection<int, ShoppingList>
     */
    #[ORM\OneToMany(targetEntity: ShoppingList::class, mappedBy: 'owner', orphanRemoval: true)]
    private Collection $shoppingLists;

    public function __construct(string $username)
    {
        $this->username = $username;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->shoppingLists = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        $this->updatedAt = new DateTimeImmutable();

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        // No password or temporary secret is stored in this demo user model.
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
     * @return Collection<int, ShoppingList>
     */
    public function getShoppingLists(): Collection
    {
        return $this->shoppingLists;
    }

    public function addShoppingList(ShoppingList $shoppingList): self
    {
        if (!$this->shoppingLists->contains($shoppingList)) {
            // Keep both sides of the Doctrine relationship in sync.
            $this->shoppingLists->add($shoppingList);
            $shoppingList->setOwner($this);
        }

        return $this;
    }

    public function removeShoppingList(ShoppingList $shoppingList): self
    {
        $this->shoppingLists->removeElement($shoppingList);

        return $this;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        // Doctrine calls this before updates so timestamps stay current.
        $this->updatedAt = new DateTimeImmutable();
    }
}
