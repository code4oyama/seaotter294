<?php

namespace App\Database\Seeds;

use App\Models\UserModel;
use App\Services\AuthService;
use CodeIgniter\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    public function run()
    {
        $model = new UserModel();

        foreach ($this->definitions() as $user) {
            $existing = $model->where('email', $user['email'])->first();
            $payload = [
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'is_active' => 1,
                'password_hash' => password_hash($user['password'], PASSWORD_DEFAULT),
            ];

            if ($existing === null) {
                $model->insert($payload);
                continue;
            }

            $model->update((int) $existing['id'], $payload);
        }
    }

    /**
     * @return list<array{name:string,email:string,role:string,password:string}>
     */
    private function definitions(): array
    {
        return [
            ['name' => '閲覧ユーザー01', 'email' => 'viewer01@example.test', 'role' => AuthService::ROLE_VIEWER, 'password' => 'password123'],
            ['name' => '閲覧ユーザー02', 'email' => 'viewer02@example.test', 'role' => AuthService::ROLE_VIEWER, 'password' => 'password123'],
            ['name' => '制限付き編集者01', 'email' => 'limited01@example.test', 'role' => AuthService::ROLE_EDITOR_LIMITED, 'password' => 'password123'],
            ['name' => '制限付き編集者02', 'email' => 'limited02@example.test', 'role' => AuthService::ROLE_EDITOR_LIMITED, 'password' => 'password123'],
            ['name' => '制限なし編集者01', 'email' => 'editor01@example.test', 'role' => AuthService::ROLE_EDITOR_FULL, 'password' => 'password123'],
            ['name' => '制限なし編集者02', 'email' => 'editor02@example.test', 'role' => AuthService::ROLE_EDITOR_FULL, 'password' => 'password123'],
            ['name' => '管理者01', 'email' => 'admin01@example.test', 'role' => AuthService::ROLE_ADMIN, 'password' => 'password123'],
            ['name' => '管理者02', 'email' => 'admin02@example.test', 'role' => AuthService::ROLE_ADMIN, 'password' => 'password123'],
        ];
    }
}
