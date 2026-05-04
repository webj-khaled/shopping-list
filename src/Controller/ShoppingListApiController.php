<?php

namespace App\Controller;

use App\Entity\AppUser;
use App\Entity\ShoppingItem;
use App\Entity\ShoppingList;
use App\Repository\AppUserRepository;
use App\Repository\ShoppingItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * JSON API for the assignment list/item endpoints.
 */
final class ShoppingListApiController extends AbstractController
{
    private const PUBLIC_USERNAME = 'public';
    private const COVER_GRADIENTS = [
        'gradient:linear-gradient(135deg,#14b8a6,#f97316),linear-gradient(45deg,#17202a,#7c3aed)',
        'gradient:linear-gradient(135deg,#ef4444,#facc15),linear-gradient(45deg,#111827,#0f766e)',
        'gradient:linear-gradient(135deg,#2563eb,#22c55e),linear-gradient(45deg,#17202a,#be123c)',
        'gradient:linear-gradient(135deg,#7c3aed,#06b6d4),linear-gradient(45deg,#111827,#f97316)',
        'gradient:linear-gradient(135deg,#db2777,#84cc16),linear-gradient(45deg,#17202a,#0369a1)',
        'gradient:linear-gradient(135deg,#ea580c,#0891b2),linear-gradient(45deg,#111827,#65a30d)',
    ];

