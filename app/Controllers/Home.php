<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index()
    {
        $role = (string) session()->get('role');

        // Signed-in users go straight to their dashboard; everyone else to login.
        // (The stock CI welcome page is intentionally not exposed.)
        if (session()->get('user_id') && in_array($role, ['owner', 'admin', 'seller'], true)) {
            return redirect()->to('/' . $role . '/dashboard');
        }

        return redirect()->to('/login');
    }
}
