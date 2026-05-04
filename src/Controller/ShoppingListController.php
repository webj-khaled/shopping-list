<?php

namespace App\Controller;

use App\Entity\AppUser;
use App\Entity\ShoppingItem;
use App\Entity\ShoppingList;
use App\Repository\AppUserRepository;
use App\Repository\ShoppingItemRepository;
use App\Repository\ShoppingListRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Renders the dashboard and handles form-based list/item actions.
 */
final class ShoppingListController extends AbstractController
{
    private const PUBLIC_USERNAME = 'public';
    private const LIST_FILTERS = ['all', 'mine', 'public'];
    private const MAX_INITIAL_ITEMS = 12;
    private const MAX_COVER_IMAGE_SIZE = 2097152;
    private const COVER_IMAGE_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    private const COVER_IMAGE_EXTENSIONS = [
        'jpg' => 'jpg',
        'jpeg' => 'jpg',
        'png' => 'png',
        'webp' => 'webp',
        'gif' => 'gif',
    ];
    private const COVER_GRADIENTS = [
        'gradient:linear-gradient(135deg,#14b8a6,#f97316),linear-gradient(45deg,#17202a,#7c3aed)',
        'gradient:linear-gradient(135deg,#ef4444,#facc15),linear-gradient(45deg,#111827,#0f766e)',
        'gradient:linear-gradient(135deg,#2563eb,#22c55e),linear-gradient(45deg,#17202a,#be123c)',
        'gradient:linear-gradient(135deg,#7c3aed,#06b6d4),linear-gradient(45deg,#111827,#f97316)',
        'gradient:linear-gradient(135deg,#db2777,#84cc16),linear-gradient(45deg,#17202a,#0369a1)',
        'gradient:linear-gradient(135deg,#ea580c,#0891b2),linear-gradient(45deg,#111827,#65a30d)',
    ];

    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    #[Route('/shopping/list', name: 'app_shopping_list', methods: ['GET'])]
    public function index(
        Request $request,
        AppUserRepository $users,
        ShoppingListRepository $shoppingLists,
        ShoppingItemRepository $shoppingItems,
    ): Response {
        // Build the dashboard model from the current optional session user.
        $currentUser = $this->getCurrentUser($request, $users);
        $activeListFilter = $this->getActiveListFilter($request, $currentUser);
        $listSearchQuery = $this->getListSearchQuery($request);
        $listFilterOptions = $this->buildListFilterOptions($activeListFilter, $listSearchQuery);
        $lists = $shoppingLists->findDashboardSummaries($currentUser, self::PUBLIC_USERNAME, $activeListFilter, $listSearchQuery);
        $itemsByListId = $shoppingItems->findDashboardItemsByListIds(array_column($lists, 'id'));

        // Attach each list's item rows to the summary array used by Twig.
        foreach ($lists as &$list) {
            $list['items'] = $itemsByListId[$list['id']] ?? [];
        }

        unset($list);

        return $this->render('shopping_list/index.html.twig', [
            'activeListFilter' => $activeListFilter,
            'currentUsername' => $currentUser?->getUsername(),
            'dashboardQueryParameters' => $this->getDashboardQueryParameters($request),
            'listFilterOptions' => $currentUser === null ? [] : $listFilterOptions,
            'listSearchQuery' => $listSearchQuery,
            'lists' => $lists,
        ]);
    }

    #[Route('/shopping/list', name: 'app_shopping_list_create', methods: ['POST'])]
    public function create(
        Request $request,
        AppUserRepository $users,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('shopping_list_create', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'The form expired. Please try again.');

            return $this->redirectToDashboard($request);
        }

        // Anonymous visitors can only create public lists.
        $currentUser = $this->getCurrentUser($request, $users);
        $visibility = (string) $request->request->get('visibility', 'private');
        $isPublicList = $currentUser === null || $visibility === 'public';
        $owner = $isPublicList
            ? $this->getPublicUser($users, $entityManager)
            : $currentUser;

        $name = trim((string) $request->request->get('name'));

        if ($name === '' || strlen($name) > 120) {
            $this->addFlash('error', 'List names must be 1-120 characters.');

            return $this->redirectToDashboard($request);
        }

        // Optional starting items are created together with the new list.
        $initialItems = $this->getSubmittedInitialItems($request);

        if ($initialItems === false) {
            return $this->redirectToDashboard($request);
        }

        $coverImage = $this->getSubmittedCoverImage($request);

        if ($coverImage === false) {
            return $this->redirectToDashboard($request);
        }

        $shoppingList = new ShoppingList($name, $coverImage ?? $this->getRandomCoverGradient(), $owner);

        $entityManager->persist($shoppingList);

        foreach ($initialItems as $initialItem) {
            $item = new ShoppingItem($initialItem['name'], $initialItem['quantity'], $shoppingList);
            $entityManager->persist($item);
        }

