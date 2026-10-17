<?php
namespace App\Controllers;
use App\Models\UserModel;
use App\Traits\PasswordTrait;

class Owner extends BaseController
{
    use PasswordTrait;
    public function dashboard()
    {
        return view('owner/dashboard', [
            'totalAdmins'  => (new UserModel())->where('role', 'admin')->countAllResults(),
            'totalSellers' => (new UserModel())->where('role', 'seller')->countAllResults(),
            'recentAdmins' => (new UserModel())->where('role', 'admin')->orderBy('id', 'DESC')->limit(6)->findAll(),
        ]);
    }

    public function admins()
    {
        return view('owner/admins', [
            'admins' => (new UserModel())->where('role', 'admin')->orderBy('id', 'DESC')->findAll(),
        ]);
    }

    public function createAdmin()
    {
        $name     = trim((string) $this->request->getPost('name'));
        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');
        $duration = max(1, min(36500, (int) $this->request->getPost('duration'))); // days, capped at 100 yr
        $phone    = trim((string) $this->request->getPost('phone')) ?: null;

        if ($name === '' || $username === '' || $password === '' || $duration <= 0) {
            return redirect()->back()->with('error', 'All fields required (duration > 0).');
        }
        if (strlen($password) < 8) {
            return redirect()->back()->with('error', 'Password must be at least 8 characters.');
        }

        $u = new UserModel();
        if ($u->where('username', $username)->first()) {
            return redirect()->back()->with('error', 'Username already exists.');
        }

        $__p = generate_api_key();

        $u->insert([
            'name'       => $name,
            'username'   => $username,
            'password'   => password_hash($password, PASSWORD_BCRYPT),
            'role'       => 'admin',
            'api_key_hash' => hash_api_key($__p), 'api_key_display' => encrypt_api_key($__p),
            'created_by' => session()->get('username'),
            'created_by_role' => session()->get('role'),
            'phone'      => $phone,
            'balance'    => 0,
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$duration} days")),
        ]);

        return redirect()->to('/owner/admins')->with('success', 'Admin created with API key assigned.');
    }

    public function deleteAdmin($id)
    {
        $u = new UserModel();
        $row = $u->find($id);
        if (! $row || $row['role'] !== 'admin') {
            return redirect()->to('/owner/admins')->with('error', 'Not found.');
        }

        $db = \Config\Database::connect();
        $db->transStart();

        // Cascade: sellers created by this admin → their keys + sessions → sellers,
        // then the admin's own keys/sessions, then the admin.
        $sellerIds = array_column(
            $db->table('users')->select('id')
               ->where('role', 'seller')
               ->where('created_by', $row['username'])
               ->get()->getResultArray(),
            'id'
        );

        $creatorIds = array_merge([(int) $id], array_map('intval', $sellerIds));
        $keyIds = array_column(
            $db->table('keys')->select('id')->whereIn('created_by', $creatorIds)->get()->getResultArray(),
            'id'
        );
        if (! empty($keyIds)) {
            $db->table('api_sessions')->whereIn('key_id', $keyIds)->delete();
        }
        $db->table('keys')->whereIn('created_by', $creatorIds)->delete();
        if (! empty($sellerIds)) {
            $db->table('users')->whereIn('id', $sellerIds)->delete();
        }

        // The admin's pricing tiers and licence levels are scoped to them and
        // would otherwise be orphaned (referencing a deleted admin_id).
        $db->table('key_plans')->where('admin_id', $id)->delete();
        $db->table('key_levels')->where('admin_id', $id)->delete();

        $u->delete($id);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->to('/owner/admins')->with('error', 'Could not delete admin. Please try again.');
        }

        return redirect()->to('/owner/admins')->with('success', 'Admin, their sellers and keys removed.');
    }

    public function profile()
    {
        return view('owner/profile', ['user' => (new UserModel())->find(session()->get('user_id'))]);
    }

    public function changePassword()
    {
        $err = $this->applyPasswordChange((int) session()->get('user_id'));
        if ($err !== null) {
            return redirect()->back()->with('error', $err);
        }

        return redirect()->back()->with('success', 'Password updated.');
    }

    public function changeAdminPassword($id)
    {
        $new     = (string) $this->request->getPost('new_password');
        $confirm = (string) $this->request->getPost('confirm_password');

        if (strlen($new) < 8) {
            return redirect()->back()->with('error', 'New password must be at least 8 characters.');
        }
        if ($new !== $confirm) {
            return redirect()->back()->with('error', 'Passwords do not match.');
        }

        $m = new \App\Models\UserModel();
        $u = $m->find($id);
        if (! $u || $u['role'] !== 'admin') {
            return redirect()->back()->with('error', 'Admin not found.');
        }

        $m->update($id, ['password' => password_hash($new, PASSWORD_BCRYPT)]);
        return redirect()->to('/owner/admins')->with('success', 'Password updated for ' . esc($u['username']) . '.');
    }

}
