<?php

namespace App\Services;

use App\Models\UserModel;
use InvalidArgumentException;

class UserService
{
    public function __construct(private readonly UserModel $userModel = new UserModel())
    {
    }

    public function create(array $data): int
    {
        $payload = $this->normalizePayload($data, true);
        $this->userModel->insert($payload);

        return (int) $this->userModel->getInsertID();
    }

    public function update(int $id, array $data, ?array $actor = null): void
    {
        $user = $this->findOrFail($id);
        $payload = $this->normalizePayload($data, false, $id, $user);

        if ((int) ($actor['id'] ?? 0) === $id && (($user['role'] ?? '') === AuthService::ROLE_ADMIN)) {
            $nextRole = (string) ($payload['role'] ?? $user['role']);
            $nextActive = (bool) ($payload['is_active'] ?? $user['is_active']);

            if ((! $nextActive || $nextRole !== AuthService::ROLE_ADMIN) && $this->activeAdminCount() <= 1) {
                throw new InvalidArgumentException('最後の管理者は無効化または権限変更できません。');
            }
        }

        $this->userModel->update($id, $payload);
    }

    public function delete(int $id, ?array $actor = null): void
    {
        $user = $this->findOrFail($id);

        if ((int) ($actor['id'] ?? 0) === $id) {
            throw new InvalidArgumentException('自分自身のユーザーは削除できません。');
        }

        if ((string) ($user['role'] ?? '') === AuthService::ROLE_ADMIN && $this->activeAdminCount() <= 1) {
            throw new InvalidArgumentException('最後の管理者は削除できません。');
        }

        $this->userModel->delete($id);
    }

    private function findOrFail(int $id): array
    {
        $user = $this->userModel->find($id);

        if (! is_array($user)) {
            throw new InvalidArgumentException('ユーザーが見つかりません。');
        }

        return $user;
    }

    private function activeAdminCount(): int
    {
        return $this->userModel
            ->where('role', AuthService::ROLE_ADMIN)
            ->where('is_active', 1)
            ->countAllResults();
    }

    private function normalizePayload(array $data, bool $isCreate, ?int $ignoreId = null, array $existing = []): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $role = trim((string) ($data['role'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $isActive = isset($data['is_active']) ? (int) ((bool) $data['is_active']) : 0;

        if ($name === '' || $email === '' || $role === '') {
            throw new InvalidArgumentException('必須項目を入力してください。');
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('メールアドレスの形式が不正です。');
        }

        if (! array_key_exists($role, AuthService::roleOptions())) {
            throw new InvalidArgumentException('権限が不正です。');
        }

        $builder = $this->userModel->builder();
        $builder->where('email', $email);
        if ($ignoreId !== null) {
            $builder->where('id !=', $ignoreId);
        }

        if ($builder->countAllResults() > 0) {
            throw new InvalidArgumentException('同じメールアドレスのユーザーが既に存在します。');
        }

        $payload = [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'is_active' => $isActive,
        ];

        if ($isCreate) {
            if (strlen($password) < 8) {
                throw new InvalidArgumentException('パスワードは8文字以上で入力してください。');
            }
            $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);

            return $payload;
        }

        if ($password !== '') {
            if (strlen($password) < 8) {
                throw new InvalidArgumentException('パスワードは8文字以上で入力してください。');
            }

            $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        if ($existing !== [] && ! array_key_exists('password_hash', $payload)) {
            unset($payload['password_hash']);
        }

        return $payload;
    }
}
