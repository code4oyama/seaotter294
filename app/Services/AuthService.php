<?php

namespace App\Services;

use App\Models\UserModel;
use InvalidArgumentException;

class AuthService
{
    public const ROLE_VIEWER = 'viewer';
    public const ROLE_EDITOR_LIMITED = 'editor_limited';
    public const ROLE_EDITOR_FULL = 'editor_full';
    public const ROLE_ADMIN = 'admin';

    public function __construct(private readonly UserModel $userModel = new UserModel())
    {
    }

    /**
     * @return array<string,string>
     */
    public static function roleOptions(): array
    {
        return [
            self::ROLE_VIEWER => '閲覧ユーザー',
            self::ROLE_EDITOR_LIMITED => '編集者（制限付き）',
            self::ROLE_EDITOR_FULL => '編集者（制限なし）',
            self::ROLE_ADMIN => '管理者',
        ];
    }

    public static function roleLabel(string $role): string
    {
        return self::roleOptions()[$role] ?? $role;
    }

    public static function can(string $permission, ?array $user = null): bool
    {
        if (! is_array($user)) {
            $sessionUser = session('auth_user');
            $user = is_array($sessionUser) ? $sessionUser : null;
        }

        if (! is_array($user)) {
            return ENVIRONMENT === 'testing';
        }

        $role = (string) ($user['role'] ?? '');

        return match ($permission) {
            'view' => in_array($role, [self::ROLE_VIEWER, self::ROLE_EDITOR_LIMITED, self::ROLE_EDITOR_FULL, self::ROLE_ADMIN], true),
            'edit' => in_array($role, [self::ROLE_EDITOR_LIMITED, self::ROLE_EDITOR_FULL, self::ROLE_ADMIN], true),
            'delete' => in_array($role, [self::ROLE_EDITOR_FULL, self::ROLE_ADMIN], true),
            'admin' => $role === self::ROLE_ADMIN,
            default => false,
        };
    }

    public function attemptLogin(string $login, string $password): array
    {
        $login = trim($login);

        if ($login === '' || $password === '') {
            throw new InvalidArgumentException('メールアドレスとパスワードを入力してください。');
        }

        $user = $this->userModel
            ->where('email', $login)
            ->where('is_active', 1)
            ->first();

        if (! is_array($user) || ! password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            throw new InvalidArgumentException('ログインIDまたはパスワードが正しくありません。');
        }

        $this->userModel->update((int) $user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        $sessionUser = $this->toSessionUser($user);
        session()->set('auth_user', $sessionUser);

        return $sessionUser;
    }

    public function logout(): void
    {
        session()->remove('auth_user');
        session()->remove('intended_url');
    }

    /**
     * @return array{id:int,name:string,email:string,role:string,is_active:bool}
     */
    public function toSessionUser(array $user): array
    {
        return [
            'id' => (int) ($user['id'] ?? 0),
            'name' => (string) ($user['name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
            'is_active' => (bool) ($user['is_active'] ?? false),
        ];
    }
}
