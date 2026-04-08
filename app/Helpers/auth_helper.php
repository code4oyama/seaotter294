<?php

use App\Services\AuthService;

if (! function_exists('auth_user')) {
    function auth_user(): ?array
    {
        $user = session('auth_user');

        return is_array($user) ? $user : null;
    }
}

if (! function_exists('auth_is_logged_in')) {
    function auth_is_logged_in(): bool
    {
        return is_array(auth_user());
    }
}

if (! function_exists('auth_can')) {
    function auth_can(string $permission, ?array $user = null): bool
    {
        return AuthService::can($permission, $user);
    }
}

if (! function_exists('auth_role_label')) {
    function auth_role_label(?string $role = null): string
    {
        $resolvedRole = $role ?? (string) (auth_user()['role'] ?? '');

        return AuthService::roleLabel($resolvedRole);
    }
}
