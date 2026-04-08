<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\UserService;
use CodeIgniter\Exceptions\PageNotFoundException;
use InvalidArgumentException;

class Users extends BaseController
{
    public function index()
    {
        $users = (new UserModel())->orderBy('role', 'ASC')->orderBy('name', 'ASC')->findAll();

        return view('users/index', [
            'title' => 'ユーザー管理',
            'users' => $users,
            'roleLabels' => AuthService::roleOptions(),
        ]);
    }

    public function new()
    {
        return view('users/new', [
            'title' => 'ユーザー追加',
            'roles' => AuthService::roleOptions(),
        ]);
    }

    public function create()
    {
        $service = new UserService();

        try {
            $service->create($this->collectPayload());
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/users')->with('message', 'ユーザーを追加しました。');
    }

    public function edit(int $id)
    {
        $user = (new UserModel())->find($id);
        if (! is_array($user)) {
            throw PageNotFoundException::forPageNotFound('ユーザーが見つかりません。');
        }

        return view('users/edit', [
            'title' => 'ユーザー編集',
            'user' => $user,
            'roles' => AuthService::roleOptions(),
        ]);
    }

    public function update(int $id)
    {
        $service = new UserService();

        try {
            $service->update($id, $this->collectPayload(), auth_user());
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->to('/users')->with('message', 'ユーザーを更新しました。');
    }

    public function delete(int $id)
    {
        $service = new UserService();

        try {
            $service->delete($id, auth_user());
        } catch (InvalidArgumentException $e) {
            return redirect()->to('/users')->with('error', $e->getMessage());
        }

        return redirect()->to('/users')->with('message', 'ユーザーを削除しました。');
    }

    private function collectPayload(): array
    {
        return [
            'name' => trim((string) $this->request->getPost('name')),
            'email' => trim((string) $this->request->getPost('email')),
            'role' => trim((string) $this->request->getPost('role')),
            'password' => (string) $this->request->getPost('password'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];
    }
}
