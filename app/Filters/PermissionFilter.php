<?php

namespace App\Filters;

use App\Services\AuthService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = session('auth_user');

        if (! is_array($user)) {
            if (ENVIRONMENT === 'testing') {
                return null;
            }

            return redirect()->to('/login')->with('error', 'ログインしてください。');
        }

        $permission = is_array($arguments) && isset($arguments[0]) ? (string) $arguments[0] : 'view';

        if (AuthService::can($permission, $user)) {
            return null;
        }

        return redirect()->to('/')->with('error', 'この操作を行う権限がありません。');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
