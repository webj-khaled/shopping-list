<?php

namespace App\Repository;

use App\Entity\AppUser;
use App\Entity\ShoppingList;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Query helper for shopping-list dashboard data.
 *
 * @extends ServiceEntityRepository<ShoppingList>
 */
class ShoppingListRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShoppingList::class);
    }

    /**
     * Loads the list cards with owner, edit permission, and item progress counts.
     *
     * @return array<int, array{id: int, name: string, coverImage: string, ownerUsername: string, isPublic: bool, canEdit: bool, itemsTotal: int, itemsChecked: int}>
     */
    public function findDashboardSummaries(
        ?AppUser $currentUser = null,
        string $publicUsername = 'public',
        string $listFilter = 'all',
        string $searchQuery = '',
    ): array {
        // Aggregate the item counts in SQL so the dashboard avoids per-list queries.
        $queryBuilder = $this->createQueryBuilder('shoppingList')
            ->select('shoppingList.id AS id')
            ->addSelect('shoppingList.name AS name')
            ->addSelect('shoppingList.coverImage AS coverImage')
            ->addSelect('owner.id AS ownerId')
            ->addSelect('owner.username AS ownerUsername')
            ->addSelect('COUNT(shoppingItem.id) AS itemsTotal')
            ->addSelect('SUM(CASE WHEN shoppingItem.checked = true THEN 1 ELSE 0 END) AS itemsChecked')
            ->innerJoin('shoppingList.owner', 'owner')
            ->leftJoin('shoppingList.items', 'shoppingItem')
            ->groupBy('shoppingList.id')
            ->addGroupBy('shoppingList.name')
            ->addGroupBy('shoppingList.coverImage')
            ->addGroupBy('owner.id')
            ->addGroupBy('owner.username')
            ->orderBy('shoppingList.updatedAt', 'DESC');

        if ($listFilter === 'mine' && $currentUser !== null) {
            $queryBuilder
                ->andWhere('owner.id = :currentUserId')
                ->setParameter('currentUserId', $currentUser->getId());
        } elseif ($listFilter === 'public') {
            $queryBuilder
                ->andWhere('owner.username = :publicUsername')
                ->setParameter('publicUsername', $publicUsername);
        }

        if ($searchQuery !== '') {
            $queryBuilder
                ->andWhere('LOWER(shoppingList.name) LIKE :searchQuery')
                ->setParameter('searchQuery', '%'.strtolower($searchQuery).'%');
        }

        return array_map(
            static function (array $list) use ($currentUser, $publicUsername): array {
                $ownerId = (int) $list['ownerId'];
                $isPublic = $list['ownerUsername'] === $publicUsername;

                // Doctrine array results are scalar strings on some drivers, so cast them here.
                return [
                    'id' => (int) $list['id'],
                    'name' => $list['name'],
                    'coverImage' => $list['coverImage'],
                    'ownerUsername' => $list['ownerUsername'],
                    'isPublic' => $isPublic,
                    'canEdit' => $isPublic || $currentUser?->getId() === $ownerId,
                    'itemsTotal' => (int) $list['itemsTotal'],
                    'itemsChecked' => (int) $list['itemsChecked'],
                ];
            },
            $queryBuilder->getQuery()->getArrayResult(),
        );
    }
}
