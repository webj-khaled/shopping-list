<?php

namespace App\Controller;

use App\Entity\AppUser;
use App\Repository\AppUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles the demo username-only session flow.
 */
final class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET'])]
    public function loginPage(): RedirectResponse
    {
        // There is no separate login page; the login form lives on the dashboard.
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/login', name: 'app_login_submit', methods: ['POST'])]
    public function login(Request $request, AppUserRepository $users): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('auth_username', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'The form expired. Please try again.');

            return $this->redirectToRoute('app_dashboard');
        }

        $username = $this->normalizeUsername((string) $request->request->get('username'));

        if (!$this->isValidUsername($username)) {
            $this->addFlash('error', 'Use 3-80 characters: letters, numbers, underscores, or hyphens.');

            return $this->redirectToRoute('app_dashboard');
        }

        $user = $users->findOneBy(['username' => $username]);

        if ($user === null) {
            $this->addFlash('error', 'User not found. Create the username first.');

            return $this->redirectToRoute('app_dashboard');
        }

        // Store only the user id and username in session for this learning app.
        $request->getSession()->set('app_user_id', $user->getId());
        $request->getSession()->set('app_username', $user->getUsername());

        $this->addFlash('success', sprintf('Logged in as "%s".', $user->getUsername()));

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/users', name: 'app_user_create', methods: ['POST'])]
    public function createUser(
        Request $request,
        AppUserRepository $users,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('auth_username', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'The form expired. Please try again.');

            return $this->redirectToRoute('app_dashboard');
        }

        $username = $this->normalizeUsername((string) $request->request->get('username'));

        if (!$this->isValidUsername($username)) {
            $this->addFlash('error', 'Use 3-80 characters: letters, numbers, underscores, or hyphens.');

            return $this->redirectToRoute('app_dashboard');
        }

        if ($users->findOneBy(['username' => $username]) !== null) {
            $this->addFlash('error', 'This username is already taken.');

            return $this->redirectToRoute('app_dashboard');
        }

        // A created username is immediately considered logged in.
        $user = new AppUser($username);

        $entityManager->persist($user);
        $entityManager->flush();

        $request->getSession()->set('app_user_id', $user->getId());
        $request->getSession()->set('app_username', $user->getUsername());

        $this->addFlash('success', sprintf('User "%s" created.', $user->getUsername()));

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/logout', name: 'app_logout_submit', methods: ['POST'])]
    public function logout(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('auth_logout', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'The form expired. Please try again.');

            return $this->redirectToRoute('app_dashboard');
        }

        // Clearing these two session values returns the visitor to public mode.
        $request->getSession()->remove('app_user_id');
        $request->getSession()->remove('app_username');

        $this->addFlash('success', 'Logged out.');

        return $this->redirectToRoute('app_dashboard');
    }

    private function normalizeUsername(string $username): string
    {
        // Usernames are case-insensitive in this app.
        return strtolower(trim($username));
    }

    private function isValidUsername(string $username): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9_-]{2,79}$/', $username) === 1;
    }
}
