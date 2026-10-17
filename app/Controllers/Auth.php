<?php
namespace App\Controllers;
use App\Models\UserModel;

class Auth extends BaseController
{
    public function login()
    {
        if (session()->get('user_id')) {
            return $this->redirectByRole(session()->get('role'));
        }
        return view('auth/login');
    }

    public function attempt()
    {
        $username = (string) $this->request->getPost('username');
        $password = (string) $this->request->getPost('password');

        // --- brute-force throttle (per IP + username, plus a per-IP ceiling) ---
        $throttler = \Config\Services::throttler();
        // cache keys forbid reserved chars (":", "/", etc.) → hash the whole thing
        $rateKey = 'login-' . hash('sha256', $this->request->getIPAddress() . '|' . strtolower($username));
        $ipKey   = 'login-ip-' . hash('sha256', $this->request->getIPAddress());

        // Per-IP ceiling first: a single host cannot fan out across many
        // usernames (each of which would otherwise get its own fresh bucket).
        if ($throttler->check($ipKey, 20, 60) === false) {
            return redirect()->back()
                ->with('error', 'Too many login attempts. Please wait a moment and try again.');
        }
        // max 5 attempts per 30s window per (IP + username)
        if ($throttler->check($rateKey, 5, 30) === false) {
            return redirect()->back()
                ->with('error', 'Too many login attempts. Please wait a moment and try again.');
        }

        $user = (new UserModel())->where('username', $username)->first();
        if (! $user || ! password_verify($password, $user['password'])) {
            return redirect()->back()->with('error', 'Invalid username or password.');
        }
        if ((int) ($user['is_banned'] ?? 0) === 1) {
            return redirect()->back()->with('error', 'This account has been banned. Please contact your administrator.');
        }
        if ($user['role'] !== 'owner'
            && $user['expires_at']
            && strtotime($user['expires_at']) < time()) {
            return redirect()->back()->with('error', 'Account has expired.');
        }

        // clear the throttle on success
        $throttler->remove($rateKey);
        $throttler->remove($ipKey);

        session()->regenerate();
        session()->set([
            'user_id'  => $user['id'],
            'username' => $user['username'],
            'name'     => $user['name'],
            'role'     => $user['role'],
        ]);

        // "Remember me" → issue (or drop) the persistent login cookie.
        if ($this->request->getPost('remember')) {
            set_remember_cookie((int) $user['id'], (string) $user['password']);
        } else {
            clear_remember_cookie();
        }

        return $this->redirectByRole($user['role']);
    }

    public function logout()
    {
        // An idle-timeout logout passes timeout=1 so the login page can explain
        // why the user suddenly has to sign in again.
        $timeout = (bool) $this->request->getPost('timeout');

        clear_remember_cookie();
        session()->destroy();

        return redirect()->to($timeout ? '/login?timeout=1' : '/login');
    }

    private function redirectByRole(string $role)
    {
        return redirect()->to('/' . $role . '/dashboard');
    }
}
