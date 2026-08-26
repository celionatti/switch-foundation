<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Passwordless;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Switch\Controller\Controller;
use Switch\Foundation\Auth\AuthManager;
use Switch\Foundation\Auth\Passwordless\Exception\InvalidTokenException;
use Switch\Foundation\Auth\Passwordless\Exception\TooManyRequestsException;
use Switch\Foundation\Auth\Passwordless\Exception\UserNotFoundException;

/**
 * Base Passwordless Authentication Controller.
 *
 * Handles backend authentication flow for magic links, registration, and recovery.
 * Contains no embedded HTML — applications define their own UI templates by overriding
 * the view hook methods.
 */
class PasswordlessController extends Controller
{
    protected PasswordlessManager $passwordless;

    public function __construct(?PasswordlessManager $passwordless = null)
    {
        $this->passwordless = $passwordless ?? PasswordlessManager::getInstance();
    }

    /**
     * Where to redirect users after successful authentication.
     */
    protected function redirectTo(): string
    {
        return '/';
    }

    /**
     * Where to redirect users after logging out.
     */
    protected function redirectAfterLogout(): string
    {
        return '/auth/login';
    }

    /**
     * Hook to render the Login View in user projects.
     */
    public function showLoginForm(): string|ResponseInterface
    {
        return $this->view('auth.login', ['title' => 'Sign In']);
    }

    /**
     * Hook to render the Register View in user projects.
     */
    public function showRegisterForm(): string|ResponseInterface
    {
        return $this->view('auth.register', ['title' => 'Create Account']);
    }

    /**
     * Hook to render the Account Recovery View in user projects.
     */
    public function showRecoveryForm(): string|ResponseInterface
    {
        return $this->view('auth.recover', ['title' => 'Recover Account']);
    }

    /**
     * Hook to render the "Link Sent" confirmation page.
     */
    public function showLinkSent(ServerRequestInterface $request): string|ResponseInterface
    {
        $email = $request->getQueryParams()['email'] ?? '';
        $type = $request->getQueryParams()['type'] ?? 'login';

        return $this->view('auth.link-sent', [
            'title' => 'Check Your Email',
            'email' => $email,
            'type' => $type,
        ]);
    }

    /**
     * Process request for a magic login link.
     */
    public function requestLogin(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $email = strtolower(trim((string) ($body['email'] ?? '')));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->handleError($request, 'Please provide a valid email address.', '/auth/login');
        }