        $entityManager->flush();

        $message = $isPublicList
            ? sprintf('Public list "%s" created.', $shoppingList->getName())
            : sprintf('Private list "%s" created.', $shoppingList->getName());

        if ($initialItems !== []) {
            $itemCount = count($initialItems);
            $message = sprintf(
                '%s Added %d %s.',
                $message,
                $itemCount,
                $itemCount === 1 ? 'item' : 'items',
            );
        }

        $this->addFlash('success', $message);

        return $this->redirectToDashboard($request);
    }

    #[Route('/shopping/list/{id<\d+>}/update', name: 'app_shopping_list_update', methods: ['POST'])]
    public function update(
        Request $request,
        ShoppingList $shoppingList,
        AppUserRepository $users,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('shopping_list_update_'.$shoppingList->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'The form expired. Please try again.');

            return $this->redirectToDashboard($request);
        }

        // Private lists can only be changed by their owner.
        $currentUser = $this->getCurrentUser($request, $users);

        if (!$this->canEditShoppingList($shoppingList, $currentUser)) {
            $this->addFlash('error', 'Only the owner can edit a private list.');

            return $this->redirectToDashboard($request);
        }

        $name = trim((string) $request->request->get('name'));

        if ($name === '' || strlen($name) > 120) {
            $this->addFlash('error', 'List names must be 1-120 characters.');

            return $this->redirectToDashboard($request);
        }

        $shoppingList->setName($name);
        $entityManager->flush();

        $this->addFlash('success', sprintf('List renamed to "%s".', $shoppingList->getName()));

        return $this->redirectToDashboard($request);
    }

    #[Route('/shopping/list/{id<\d+>}/delete', name: 'app_shopping_list_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        ShoppingList $shoppingList,
        AppUserRepository $users,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('shopping_list_delete_'.$shoppingList->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'The form expired. Please try again.');

            return $this->redirectToDashboard($request);
        }

        // Private lists can only be deleted by their owner.
        $currentUser = $this->getCurrentUser($request, $users);

        if (!$this->canEditShoppingList($shoppingList, $currentUser)) {
            $this->addFlash('error', 'Only the owner can edit a private list.');

            return $this->redirectToDashboard($request);
        }

        $listName = $shoppingList->getName();

        $entityManager->remove($shoppingList);
        $entityManager->flush();

        $this->addFlash('success', sprintf('List "%s" deleted.', $listName));

        return $this->redirectToDashboard($request);
    }

    #[Route('/shopping/list/{id<\d+>}/items', name: 'app_shopping_item_create', methods: ['POST'])]
    public function createItem(
        Request $request,
        ShoppingList $shoppingList,
        AppUserRepository $users,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('shopping_item_create_'.$shoppingList->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'The form expired. Please try again.');

            return $this->redirectToDashboard($request);
        }

        // Public lists are editable by anyone; private lists require ownership.
        $currentUser = $this->getCurrentUser($request, $users);

        if (!$this->canEditShoppingList($shoppingList, $currentUser)) {
            $this->addFlash('error', 'Only the owner can edit a private list.');

            return $this->redirectToDashboard($request);
        }

        $name = trim((string) $request->request->get('name'));
        $quantity = $this->normalizeQuantity((string) $request->request->get('quantity', '1'));

        if ($name === '' || strlen($name) > 180) {
            $this->addFlash('error', 'Item names must be 1-180 characters.');

            return $this->redirectToDashboard($request);
        }

        if ($quantity === null) {
            $this->addFlash('error', 'Use a quantity greater than 0.');

            return $this->redirectToDashboard($request);
        }

        $item = new ShoppingItem($name, $quantity, $shoppingList);

        $entityManager->persist($item);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Item "%s" added to "%s".', $item->getName(), $shoppingList->getName()));

        return $this->redirectToDashboard($request);
    }

    #[Route('/shopping/list/{id<\d+>}/items/{itemId<\d+>}/update', name: 'app_shopping_item_update', methods: ['POST'])]
    public function updateItem(
        Request $request,
        ShoppingList $shoppingList,
        int $itemId,
        ShoppingItemRepository $shoppingItems,
        AppUserRepository $users,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('shopping_item_update_'.$itemId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'The form expired. Please try again.');

            return $this->redirectToDashboard($request);
        }

        // Make sure the submitted item id belongs to this exact list.
        $item = $this->findItemInList($shoppingList, $itemId, $shoppingItems);

        if ($item === null) {
            $this->addFlash('error', 'Item not found.');

            return $this->redirectToDashboard($request);
        }

        $currentUser = $this->getCurrentUser($request, $users);

        if (!$this->canEditShoppingList($shoppingList, $currentUser)) {
            $this->addFlash('error', 'Only the owner can edit a private list.');

            return $this->redirectToDashboard($request);
        }

        $name = trim((string) $request->request->get('name'));
        $quantity = $this->normalizeQuantity((string) $request->request->get('quantity', '1'));

        if ($name === '' || strlen($name) > 180) {
            $this->addFlash('error', 'Item names must be 1-180 characters.');

            return $this->redirectToDashboard($request);
        }

        if ($quantity === null) {
            $this->addFlash('error', 'Use a quantity greater than 0.');

            return $this->redirectToDashboard($request);
        }

        $item
            ->setName($name)
            ->setQuantity($quantity)
            ->setChecked($request->request->has('checked'));

        $entityManager->flush();

        $this->addFlash('success', sprintf('Item "%s" updated.', $item->getName()));

        return $this->redirectToDashboard($request);
    }

    #[Route('/shopping/list/{id<\d+>}/items/{itemId<\d+>}/delete', name: 'app_shopping_item_delete', methods: ['POST'])]
    public function deleteItem(
        Request $request,
        ShoppingList $shoppingList,
        int $itemId,
        ShoppingItemRepository $shoppingItems,
        AppUserRepository $users,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('shopping_item_delete_'.$itemId, (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'The form expired. Please try again.');

            return $this->redirectToDashboard($request);
        }

        // Make sure the submitted item id belongs to this exact list.
        $item = $this->findItemInList($shoppingList, $itemId, $shoppingItems);

        if ($item === null) {
            $this->addFlash('error', 'Item not found.');

            return $this->redirectToDashboard($request);
        }

        $currentUser = $this->getCurrentUser($request, $users);

        if (!$this->canEditShoppingList($shoppingList, $currentUser)) {
            $this->addFlash('error', 'Only the owner can edit a private list.');

            return $this->redirectToDashboard($request);
        }

        $itemName = $item->getName();

        $entityManager->remove($item);
        $entityManager->flush();

        $this->addFlash('success', sprintf('Item "%s" deleted.', $itemName));

        return $this->redirectToDashboard($request);
    }

    private function getActiveListFilter(Request $request, ?AppUser $currentUser): string
    {
        if ($currentUser === null) {
            return 'all';
        }

        $listFilter = (string) $request->query->get('listFilter', 'all');

        if (!in_array($listFilter, self::LIST_FILTERS, true)) {
            return 'all';
        }

        return $listFilter;
    }

    private function getListSearchQuery(Request $request): string
    {
        $searchQuery = trim((string) $request->query->get('q', ''));

        if (strlen($searchQuery) > 120) {
            return substr($searchQuery, 0, 120);
        }

        return $searchQuery;
    }

    /**
     * @return array<int, array{value: string, label: string, isActive: bool, query: array<string, string>}>
     */
    private function buildListFilterOptions(string $activeListFilter, string $listSearchQuery): array
    {
        $options = [
            ['value' => 'all', 'label' => 'All lists'],
            ['value' => 'mine', 'label' => 'My lists'],
            ['value' => 'public', 'label' => 'Public lists'],
        ];

        return array_map(
            static function (array $option) use ($activeListFilter, $listSearchQuery): array {
                $query = [];

                if ($option['value'] !== 'all') {
                    $query['listFilter'] = $option['value'];
                }

                if ($listSearchQuery !== '') {
                    $query['q'] = $listSearchQuery;
                }

                return [
                    'value' => $option['value'],
                    'label' => $option['label'],
                    'isActive' => $activeListFilter === $option['value'],
                    'query' => $query,
                ];
            },
            $options,
        );
    }

    private function redirectToDashboard(Request $request): RedirectResponse
    {
        // Preserve active filters/search after form submissions.
        return $this->redirectToRoute('app_dashboard', $this->getDashboardQueryParameters($request));
    }

    /**
     * @return array<string, string>
     */
    private function getDashboardQueryParameters(Request $request): array
    {
        $routeParameters = [];
        $listFilter = (string) $request->query->get('listFilter', 'all');
        $searchQuery = $this->getListSearchQuery($request);

        if (in_array($listFilter, self::LIST_FILTERS, true) && $listFilter !== 'all') {
            $routeParameters['listFilter'] = $listFilter;
        }

        if ($searchQuery !== '') {
            $routeParameters['q'] = $searchQuery;
        }

        return $routeParameters;
    }

    private function getCurrentUser(Request $request, AppUserRepository $users): ?AppUser
    {
        // The app has username sessions, not Symfony password authentication.
        if (!$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();
        $userId = $session->get('app_user_id');

        if ($userId === null) {
            return null;
        }

        $user = $users->find($userId);

        if ($user === null) {
            $session->remove('app_user_id');
            $session->remove('app_username');
            $this->addFlash('error', 'The selected username no longer exists. Choose another username.');

            return null;
        }

        $session->set('app_username', $user->getUsername());

        return $user;
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

    private function normalizeQuantity(string $quantity): ?float
    {
        // Accept both "1.5" and "1,5" as decimal quantities.
        $normalizedQuantity = str_replace(',', '.', trim($quantity));

        if (!is_numeric($normalizedQuantity)) {
            return null;
        }

        $quantityValue = (float) $normalizedQuantity;

        if ($quantityValue <= 0) {
            return null;
        }

        return $quantityValue;
    }

    /**
     * @return array<int, array{name: string, quantity: float}>|false
     */
    private function getSubmittedInitialItems(Request $request): array|false
    {
        // Empty starting-item rows are ignored; invalid filled rows stop creation.
        $submittedItems = $request->request->all()['items'] ?? [];

        if (!is_array($submittedItems)) {
            $this->addFlash('error', 'Starting items could not be read. Please try again.');

            return false;
        }

        $items = [];

        foreach ($submittedItems as $submittedItem) {
            if (!is_array($submittedItem)) {
                $this->addFlash('error', 'Starting items could not be read. Please try again.');

                return false;
            }

            $nameValue = $submittedItem['name'] ?? '';

            if (!is_scalar($nameValue)) {
                $this->addFlash('error', 'Item names must be 1-180 characters.');

                return false;
            }

            $name = trim((string) $nameValue);

            if ($name === '') {
                continue;
            }

            if (strlen($name) > 180) {
                $this->addFlash('error', 'Item names must be 1-180 characters.');

                return false;
            }

            $quantityValue = $submittedItem['quantity'] ?? '1';

            if (!is_scalar($quantityValue)) {
                $this->addFlash('error', 'Use a quantity greater than 0.');

                return false;
            }

            $quantity = $this->normalizeQuantity((string) $quantityValue);

            if ($quantity === null) {
                $this->addFlash('error', 'Use a quantity greater than 0.');

                return false;
            }

            $items[] = [
                'name' => $name,
                'quantity' => $quantity,
            ];

            if (count($items) > self::MAX_INITIAL_ITEMS) {
                $this->addFlash('error', sprintf('Add up to %d starting items when creating a list.', self::MAX_INITIAL_ITEMS));

                return false;
            }
        }

        return $items;
    }

    private function getSubmittedCoverImage(Request $request): string|false|null
    {
        // Null means no upload; false means an upload was present but invalid.
        $uploadedCover = $request->files->get('coverImage');

        if (!$uploadedCover instanceof UploadedFile) {
            return null;
        }

        if (!$uploadedCover->isValid()) {
            $message = in_array($uploadedCover->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'Cover pictures must be 2 MB or smaller.'
                : 'The cover picture could not be uploaded.';

            $this->addFlash('error', $message);

            return false;
        }

        if ($uploadedCover->getSize() > self::MAX_COVER_IMAGE_SIZE) {
            $this->addFlash('error', 'Cover pictures must be 2 MB or smaller.');

            return false;
        }

        $fileExtension = $this->getCoverImageExtension($uploadedCover);

        if ($fileExtension === null) {
            $this->addFlash('error', 'Use a JPG, PNG, WebP, or GIF cover picture.');

            return false;
        }

        $filename = sprintf('%s.%s', bin2hex(random_bytes(16)), $fileExtension);
        $uploadDirectory = sprintf('%s/public/uploads/covers', $this->getParameter('kernel.project_dir'));

        try {
            $uploadedCover->move($uploadDirectory, $filename);
        } catch (FileException) {
            $this->addFlash('error', 'The cover picture could not be saved.');

            return false;
        }

        return '/uploads/covers/'.$filename;
    }

    private function getCoverImageExtension(UploadedFile $uploadedCover): ?string
    {
        $clientMimeType = $uploadedCover->getClientMimeType();

        if (isset(self::COVER_IMAGE_MIME_TYPES[$clientMimeType])) {
            return self::COVER_IMAGE_MIME_TYPES[$clientMimeType];
        }

        $clientExtension = strtolower($uploadedCover->getClientOriginalExtension());

        return self::COVER_IMAGE_EXTENSIONS[$clientExtension] ?? null;
    }

    private function getRandomCoverGradient(): string
    {
        return self::COVER_GRADIENTS[array_rand(self::COVER_GRADIENTS)];
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

    private function findItemInList(
        ShoppingList $shoppingList,
        int $itemId,
        ShoppingItemRepository $shoppingItems,
    ): ?ShoppingItem {
        // Prevent editing or deleting an item through the wrong list URL.
        $item = $shoppingItems->find($itemId);

        if ($item === null || $item->getShoppingList()->getId() !== $shoppingList->getId()) {
            return null;
        }

        return $item;
    }
}