    #[Route('/lists', name: 'api_lists_create', methods: ['POST'])]
    public function createList(
        Request $request,
        AppUserRepository $users,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        // API endpoints accept JSON bodies and regular form-encoded requests.
        $payload = $this->readPayload($request);

        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $name = $this->normalizeText($payload['name'] ?? null, 120);

        if ($name === null) {
            return $this->apiError('List names must be 1-120 characters.', Response::HTTP_BAD_REQUEST);
        }

        // Anonymous API calls create public lists.
        $currentUser = $this->getCurrentUser($request, $users);
        $visibility = (string) ($payload['visibility'] ?? 'private');
        $isPublicList = $currentUser === null || $visibility === 'public';
        $owner = $isPublicList ? $this->getPublicUser($users, $entityManager) : $currentUser;

        $shoppingList = new ShoppingList($name, $this->getRandomCoverGradient(), $owner);

        $entityManager->persist($shoppingList);
        $entityManager->flush();

        return $this->json(
            ['list' => $this->serializeList($shoppingList, true)],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/lists/{id<\d+>}/item', name: 'api_items_create', methods: ['POST'])]
    public function createItem(
        Request $request,
        ShoppingList $shoppingList,
        AppUserRepository $users,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        // Apply the same public/private edit rule used by the dashboard.
        $currentUser = $this->getCurrentUser($request, $users);

        if (!$this->canEditShoppingList($shoppingList, $currentUser)) {
            return $this->apiError('Only the owner can edit a private list.', Response::HTTP_FORBIDDEN);
        }

        $payload = $this->readPayload($request);

        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $itemData = $this->normalizeItemPayload($payload, requireName: true);

        if ($itemData instanceof JsonResponse) {
            return $itemData;
        }

        $item = new ShoppingItem($itemData['name'], $itemData['quantity'], $shoppingList);
        $item->setUnit($itemData['unit']);
        $item->setCategory($itemData['category']);
        $item->setChecked($itemData['checked']);

        $entityManager->persist($item);
        $entityManager->flush();

        return $this->json(
            ['item' => $this->serializeItem($item)],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/lists/{id<\d+>}/items', name: 'api_items_index', methods: ['GET'])]
    public function listItems(
        ShoppingList $shoppingList,
        ShoppingItemRepository $items,
        Request $request,
        AppUserRepository $users,
    ): JsonResponse {
        $currentUser = $this->getCurrentUser($request, $users);
        // Return items in their original creation order for predictable API output.
        $shoppingItems = $items->findBy(['shoppingList' => $shoppingList], ['createdAt' => 'ASC', 'id' => 'ASC']);

        return $this->json([
            'list' => $this->serializeList($shoppingList, $this->canEditShoppingList($shoppingList, $currentUser)),
            'items' => array_map($this->serializeItem(...), $shoppingItems),
        ]);
    }

    #[Route('/lists/{id<\d+>}/items/{itemId<\d+>}', name: 'api_items_show', methods: ['GET'])]
    public function showItem(
        ShoppingList $shoppingList,
        int $itemId,
        ShoppingItemRepository $items,
    ): JsonResponse {
        // Verify the item belongs to the list from the URL.
        $item = $this->findItemInList($shoppingList, $itemId, $items);

        if ($item === null) {
            return $this->apiError('Item not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->json(['item' => $this->serializeItem($item)]);
    }

    #[Route('/lists/{id<\d+>}/items/{itemId<\d+>}', name: 'api_items_update', methods: ['PUT'])]
    public function updateItem(
        Request $request,
        ShoppingList $shoppingList,
        int $itemId,
        ShoppingItemRepository $items,
        AppUserRepository $users,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        // Verify the item belongs to the list from the URL before editing.
        $item = $this->findItemInList($shoppingList, $itemId, $items);

        if ($item === null) {
            return $this->apiError('Item not found.', Response::HTTP_NOT_FOUND);
        }

        // Deleting a private list requires the active session user to own it.
        $currentUser = $this->getCurrentUser($request, $users);

        if (!$this->canEditShoppingList($shoppingList, $currentUser)) {
            return $this->apiError('Only the owner can edit a private list.', Response::HTTP_FORBIDDEN);
        }

        $payload = $this->readPayload($request);

        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $itemData = $this->normalizeItemPayload($payload, requireName: false);

        if ($itemData instanceof JsonResponse) {
            return $itemData;
        }

        if (array_key_exists('name', $itemData)) {
            $item->setName($itemData['name']);
        }

        if (array_key_exists('quantity', $itemData)) {
            $item->setQuantity($itemData['quantity']);
        }

        if (array_key_exists('unit', $itemData)) {
            $item->setUnit($itemData['unit']);
        }

        if (array_key_exists('category', $itemData)) {
            $item->setCategory($itemData['category']);
        }

        if (array_key_exists('checked', $itemData)) {
            $item->setChecked($itemData['checked']);
        }

        $entityManager->flush();

        return $this->json(['item' => $this->serializeItem($item)]);
    }

    #[Route('/lists/{id<\d+>}', name: 'api_lists_delete', methods: ['DELETE'])]
    public function deleteList(
        Request $request,
        ShoppingList $shoppingList,
        AppUserRepository $users,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $currentUser = $this->getCurrentUser($request, $users);

        if (!$this->canEditShoppingList($shoppingList, $currentUser)) {
            return $this->apiError('Only the owner can delete a private list.', Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($shoppingList);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/lists/{id<\d+>}/items/{itemId<\d+>}', name: 'api_items_delete', methods: ['DELETE'])]
    public function deleteItem(
        Request $request,
        ShoppingList $shoppingList,
        int $itemId,
        ShoppingItemRepository $items,
        AppUserRepository $users,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        // Verify the item belongs to the list from the URL before deleting.
        $item = $this->findItemInList($shoppingList, $itemId, $items);

        if ($item === null) {
            return $this->apiError('Item not found.', Response::HTTP_NOT_FOUND);
        }

        $currentUser = $this->getCurrentUser($request, $users);

        if (!$this->canEditShoppingList($shoppingList, $currentUser)) {
            return $this->apiError('Only the owner can edit a private list.', Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($item);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function readPayload(Request $request): array|JsonResponse
    {
        // PUT form data is not automatically parsed by PHP, so parse it manually.
        $contentType = $request->headers->get('content-type', '');

        if (str_contains($contentType, 'json')) {
            try {
                return $request->toArray();
            } catch (JsonException) {
                return $this->apiError('Invalid JSON request body.', Response::HTTP_BAD_REQUEST);
            }
        }

        if ($request->isMethod('PUT')) {
            parse_str($request->getContent(), $payload);

            return $payload;
        }

        return $request->request->all();
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    private function normalizeItemPayload(array $payload, bool $requireName): array|JsonResponse
    {
        // Used for both create and update: create requires all core fields, update allows partial fields.
        $itemData = [];

        if ($requireName || array_key_exists('name', $payload)) {
            $name = $this->normalizeText($payload['name'] ?? null, 180);

            if ($name === null) {
                return $this->apiError('Item names must be 1-180 characters.', Response::HTTP_BAD_REQUEST);
            }

            $itemData['name'] = $name;
        }

        if ($requireName || array_key_exists('quantity', $payload)) {
            $quantity = $this->normalizeQuantity($payload['quantity'] ?? 1);

            if ($quantity === null) {
                return $this->apiError('Use a quantity greater than 0.', Response::HTTP_BAD_REQUEST);
            }

            $itemData['quantity'] = $quantity;
        }

        foreach (['unit' => 40, 'category' => 80] as $field => $maxLength) {
            if (!array_key_exists($field, $payload)) {
                if ($requireName) {
                    $itemData[$field] = null;
                }

                continue;
            }

            $value = $this->normalizeOptionalText($payload[$field], $maxLength);

            if ($value === false) {
                return $this->apiError(sprintf('%s must be %d characters or fewer.', ucfirst($field), $maxLength), Response::HTTP_BAD_REQUEST);
            }

            $itemData[$field] = $value;
        }

        if ($requireName || array_key_exists('checked', $payload)) {
            $itemData['checked'] = $this->normalizeBoolean($payload['checked'] ?? false);
        }

        return $itemData;
    }

    private function normalizeText(mixed $value, int $maxLength): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '' || strlen($text) > $maxLength) {
            return null;
        }

        return $text;
    }

    private function normalizeOptionalText(mixed $value, int $maxLength): string|false|null
    {
        if ($value === null) {
            return null;
        }

        if (!is_scalar($value)) {
            return false;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        if (strlen($text) > $maxLength) {
            return false;
        }

        return $text;
    }

    private function normalizeQuantity(mixed $quantity): ?float
    {
        // Accept both "1.5" and "1,5" as decimal quantities.
        if (!is_scalar($quantity)) {
            return null;
        }

        $normalizedQuantity = str_replace(',', '.', trim((string) $quantity));

        if (!is_numeric($normalizedQuantity)) {
            return null;
        }

        $quantityValue = (float) $normalizedQuantity;

        if ($quantityValue <= 0) {
            return null;
        }

        return $quantityValue;
    }

    private function normalizeBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        return false;
    }

    private function findItemInList(
        ShoppingList $shoppingList,
        int $itemId,
        ShoppingItemRepository $items,
    ): ?ShoppingItem {
        $item = $items->find($itemId);

        if ($item === null || $item->getShoppingList()->getId() !== $shoppingList->getId()) {
            return null;
        }

        return $item;
    }

    private function getCurrentUser(Request $request, AppUserRepository $users): ?AppUser
    {
        // The API reuses the same optional username session as the dashboard.
        if (!$request->hasSession()) {
            return null;
        }

        $userId = $request->getSession()->get('app_user_id');

        if ($userId === null) {
            return null;
        }

        return $users->find($userId);
    }

    private function getPublicUser(
        AppUserRepository $users,
        EntityManagerInterface $entityManager,
    ): AppUser {
        // The public pseudo-user owns all public lists.
        $publicUser = $users->findOneBy(['username' => self::PUBLIC_USERNAME]);

        if ($publicUser !== null) {
            return $publicUser;
        }

        $publicUser = new AppUser(self::PUBLIC_USERNAME);
        $entityManager->persist($publicUser);

        return $publicUser;
    }

    private function canEditShoppingList(ShoppingList $shoppingList, ?AppUser $currentUser): bool
    {
        // Public lists are shared. Private lists belong to one username.
        $owner = $shoppingList->getOwner();

        if ($owner->getUsername() === self::PUBLIC_USERNAME) {
            return true;
        }

        return $currentUser !== null && $owner->getId() === $currentUser->getId();
    }

    private function serializeList(ShoppingList $shoppingList, bool $canEdit): array
    {
        // Keep API list payloads small and do not expose owner usernames.
        $isPublic = $shoppingList->getOwner()->getUsername() === self::PUBLIC_USERNAME;

        return [
            'id' => $shoppingList->getId(),
            'name' => $shoppingList->getName(),
            'visibility' => $isPublic ? 'public' : 'private',
            'canEdit' => $canEdit,
            'coverImage' => $shoppingList->getCoverImage(),
            'createdAt' => $shoppingList->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $shoppingList->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function serializeItem(ShoppingItem $item): array
    {
        return [
            'id' => $item->getId(),
            'listId' => $item->getShoppingList()->getId(),
            'name' => $item->getName(),
            'quantity' => $item->getQuantity(),
            'unit' => $item->getUnit(),
            'category' => $item->getCategory(),
            'checked' => $item->isChecked(),
            'createdAt' => $item->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $item->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function getRandomCoverGradient(): string
    {
        return self::COVER_GRADIENTS[array_rand(self::COVER_GRADIENTS)];
    }

    private function apiError(string $message, int $status): JsonResponse
    {
        return $this->json(['error' => $message], $status);
    }
}
