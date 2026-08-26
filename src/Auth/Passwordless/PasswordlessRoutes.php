<?php

declare(strict_types=1);

namespace Switch\Foundation\Auth\Passwordless;

use Switch\Router\Facade\Route;

/**
 * Convenient route registration helper for Passwordless Authentication.
 */
class PasswordlessRoutes
{
    /**
     * Register full set of passwordless authentication web routes.
     *
     * @param string $controller Class name of the controller extending PasswordlessController
     * @param array{prefix?: string, name_prefix?: string, middleware?: array<mixed>} $options
     */
    public static function register(string $controller = PasswordlessController::class, array $options = []): void
    {
        $prefix = rtrim($options['prefix'] ?? '/auth', '/');
        $namePrefix = $options['name_prefix'] ?? 'auth.';
        $middleware = $options['middleware'] ?? [];

        $groupAttrs = [
            'prefix' => $prefix,
            'as' => $namePrefix,
            'name' => $namePrefix,
        ];

        if (!empty($middleware)) {
            $groupAttrs['middleware'] = $middleware;
        }

        Route::group($groupAttrs, function () use ($controller) {
            // Login routes
            Route::get('/login', [$controller, 'showLoginForm'])->name('login');
            Route::post('/login', [$controller, 'requestLogin'])->name('login.request');

            // Registration routes
            Route::get('/register', [$controller, 'showRegisterForm'])->name('register');
            Route::post('/register', [$controller, 'requestRegister'])->name('register.request');

            // Recovery routes
            Route::get('/recover', [$controller, 'showRecoveryForm'])->name('recover');
            Route::post('/recover', [$controller, 'requestRecovery'])->name('recover.request');

            // Magic link verification
            Route::get('/verify/{token}', [$controller, 'verify'])->name('verify');

            // Link sent confirmation page
            Route::get('/link-sent', [$controller, 'showLinkSent'])->name('link_sent');

            // Logout
            Route::post('/logout', [$controller, 'logout'])->name('logout');
            Route::get('/logout', [$controller, 'logout'])->name('logout.get');
        });
    }
}
