<?php
namespace App\Filters;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use App\Models\UserModel;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        if (! $session->get('user_id')) {
            return redirect()->to('/login');
        }

        $user = (new UserModel())->find($session->get('user_id'));
        if (! $user) {
            $session->destroy();
            return redirect()->to('/login')->with('error', 'Session invalid.');
        }

        if ((int) ($user['is_banned'] ?? 0) === 1) {
            $session->destroy();
            return redirect()->to('/login')->with('error', 'This account has been banned.');
        }

        if ($user['role'] !== 'owner'
            && $user['expires_at']
            && strtotime($user['expires_at']) < time()) {
            $session->destroy();
            return redirect()->to('/login')->with('error', 'Account expired.');
        }

        if (! in_array($user['role'], $arguments ?? [], true)) {
            return redirect()->to('/' . $user['role'] . '/dashboard');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