        try {
            $this->passwordless->sendLoginLink($email);
            return $this->handleSuccess(
                $request,
                "We sent a magic sign-in link to {$email}. Please check your inbox!",
                "/auth/link-sent?email=" . urlencode($email) . "&type=login"
            );
        } catch (TooManyRequestsException $e) {
            return $this->handleError($request, $e->getMessage(), '/auth/login', 429);
        } catch (UserNotFoundException $e) {
            return $this->handleError($request, $e->getMessage(), '/auth/login', 404);
        } catch (\Throwable $e) {
            return $this->handleError($request, "Unable to send login link: " . $e->getMessage(), '/auth/login');
        }
    }

    /**
     * Process request for a registration confirmation link.
     */
    public function requestRegister(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $email = strtolower(trim((string) ($body['email'] ?? '')));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->handleError($request, 'Please provide a valid email address.', '/auth/register');
        }

        $userData = $body;
        unset($userData['email'], $userData['_csrf'], $userData['_token']);

        try {
            $this->passwordless->sendRegistrationLink($email, $userData);
            return $this->handleSuccess(
                $request,
                "We sent a confirmation link to {$email}. Click it to activate your account!",
                "/auth/link-sent?email=" . urlencode($email) . "&type=register"
            );
        } catch (TooManyRequestsException $e) {
            return $this->handleError($request, $e->getMessage(), '/auth/register', 429);
        } catch (\Throwable $e) {
            return $this->handleError($request, "Unable to send registration link: " . $e->getMessage(), '/auth/register');
        }
    }

    /**
     * Process request for an account recovery link.
     */
    public function requestRecovery(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $email = strtolower(trim((string) ($body['email'] ?? '')));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->handleError($request, 'Please provide a valid email address.', '/auth/recover');
        }

        try {
            $this->passwordless->sendRecoveryLink($email);
            return $this->handleSuccess(
                $request,
                "We sent an account recovery link to {$email}.",
                "/auth/link-sent?email=" . urlencode($email) . "&type=recovery"
            );
        } catch (TooManyRequestsException $e) {
            return $this->handleError($request, $e->getMessage(), '/auth/recover', 429);
        } catch (UserNotFoundException $e) {
            // For security, can still pretend sent or display not found
            return $this->handleError($request, $e->getMessage(), '/auth/recover', 404);
        } catch (\Throwable $e) {
            return $this->handleError($request, "Unable to send recovery link: " . $e->getMessage(), '/auth/recover');
        }
    }

    /**
     * Verify magic link token and authenticate the user.
     */
    public function verify(mixed $token = null, ?ServerRequestInterface $request = null): ResponseInterface
    {
        if ($token instanceof ServerRequestInterface) {
            $request = $token;
            $token = $request->getAttribute('token') ?? $request->getQueryParams()['token'] ?? null;
        } elseif ($token === null && $request !== null) {
            $token = $request->getAttribute('token') ?? $request->getQueryParams()['token'] ?? null;
        }

        $tokenStr = (string) $token;

        if (empty($tokenStr)) {
            return $this->handleError($request, 'Authentication token missing.', '/auth/login');
        }

        try {
            $user = $this->passwordless->authenticate($tokenStr);
            $userName = method_exists($user, 'getAttribute') ? $user->getAttribute('name') : ($user->name ?? 'User');

            $this->toast("Welcome, {$userName}! You have been securely logged in.", 'success');

            if ($request && $this->wantsJson($request)) {
                return $this->json([
                    'success' => true,
                    'message' => 'Authenticated successfully.',
                    'user' => method_exists($user, 'toArray') ? $user->toArray() : ['id' => $user->getAuthIdentifier()],
                    'redirect' => $this->redirectTo(),
                ]);
            }

            return $this->redirect($this->redirectTo());
        } catch (InvalidTokenException $e) {
            return $this->handleError($request, $e->getMessage(), '/auth/login');
        } catch (\Throwable $e) {
            return $this->handleError($request, 'Authentication failed: ' . $e->getMessage(), '/auth/login');
        }
    }

    /**
     * Log out the currently authenticated user.
     */
    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        AuthManager::getInstance()->logout();
        $this->toast('You have been signed out.', 'info');

        if ($this->wantsJson($request)) {
            return $this->json([
                'success' => true,
                'message' => 'Logged out successfully.',
                'redirect' => $this->redirectAfterLogout(),
            ]);
        }

        return $this->redirect($this->redirectAfterLogout());
    }

    /**
     * Helper to determine if the client expects a JSON response.
     */
    protected function wantsJson(?ServerRequestInterface $request): bool
    {
        if ($request === null) {
            return false;
        }

        $accept = $request->getHeaderLine('Accept');
        $contentType = $request->getHeaderLine('Content-Type');

        return str_contains($accept, 'application/json') || str_contains($contentType, 'application/json');
    }

    /**
     * Handle error responses consistently across HTML and JSON.
     */
    protected function handleError(?ServerRequestInterface $request, string $message, string $redirectUrl, int $statusCode = 400): ResponseInterface
    {
        $this->toast($message, 'error');

        if ($this->wantsJson($request)) {
            return $this->json([
                'success' => false,
                'error' => $message,
            ], $statusCode);
        }

        return $this->redirect($redirectUrl);
    }

    /**
     * Handle success responses consistently across HTML and JSON.
     */
    protected function handleSuccess(?ServerRequestInterface $request, string $message, string $redirectUrl): ResponseInterface
    {
        $this->toast($message, 'success');

        if ($this->wantsJson($request)) {
            return $this->json([
                'success' => true,
                'message' => $message,
                'redirect' => $redirectUrl,
            ]);
        }

        return $this->redirect($redirectUrl);
    }
}
