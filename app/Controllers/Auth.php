<?php

namespace App\Controllers;

use App\Services\AuthService;
use InvalidArgumentException;

class Auth extends BaseController
{
    public function login()
    {
        if (auth_is_logged_in()) {
            return redirect()->to('/');
        }

        return view('auth/login', [
            'title' => 'ログイン',
        ]);
    }

    public function attemptLogin()
    {
        $service = new AuthService();

        try {
            $service->attemptLogin(
                (string) $this->request->getPost('login'),
                (string) $this->request->getPost('password')
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        $intendedUrl = session()->get('intended_url');
        session()->remove('intended_url');

        return redirect()->to(is_string($intendedUrl) && $intendedUrl !== '' ? $intendedUrl : '/')->with('message', 'ログインしました。');
    }

    public function logout()
    {
        (new AuthService())->logout();

        return redirect()->to('/login')->with('message', 'ログアウトしました。');
    }
}
