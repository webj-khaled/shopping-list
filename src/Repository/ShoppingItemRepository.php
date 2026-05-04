<?php

namespace App\Repository;

use App\Entity\ShoppingItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Query helper for shopping-list item data.
 *
 * @extends ServiceEntityRepository<ShoppingItem>
 */
class ShoppingItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShoppingItem::class);
    }

    /**
     * Loads all visible dashboard items in one query, grouped by list id.
     *
     * @param array<int, int> $listIds
     *
     * @return array<int, array<int, array{id: int, shoppingListId: int, name: string, quantity: float, unit: string|null, category: string|null, checked: bool}>>
     */
    public function findDashboardItemsByListIds(array $listIds): array
    {
        if ($listIds === []) {
            return [];
        }

        // Fetch all items for the current dashboard page without one query per list.
        $items = $this->createQueryBuilder('item')
            ->select('item.id AS id')
            ->addSelect('shoppingList.id AS shoppingListId')
            ->addSelect('item.name AS name')
            ->addSelect('item.quantity AS quantity')
            ->addSelect('item.unit AS unit')
            ->addSelect('item.category AS category')
            ->addSelect('item.checked AS checked')
            ->innerJoin('item.shoppingList', 'shoppingList')
            ->andWhere('shoppingList.id IN (:listIds)')
            ->setParameter('listIds', $listIds)
            ->orderBy('item.checked', 'ASC')
            ->addOrderBy('item.createdAt', 'ASC')
            ->addOrderBy('item.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $groupedItems = [];

        foreach ($items as $item) {
            // Grouping here matches the Twig shape: list id => list item rows.
            $listId = (int) $item['shoppingListId'];
            $groupedItems[$listId][] = [
                'id' => (int) $item['id'],
                'shoppingListId' => $listId,
                'name' => $item['name'],
                'quantity' => (float) $item['quantity'],
                'unit' => $item['unit'],
                'category' => $item['category'],
                'checked' => (bool) $item['checked'],
            ];
        }

        return $groupedItems;
    }
}
