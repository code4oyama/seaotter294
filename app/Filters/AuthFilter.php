<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (is_array(session('auth_user'))) {
            return null;
        }

        if (ENVIRONMENT === 'testing') {
            return null;
        }

        session()->set('intended_url', current_url());

        return redirect()->to('/login')->with('error', 'ログインしてください。');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
