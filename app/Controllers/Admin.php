<?php
namespace App\Controllers;

use App\Models\KeyModel;
use App\Models\KeyPlanModel;
use App\Models\KeyLevelModel;
use App\Models\UserModel;
use App\Traits\KeyManagementTrait;
use App\Traits\PasswordTrait;

class Admin extends BaseController
{
    use KeyManagementTrait;
    use PasswordTrait;

    /**
     * Numeric ids of every account whose keys fall inside this admin's scope:
     * the admin themselves plus every seller they created. Used for the
     * "level still in use" guard and level usage counters, so a level cannot
     * be deleted while a SELLER's key still references it.
     *
     * @return list<int>
     */
    private function scopedCreatorIds(int $uid, string $uname): array
    {
        $sellerIds = array_column(
            \Config\Database::connect()
                ->table('users')->select('id')
                ->where('role', 'seller')
                ->where('created_by', $uname)
                ->get()->getResultArray(),
            'id'
        );

        return array_merge([$uid], array_map('intval', $sellerIds));
    }

    /**
     * True when a key is inside this admin's scope: the admin's own key, or a
     * key created by a seller this admin created. Used by delete / reset / ban
     * so all three enforce the SAME ownership rule as the All Keys list.
     *
     * @param array<string,mixed> $key
     */
    private function canManageKey(array $key): bool
    {
        $uid = (int) session()->get('user_id');
        if ((int) ($key['created_by'] ?? 0) === $uid) {
            return true;
        }

        $creator = (new UserModel())->find($key['created_by'] ?? 0);

        return $creator !== null
            && $creator['role'] === 'seller'
            && ($creator['created_by'] ?? '') === session()->get('username');
    }

    public function dashboard()
    {
        $uid   = (int) session()->get('user_id');
        $uname = (string) session()->get('username');
        $now   = date('Y-m-d H:i:s');
        $soon  = date('Y-m-d H:i:s', strtotime('+7 days'));

        return view('admin/dashboard', [
            'totalKeys'    => (new KeyModel())->where('created_by', $uid)->countAllResults(),
            'unusedKeys'   => (new KeyModel())->where('created_by', $uid)->where('status', 'unused')->countAllResults(),
            'totalSellers' => (new UserModel())->where('role', 'seller')->where('created_by', $uname)->countAllResults(),
            'recentKeys'   => (new KeyModel())
                ->where('created_by', $uid)->orderBy('id', 'DESC')->limit(6)->findAll(),
            'expiringSoon' => (new KeyModel())
                ->where('created_by', $uid)->where('status', 'used')
                ->where('expires_at >=', $now)->where('expires_at <=', $soon)
                ->orderBy('expires_at', 'ASC')->limit(6)->findAll(),
            'chart'        => $this->dailyCounts($uid),
        ]);
    }


    public function keys()
    {
        $uid = (int) session()->get('user_id');
        $keys = (new KeyModel())->where('created_by', $uid)->orderBy('id', 'DESC')->paginate(20);
        return view('admin/keys', [
            'keys'         => $keys,
            'levels'       => (new KeyLevelModel())->forAdmin($uid),
            'pager'        => \Config\Services::pager(),
        ]);
    }

    // ---------------------------------------------------------------------
    //  Seller pricing plans — each admin sells their own set of durations
    // ---------------------------------------------------------------------

    public function plans()
    {
        $uid = (int) session()->get('user_id');

        // which admins do sellers of THIS admin inherit plans from?
        // just this admin — per-admin scope.
        return view('admin/plans', [
            'plans'   => (new KeyPlanModel())->allForAdmin($uid),
            'levels'  => (new KeyLevelModel())->forAdmin($uid),
            'sellerCount' => (new UserModel())
                ->where('role', 'seller')
                ->where('created_by', session()->get('username'))
                ->countAllResults(),
        ]);
    }

    public function savePlan()
    {
        $uid      = (int) session()->get('user_id');
        $duration = (int) $this->request->getPost('duration_hours'); // duration in HOURS
        $level    = (int) $this->request->getPost('level_number');
        $price    = (int) $this->request->getPost('price');
        $active   = $this->request->getPost('is_active') ? 1 : 0;

        if ($duration < 1 || $duration > 87600) { // 87600h = 3650 days
            return redirect()->back()->with('error', 'Duration must be between 1 and 87600 hours.');
        }
        if ($price < 0) {
            return redirect()->back()->with('error', 'Price cannot be negative.');
        }

        // level must be one this admin actually defined (0 = "any / unleveled")
        $lm = new KeyLevelModel();
        if ($level !== 0 && ! $lm->exists($uid, $level)) {
            return redirect()->back()->with('error', 'Pick a level you created (or leave it as No level).');
        }
        if ($lm->isAdminOnly($uid, $level)) {
            return redirect()->back()->with('error', 'That level is admin-only — sellers cannot buy it. Pick another level.');
        }

        $m = new KeyPlanModel();
        // a plan is unique per (admin, duration_hours, level).
        $existing = $m->where('admin_id', $uid)
            ->where('duration_hours', $duration)
            ->where('level_number', $level)
            ->first();

        $label = kg_duration($duration, true);

        if ($existing) {
            $m->update($existing['id'], ['price' => $price, 'is_active' => $active]);
            $msg = "Updated {$label} plan (level " . ($level > 0 ? $level : '—') . ").";
        } else {
            $m->insert([
                'admin_id'       => $uid,
                'duration_hours' => $duration,
                'duration_days'  => (int) ceil($duration / 24),
                'level_number'   => $level,
                'price'          => $price,
                'is_active'      => $active,
            ]);
            $msg = "Added {$label} plan (level " . ($level > 0 ? $level : '—') . ").";
        }

        return redirect()->to('/admin/plans')->with('success', $msg);
    }

    public function deletePlan($id)
    {
        $uid = (int) session()->get('user_id');
        $m   = new KeyPlanModel();
        $row = $m->find($id);
        if (! $row || (int) $row['admin_id'] !== $uid) {
            return redirect()->to('/admin/plans')->with('error', 'Plan not found.');
        }
        $m->delete($id);
        return redirect()->to('/admin/plans')->with('success', 'Plan removed.');
    }

    // ---------------------------------------------------------------------
    //  Admin-defined license levels (Level 1, Level 2, ... as many as wanted)
    // ---------------------------------------------------------------------

    public function levels()
    {
        $uid   = (int) session()->get('user_id');
        $uname = (string) session()->get('username');

        // how many keys/plans reference each level, for a friendly delete guard.
        // Keys are counted across the whole scope (admin + their sellers) so a
        // level used only by seller keys is still reported as in use.
        $keyCounts = [];
        $creatorIds = $this->scopedCreatorIds($uid, $uname);
        if (! empty($creatorIds)) {
            $rows = \Config\Database::connect()->table('keys')
                ->select('level, COUNT(*) AS c')
                ->whereIn('created_by', $creatorIds)
                ->groupBy('level')
                ->get()->getResultArray();
            foreach ($rows as $r) {
                $keyCounts[(int) ($r['level'] ?? 0)] = (int) $r['c'];
            }
        }

        $planCounts = [];
        $pRows = \Config\Database::connect()->table('key_plans')
            ->select('level_number, COUNT(*) AS c')
            ->where('admin_id', $uid)
            ->groupBy('level_number')
            ->get()->getResultArray();
        foreach ($pRows as $p) {
            $planCounts[(int) ($p['level_number'] ?? 0)] = (int) $p['c'];
        }

        return view('admin/levels', [
            'levels'       => (new KeyLevelModel())->forAdmin($uid),
            'keyCounts'    => $keyCounts,
            'planCounts'   => $planCounts,
        ]);
    }

    public function saveLevel()
    {
        $uid  = (int) session()->get('user_id');
        $num  = (int) $this->request->getPost('level_number');
        $name = trim((string) $this->request->getPost('name'));
        $adminOnly = $this->request->getPost('is_admin_only') ? 1 : 0;

        if ($num < 1 || $num > 1000) {
            return redirect()->back()->with('error', 'Level number must be between 1 and 1000.');
        }
        if ($name === '' || mb_strlen($name) > 60) {
            return redirect()->back()->with('error', 'Level name is required (max 60 chars).');
        }

        $m = new KeyLevelModel();
        $existing = $m->where('admin_id', $uid)->where('level_number', $num)->first();

        if ($existing) {
            $m->update($existing['id'], ['name' => $name, 'is_admin_only' => $adminOnly]);
            $msg = "Level {$num} updated.";
        } else {
            $m->insert([
                'admin_id'      => $uid,
                'level_number'  => $num,
                'name'          => $name,
                'is_admin_only' => $adminOnly,
            ]);
            $msg = "Level {$num} added.";
        }

        return redirect()->to('/admin/levels')->with('success', $msg);
    }

    public function deleteLevel($id)
    {
        $uid   = (int) session()->get('user_id');
        $uname = (string) session()->get('username');
        $m    = new KeyLevelModel();
        $row  = $m->find($id);
        if (! $row || (int) $row['admin_id'] !== $uid) {
            return redirect()->to('/admin/levels')->with('error', 'Level not found.');
        }

        $num        = (int) $row['level_number'];
        $creatorIds = $this->scopedCreatorIds($uid, $uname);
        $db         = \Config\Database::connect();

        // Count everything still referencing this level, so we can clean it up
        // in one atomic step and report exactly what changed. (Blocking the
        // delete outright left admins stuck when keys/plans/sellers pointed at
        // a level they wanted gone.)
        $keysInUse = (new KeyModel())
            ->whereIn('created_by', $creatorIds)
            ->where('level', $num)
            ->countAllResults();
        $plansInUse = (new KeyPlanModel())->where('admin_id', $uid)->where('level_number', $num)->countAllResults();
        // sellers assigned to this level (users.level) — their future keys would
        // otherwise inherit a level number that no longer has a name.
        $sellersInUse = (new UserModel())
            ->where('role', 'seller')
            ->where('created_by', $uname)
            ->where('level', $num)
            ->countAllResults();

        $db->transStart();

        // Issued keys stay valid — they are just dropped to "No level" (0).
        if ($keysInUse > 0) {
            $db->table('keys')->whereIn('created_by', $creatorIds)->where('level', $num)->update(['level' => 0]);
        }
        // Plans cannot exist without their level, so remove the ones that used it.
        if ($plansInUse > 0) {
            $db->table('key_plans')->where('admin_id', $uid)->where('level_number', $num)->delete();
        }
        // Sellers assigned to this level fall back to "No level".
        if ($sellersInUse > 0) {
            $db->table('users')->where('role', 'seller')->where('created_by', $uname)->where('level', $num)->update(['level' => 0]);
        }

        $m->delete($id);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->to('/admin/levels')->with('error', 'Could not delete the level. Please try again.');
        }

        $cleared = [];
        if ($keysInUse > 0)    { $cleared[] = "{$keysInUse} key(s) now have no level"; }
        if ($plansInUse > 0)   { $cleared[] = "{$plansInUse} plan(s) removed"; }
        if ($sellersInUse > 0) { $cleared[] = "{$sellersInUse} seller(s) now have no level"; }

        $msg = "Level {$num} removed.";
        if ($cleared !== []) {
            $msg .= ' ' . implode('; ', $cleared) . '.';
        }

        return redirect()->to('/admin/levels')->with('success', $msg);
    }

    public function generateKeys()
    {
        $count    = max(1, min(100, (int) $this->request->getPost('count')));
        $duration = max(1, min(87600, (int) $this->request->getPost('duration'))); // HOURS
        $devices  = max(1, min(100, (int) $this->request->getPost('devices')));   // device limit
        $level    = (int) $this->request->getPost('level');

        // optional human-meaningful tag; the prefix is always your username
        $tag = normalize_key_part((string) $this->request->getPost('key_tag'), 16);

        $uid   = (int) session()->get('user_id');
        $uname = (string) session()->get('username');
        $urole = (string) session()->get('role');

        $lm = new KeyLevelModel();

        // "No level" (0) is a valid choice and stays 0 — identical to how a
        // seller's level-0 plan behaves. A non-zero level must be one this
        // admin actually defined.
        if ($level > 0 && ! $lm->exists($uid, $level)) {
            return redirect()->back()->with('error', 'Pick a level you created (or leave it as No level).');
        }

        // resolve product: admin's own → else inherit from creating owner
        $u       = new UserModel();
        $me      = $u->find($uid);
        $product = trim((string) ($me['product'] ?? ''));
        if ($product === '' && ! empty($me['created_by'])) {
            $owner = $u->where('username', $me['created_by'])->first();
            if ($owner && ! empty($owner['product'])) {
                $product = trim((string) $owner['product']);
            }
        }

        $k    = new KeyModel();
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            // Prefix is always the creator's username; the tag is optional.
            // Total key length stays constant whether or not a tag is used.
            $keyValue = generate_license_key_for($uname, $tag);

            $rows[] = [
                'key'                 => $keyValue,
                'created_by'          => $uid,
                'created_by_username' => $uname,
                'created_by_role'     => $urole,
                'duration_hours'      => $duration,
                'duration_days'       => (int) ceil($duration / 24),
                'level'               => $level,
                'product'             => $product,
                'device_limit'        => $devices,
                'status'              => 'unused',
            ];
        }
        $k->insertBatch($rows);
        return redirect()->to('/admin/keys')->with('success', "{$count} key(s) of " . kg_duration($duration, true) . ' generated.');
    }

    public function deleteKey($id)
    {
        $id  = (int) $id;
        $k   = new KeyModel();
        $row = $k->find($id);

        if (! $row) {
            return redirect()->to('/admin/keys/list')->with('error', 'Key not found.');
        }
        if (! $this->canManageKey($row)) {
            return redirect()->to('/admin/keys/list')->with('error', 'Not permitted.');
        }

        // drop live API sessions tied to this key first (FK hygiene), then the
        // key itself — atomically so we never delete sessions without the key.
        $db = \Config\Database::connect();
        $db->transStart();
        $db->table('api_sessions')->where('key_id', $id)->delete();
        $k->delete($id);
        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->to('/admin/keys/list')->with('error', 'Could not delete key. Please try again.');
        }

        return redirect()->to('/admin/keys/list')->with('success', 'Key removed.');
    }

    /**
     * Delete only the EXPIRED keys in this admin's scope — their own and their
     * sellers' keys whose `expires_at` is in the past. Active/unused keys are
     * left untouched.
     */
    public function deleteExpiredKeys()
    {
        $uid   = (int) session()->get('user_id');
        $uname = (string) session()->get('username');
        $now   = date('Y-m-d H:i:s');
        $db    = \Config\Database::connect();

        $creatorIds = $this->scopedCreatorIds($uid, $uname);

        $keyIds = array_column(
            $db->table('keys')->select('id')
               ->whereIn('created_by', $creatorIds)
               ->where('status', 'used')
               ->where('expires_at IS NOT NULL')
               ->where('expires_at <', $now)
               ->get()->getResultArray(),
            'id'
        );

        if (empty($keyIds)) {
            return redirect()->to('/admin/keys/list')->with('error', 'No expired keys to delete.');
        }

        $db->transStart();
        $db->table('api_sessions')->whereIn('key_id', $keyIds)->delete();
        $db->table('keys')->whereIn('id', $keyIds)->delete();
        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->to('/admin/keys/list')->with('error', 'Could not delete keys. Please try again.');
        }

        return redirect()->to('/admin/keys/list')->with('success', count($keyIds) . ' expired key(s) deleted.');
    }

    public function sellers()
    {
        $uid = (int) session()->get('user_id');
        return view('admin/sellers', [
            'sellers' => (new UserModel())->where('role', 'seller')->where('created_by', session()->get('username'))->orderBy('id', 'DESC')->findAll(),
            'levels'  => (new KeyLevelModel())->forAdmin($uid),
        ]);
    }

    public function createSeller()
    {
        $name     = trim((string) $this->request->getPost('name'));
        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');
        $balance  = (int) $this->request->getPost('balance');
        $duration = max(1, min(36500, (int) $this->request->getPost('duration'))); // days, capped at 100 yr
        $phone    = trim((string) $this->request->getPost('phone')) ?: null;

        if ($name === '' || $username === '' || $password === '' || $duration <= 0) {
            return redirect()->back()->with('error', 'All fields required (duration > 0).');
        }
        if (strlen($password) < 8) {
            return redirect()->back()->with('error', 'Password must be at least 8 characters.');
        }
        if ($balance < 0) {
            return redirect()->back()->with('error', 'Balance cannot be negative.');
        }

        $uid = (int) session()->get('user_id');
        [$sellLevels, $err] = $this->cleanSellLevels($uid);
        if ($err !== null) {
            return redirect()->back()->with('error', $err);
        }

        $u = new UserModel();
        if ($u->where('username', $username)->first()) {
            return redirect()->back()->with('error', 'Username exists.');
        }

        $u->insert([
            'name'            => $name,
            'username'        => $username,
            'password'        => password_hash($password, PASSWORD_BCRYPT),
            'role'            => 'seller',
            'created_by'      => session()->get('username'),
            'created_by_role' => session()->get('role'),
            'balance'         => $balance,
            'level'           => $sellLevels !== [] ? $sellLevels[0] : 0,
            'sell_levels'     => implode(',', $sellLevels),
            'can_extend'      => $this->request->getPost('can_extend') ? 1 : 0,
            'can_ban'         => $this->request->getPost('can_ban') ? 1 : 0,
            'can_reset_hwid'  => $this->request->getPost('can_reset_hwid') ? 1 : 0,
            'can_delete'      => $this->request->getPost('can_delete') ? 1 : 0,
            'phone'           => $phone,
            'expires_at'      => date('Y-m-d H:i:s', strtotime("+{$duration} days")),
        ]);

        return redirect()->to('/admin/sellers')->with('success', 'Seller created.');
    }

    /**
     * Read + validate the `sell_levels[]` checkboxes against the admin's own,
     * NON admin-only levels.
     *
     * @return array{0: list<int>, 1: string|null} [levels, error]
     */
    private function cleanSellLevels(int $uid): array
    {
        $raw = $this->request->getPost('sell_levels');
        $raw = is_array($raw) ? $raw : [];
        $lm  = new KeyLevelModel();

        $out = [];
        foreach ($raw as $v) {
            $n = (int) $v;
            if ($n <= 0) {
                continue;
            }
            if (! $lm->exists($uid, $n) || $lm->isAdminOnly($uid, $n)) {
                return [[], 'One of the selected levels is not a sellable level you created (admin-only levels cannot be sold).'];
            }
            $out[$n] = $n;
        }

        return [array_values($out), null];
    }

    public function deleteSeller($id)
    {
        $u = new UserModel();
        $s = $u->find($id);
        // users.created_by stores the creator's USERNAME (varchar), not id
        if (! $s || $s['role'] !== 'seller' || ($s['created_by'] ?? null) !== session()->get('username')) {
            return redirect()->to('/admin/sellers')->with('error', 'Not found.');
        }

        // Cascade: drop the seller's keys + any live API sessions, then the user,
        // so no key is left pointing at a non-existent creator.
        $db = \Config\Database::connect();
        $db->transStart();

        $keyIds = array_column(
            $db->table('keys')->select('id')->where('created_by', $id)->get()->getResultArray(),
            'id'
        );
        if (! empty($keyIds)) {
            $db->table('api_sessions')->whereIn('key_id', $keyIds)->delete();
        }
        $db->table('keys')->where('created_by', $id)->delete();
        $u->delete($id);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->to('/admin/sellers')->with('error', 'Could not delete seller. Please try again.');
        }

        return redirect()->to('/admin/sellers')->with('success', 'Seller and their keys removed.');
    }

    public function profile()
    {
        $uid  = (int) session()->get('user_id');
        $user = (new UserModel())->find($uid);

        return view('admin/profile', [
            'user'         => $user,
            'totalKeys'    => (new KeyModel())->where('created_by', $uid)->countAllResults(),
            'unusedKeys'   => (new KeyModel())->where('created_by', $uid)->where('status', 'unused')->countAllResults(),
            'totalSellers' => (new UserModel())
                ->where('role', 'seller')
                ->where('created_by', session()->get('username'))
                ->countAllResults(),
        ]);
    }

    /**
     * In-app API reference for admins integrating their software.
     */
    public function docs()
    {
        $user = (new UserModel())->find((int) session()->get('user_id'));

        return view('admin/docs', ['user' => $user]);
    }

    public function changePassword()
    {
        $err = $this->applyPasswordChange((int) session()->get('user_id'));
        if ($err !== null) {
            return redirect()->back()->with('error', $err);
        }

        return redirect()->back()->with('success', 'Password updated.');
    }

    public function regenApiKey()
    {
        $plain = generate_api_key();
        $hash  = hash_api_key($plain);
        $enc   = encrypt_api_key($plain);

        (new \App\Models\UserModel())->update(session()->get('user_id'), [
            'api_key_hash'    => $hash,
            'api_key_display' => $enc,
        ]);

        return redirect()->back()
            ->with('success', 'API key regenerated.')
            ->with('new_api_key', $plain);
    }

    public function updateProfile()
    {
        $name  = trim((string) $this->request->getPost('name'));
        $phone = trim((string) $this->request->getPost('phone')) ?: null;

        if ($name === '') {
            return redirect()->back()->with('error', 'Name is required.');
        }
        if ($phone !== null && ! preg_match('/^[+0-9 ()-]{6,20}$/', $phone)) {
            return redirect()->back()->with('error', 'Phone number looks invalid.');
        }

        $m = new \App\Models\UserModel();
        $u = $m->find(session()->get('user_id'));
        if (! $u) {
            return redirect()->back()->with('error', 'Account not found.');
        }

        $m->update($u['id'], [
            'name'  => $name,
            'phone' => $phone,
        ]);

        // keep session name in sync
        session()->set('name', $name);

        return redirect()->to('/admin/profile')->with('success', 'Profile updated.');
    }
    public function allKeys()
    {
        $db     = \Config\Database::connect();
        $search = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));
        $uid    = (int) session()->get('user_id');
        $uname  = (string) session()->get('username');

        $builder = $db->table('keys k')
            ->select('k.*, u.username AS creator_username, u.name AS creator_name, u.role AS creator_role, u.created_by AS creator_created_by, (SELECT COUNT(*) FROM key_devices kd WHERE kd.key_id = k.id) AS devices_used')
            ->join('users u', 'u.id = k.created_by', 'left')
            ->groupStart()
            ->where('k.created_by', $uid)
            ->orWhere('u.created_by', $uname)
            ->groupEnd()
            ->orderBy('k.id', 'DESC');

        if ($search !== '') {
            $builder->groupStart()
                ->like('k.key', $search)
                ->orLike('u.username', $search)
                ->orLike('u.name', $search)
                ->groupEnd();
        }
        if (in_array($status, ['unused', 'used', 'banned'], true)) {
            $builder->where('k.status', $status);
        }

        // Query Builder has no paginate(); count then slice manually.
        $total = $builder->countAllResults(false);
        $perPage = 20;
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $keys = $builder->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        $pager = \Config\Services::pager();
        $pager->makeLinks($page, $perPage, $total, 'default_full');

        // how many keys in scope are already expired (for the bulk-delete card)
        $expiredCount = (int) $db->table('keys')
            ->whereIn('created_by', $this->scopedCreatorIds($uid, $uname))
            ->where('status', 'used')
            ->where('expires_at IS NOT NULL')
            ->where('expires_at <', date('Y-m-d H:i:s'))
            ->countAllResults();

        // owners whose keys this admin can bulk-delete: self first, then sellers
        $creators = [[
            'id'       => $uid,
            'username' => $uname,
            'self'     => true,
            'count'    => (int) $db->table('keys')->where('created_by', $uid)->countAllResults(),
        ]];
        $sellerRows = $db->table('users')->select('id, username')
            ->where('role', 'seller')->where('created_by', $uname)
            ->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($sellerRows as $s) {
            $creators[] = [
                'id'       => (int) $s['id'],
                'username' => (string) $s['username'],
                'self'     => false,
                'count'    => (int) $db->table('keys')->where('created_by', (int) $s['id'])->countAllResults(),
            ];
        }

        return view('admin/keys_list', [
            'keys'         => $keys,
            'search'       => $search,
            'status'       => $status,
            'levels'       => (new KeyLevelModel())->nameMap($uid),
            'expiredCount' => $expiredCount,
            'creators'     => $creators,
            'pager'        => $pager,
        ]);
    }

    /**
     * Bulk-delete keys by TYPE (all / expired / unused / active / banned) and
     * OWNER (self, a specific seller, or every owner in this admin's scope).
     * Live API sessions are removed too.
     */
    public function purgeKeys()
    {
        $uid   = (int) session()->get('user_id');
        $uname = (string) session()->get('username');
        $scope = $this->scopedCreatorIds($uid, $uname);

        // ---- resolve owners ----
        $owner = (string) $this->request->getPost('owner');
        if ($owner === 'all') {
            $ids = $scope;
        } elseif ($owner === 'self') {
            $ids = [$uid];
        } else {
            $oid = (int) $owner;
            $ids = in_array($oid, $scope, true) ? [$oid] : []; // never trust the request
        }
        if ($ids === []) {
            return redirect()->back()->with('error', 'Pick whose keys to delete.');
        }

        // ---- resolve type filter ----
        $type = (string) $this->request->getPost('type');
        $now  = date('Y-m-d H:i:s');

        $db = \Config\Database::connect();
        $b  = $db->table('keys')->select('id')->whereIn('created_by', $ids);

        switch ($type) {
            case 'expired':
                $b->where('status', 'used')->where('expires_at IS NOT NULL')->where('expires_at <', $now);
                break;
            case 'unused':
                $b->where('status', 'unused');
                break;
            case 'banned':
                $b->where('status', 'banned');
                break;
            case 'active':
                $b->where('status', 'used')
                  ->groupStart()->where('expires_at IS NULL')->orWhere('expires_at >=', $now)->groupEnd();
                break;
            case 'all':
            default:
                // 'all' deletes only non-active keys (expired, unused, banned).
                // To delete active keys, explicitly choose type=active.
                $b->groupStart()
                    ->where('status', 'unused')
                    ->orWhere('status', 'banned')
                    ->orWhere(function ($q) use ($now) {
                        $q->where('status', 'used')
                          ->where('expires_at IS NOT NULL')
                          ->where('expires_at <', $now);
                    })
                    ->groupEnd();
                break;
        }

        $keyIds = array_column($b->get()->getResultArray(), 'id');
        if ($keyIds === []) {
            return redirect()->to('/admin/keys/list')->with('error', 'No matching keys to delete.');
        }

        $db->transStart();
        $db->table('api_sessions')->whereIn('key_id', $keyIds)->delete();
        $db->table('keys')->whereIn('id', $keyIds)->delete();
        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->to('/admin/keys/list')->with('error', 'Could not delete keys. Please try again.');
        }

        $label = in_array($type, ['all', 'expired', 'unused', 'active', 'banned'], true) ? $type : 'matching';
        return redirect()->to('/admin/keys/list')->with('success', count($keyIds) . " {$label} key(s) deleted.");
    }

    public function editSeller($id)
    {
        $u      = new \App\Models\UserModel();
        $seller = $u->find($id);
        if (! $seller || $seller['role'] !== 'seller'
            || ($seller['created_by'] ?? null) !== session()->get('username')) {
            return redirect()->to('/admin/sellers')->with('error', 'Seller not found.');
        }
        return view('admin/seller_edit', [
            'seller' => $seller,
            'levels' => (new KeyLevelModel())->forAdmin((int) session()->get('user_id')),
        ]);
    }

    public function updateSeller($id)
    {
        $u      = new \App\Models\UserModel();
        $seller = $u->find($id);
        if (! $seller || $seller['role'] !== 'seller'
            || ($seller['created_by'] ?? null) !== session()->get('username')) {
            return redirect()->to('/admin/sellers')->with('error', 'Seller not found.');
        }

        $name     = trim((string) $this->request->getPost('name'));
        $phone    = trim((string) $this->request->getPost('phone')) ?: null;
        $password = (string) $this->request->getPost('password');

        if ($name === '') {
            return redirect()->back()->with('error', 'Name is required.');
        }
        if ($phone !== null && ! preg_match('/^[+0-9 ()-]{6,20}$/', $phone)) {
            return redirect()->back()->with('error', 'Phone number looks invalid.');
        }

        $uid = (int) session()->get('user_id');
        [$sellLevels, $err] = $this->cleanSellLevels($uid);
        if ($err !== null) {
            return redirect()->back()->with('error', $err);
        }

        $update = [
            'name'           => $name,
            'phone'          => $phone,
            'level'          => $sellLevels !== [] ? $sellLevels[0] : 0,
            'sell_levels'    => implode(',', $sellLevels),
            'can_extend'     => $this->request->getPost('can_extend') ? 1 : 0,
            'can_ban'        => $this->request->getPost('can_ban') ? 1 : 0,
            'can_reset_hwid' => $this->request->getPost('can_reset_hwid') ? 1 : 0,
            'can_delete'     => $this->request->getPost('can_delete') ? 1 : 0,
        ];

        if ($password !== '') {
            if (strlen($password) < 8) {
                return redirect()->back()->with('error', 'Password must be at least 8 characters.');
            }
            $update['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        $u->update($id, $update);
        return redirect()->to('/admin/sellers')->with('success', 'Seller updated.');
    }

    /**
     * Recharge a seller's balance — ADDS the amount, never sets/reduces it.
     */
    public function rechargeSeller($id)
    {
        $u = new UserModel();
        $s = $u->find($id);
        if (! $s || $s['role'] !== 'seller'
            || ($s['created_by'] ?? null) !== session()->get('username')) {
            return redirect()->to('/admin/sellers')->with('error', 'Seller not found.');
        }

        $amount = (int) $this->request->getPost('amount');
        if ($amount <= 0) {
            return redirect()->to('/admin/sellers')->with('error', 'Enter a recharge amount greater than 0.');
        }

        \Config\Database::connect()
            ->table('users')
            ->where('id', $id)
            ->update(['balance' => new \CodeIgniter\Database\RawSql('balance + ' . $amount)]);

        $new = (int) $s['balance'] + $amount;
        return redirect()->to('/admin/sellers')
            ->with('success', "Recharged {$amount} for @{$s['username']}. New balance: {$new}.");
    }

    /**
     * Extend a seller's account duration (expiry) by N days.
     */
    public function extendSeller($id)
    {
        $u = new UserModel();
        $s = $u->find($id);
        if (! $s || $s['role'] !== 'seller'
            || ($s['created_by'] ?? null) !== session()->get('username')) {
            return redirect()->to('/admin/sellers')->with('error', 'Seller not found.');
        }

        $days = max(1, min(3650, (int) $this->request->getPost('days')));

        $base = ($s['expires_at'] && strtotime((string) $s['expires_at']) > time())
            ? strtotime((string) $s['expires_at'])
            : time();
        $newExpiry = date('Y-m-d H:i:s', strtotime("+{$days} days", $base));

        $u->update($id, ['expires_at' => $newExpiry]);

        return redirect()->to('/admin/sellers')
            ->with('success', "Seller @{$s['username']} extended by {$days} day(s) — expires {$newExpiry}.");
    }

    /**
     * Ban / unban a seller account.
     *
     * A banned seller cannot sign in, cannot generate keys, and their API
     * credential stops working (their live sessions are ended immediately).
     * Unbanning restores access.
     */
    public function banSeller($id)
    {
        $u = new UserModel();
        $s = $u->find($id);
        if (! $s || $s['role'] !== 'seller'
            || ($s['created_by'] ?? null) !== session()->get('username')) {
            return redirect()->to('/admin/sellers')->with('error', 'Seller not found.');
        }

        $nowBanned = (int) ($s['is_banned'] ?? 0) === 1;
        $newState  = $nowBanned ? 0 : 1;

        $u->update($id, ['is_banned' => $newState]);

        if ($newState === 1) {
            // end any live licensing sessions owned by this seller
            \Config\Database::connect()->table('api_sessions')->where('user_id', $id)->delete();
            return redirect()->to('/admin/sellers')->with('success', "Seller @{$s['username']} banned. They can no longer sign in or generate keys.");
        }

        return redirect()->to('/admin/sellers')->with('success', "Seller @{$s['username']} unbanned. Access restored.");
    }

    public function extendAll()
    {
        $hours = max(1, min(87600, (int) $this->request->getPost('hours')));
        $uid   = (int)    session()->get('user_id');
        $uname = (string) session()->get('username');
        $now   = date('Y-m-d H:i:s');

        $db = \Config\Database::connect();

        // every key this admin can touch: their own + their sellers'
        $creatorIds = $this->scopedCreatorIds($uid, $uname);
        $ph         = implode(',', array_fill(0, count($creatorIds), '?'));

        // ACTIVE only: activated (status=used) and not yet expired.
        // Expired keys and unused keys are intentionally left untouched.
        $activeOnly = "created_by IN ($ph) AND status = 'used' AND expires_at IS NOT NULL AND expires_at > ?";

        // The session-sync query below JOINs `api_sessions` with `keys`, and BOTH
        // tables have an `expires_at` column. An unqualified `expires_at` there
        // is rejected by MySQL/MariaDB with error 1052 ("Column 'expires_at' in
        // WHERE is ambiguous"), so the join needs a table-qualified predicate.
        $activeJoin = "k.created_by IN ($ph) AND k.status = 'used' AND k.expires_at IS NOT NULL AND k.expires_at > ?";

        // ADD N hours to every active key — both columns in ONE transaction so a
        // failure can never leave duration_hours bumped but the expiry un-moved.
        $db->transStart();

        $db->query(
            "UPDATE `keys` SET duration_hours = duration_hours + ? WHERE {$activeOnly}",
            array_merge([$hours], $creatorIds, [$now])
        );
        $bumped = $db->affectedRows();

        // TIMESTAMPADD(HOUR, ?, col) takes the interval as a normal bindable
        // value, unlike `INTERVAL ? HOUR` which is not reliably preparable on
        // every MySQL/MariaDB version.
        $db->query(
            "UPDATE `keys` SET expires_at = TIMESTAMPADD(HOUR, ?, expires_at) WHERE {$activeOnly}",
            array_merge([$hours], $creatorIds, [$now])
        );

        // Keep live sessions in sync with the new expiry so clients keep validating.
        // NOTE: this query has NO `?` for $hours — it copies the already-updated
        // k.expires_at — so it must be bound with $creatorIds + $now ONLY.
        // Passing $hours as well would make the bind count exceed the placeholder
        // count, and CI4 then skips binding the query altogether.
        $db->query(
            "UPDATE `api_sessions` s
             INNER JOIN `keys` k ON k.id = s.key_id
             SET s.expires_at = k.expires_at
             WHERE {$activeJoin}",
            array_merge($creatorIds, [$now])
        );

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->to('/admin/keys/list')
                ->with('error', 'Could not extend keys. Please try again.');
        }

        return redirect()->to('/admin/keys/list')
            ->with('success', "Extended {$bumped} active key(s) by " . kg_duration($hours, true) . '. Expired & unused keys untouched.');
    }

    /**
     * Extend ONE key (the single-key companion to extendAll).
     *
     *   • activated key  → push expires_at forward from max(now, current expiry)
     *                      so an expired key is revived, and grow duration_hours.
     *   • unused key     → has no expiry yet, so just grow duration_hours (its
     *                      clock starts longer on first activation).
     *
     * Ownership follows the same rule as reset/ban/delete (own key or a key
     * belonging to a seller this admin created).
     */
    public function extendKey($id)
    {
        $id    = (int) $id;
        $hours = max(1, min(87600, (int) $this->request->getPost('hours')));

        $k   = new KeyModel();
        $row = $k->find($id);
        if (! $row) {
            return redirect()->back()->with('error', 'Key not found.');
        }
        if (! $this->canManageKey($row)) {
            return redirect()->back()->with('error', "You can only manage your own keys or your sellers' keys.");
        }

        $update = ['duration_hours' => (int) $row['duration_hours'] + $hours];

        if (! empty($row['expires_at'])) {
            $base = strtotime((string) $row['expires_at']);
            if ($base === false || $base < time()) {
                $base = time(); // expired / invalid → extend from now, reviving it
            }
            $update['expires_at'] = date('Y-m-d H:i:s', strtotime("+{$hours} hours", $base));
        }

        $k->update($id, $update);

        // Keep live sessions in sync so clients don't get a false "Session expired".
        if (! empty($update['expires_at'])) {
            \Config\Database::connect()
                ->table('api_sessions')
                ->where('key_id', $id)
                ->update(['expires_at' => $update['expires_at']]);
        }

        return redirect()->back()->with('success', 'Key extended by ' . kg_duration($hours, true) . '.');
    }


    public function updateProduct()
    {
        $product = trim((string) $this->request->getPost('product'));
        if ($product === '' || mb_strlen($product) > 100) {
            return redirect()->back()->with('error', 'Product name required (max 100 chars).');
        }

        $m = new \App\Models\UserModel();
        $u = $m->find(session()->get('user_id'));
        if (! $u) {
            return redirect()->back()->with('error', 'Account not found.');
        }
        $m->update($u['id'], ['product' => $product]);

        // Backfill the product on EVERY key in this admin's scope — their own
        // keys AND the keys of the sellers they created. Seller keys copy the
        // admin's product at generation time, so without this a product rename
        // would leave older seller keys demanding the previous name, and a
        // client that sends the new name would get "403 Product mismatch".
        $creatorIds = $this->scopedCreatorIds((int) $u['id'], (string) $u['username']);
        \Config\Database::connect()
            ->table('keys')
            ->whereIn('created_by', $creatorIds)
            ->update(['product' => $product]);

        return redirect()->to('/admin/profile')->with('success', 'Product updated.');
    }

    /**
     * Toggle this admin's software on/off and store the offline message.
     * When off, the licensing API returns 503 to all clients in this admin's
     * scope (their own keys and their sellers' keys).
     */
    public function updateSoftware()
    {
        $enabled = $this->request->getPost('software_enabled') ? 1 : 0;
        $message = trim((string) $this->request->getPost('offline_message'));

        if (mb_strlen($message) > 255) {
            return redirect()->back()->with('error', 'Offline message too long (max 255 chars).');
        }

        $m = new \App\Models\UserModel();
        $u = $m->find(session()->get('user_id'));
        if (! $u) {
            return redirect()->back()->with('error', 'Account not found.');
        }

        $m->update($u['id'], [
            'software_enabled' => $enabled,
            'offline_message'  => $message !== '' ? $message : null,
        ]);

        $verb = $enabled ? 'ONLINE' : 'OFFLINE';
        return redirect()->to('/admin/profile')->with(
            'success',
            "Software is now {$verb}." . ($enabled ? '' : ' Clients will receive your offline message.')
        );
    }


    public function resetHwid()
    {
        $id = (int) $this->request->getPost('id');
        if ($id <= 0) {
            return redirect()->back()->with('error', 'Invalid key id.');
        }

        $k = new \App\Models\KeyModel();
        $row = $k->find($id);
        if (! $row) {
            return redirect()->back()->with('error', 'Key not found.');
        }

        // ownership: admin's own key OR a key from a seller this admin created
        if (! $this->canManageKey($row)) {
            return redirect()->back()->with('error', 'You can only reset your own keys or keys of sellers you created.');
        }

        // Only clear HWID. Keep status='used', used_at, expires_at intact.
        // Next login will rebind a new HWID without changing the clock.
        $k->update($id, ['hwid' => null]);

        // unbind all devices + kill live sessions for this key
        $db = \Config\Database::connect();
        $db->table('key_devices')->where('key_id', $id)->delete();
        $db->table('api_sessions')->where('key_id', $id)->delete();

        return redirect()->back()->with('success',
            'HWID cleared. Expiry stays the same — the key can now be activated on a new device without extending its lifetime.');
    }

    /**
     * Ban / unban a key in this admin's scope (own keys or a seller's).
     * Banning ends any live API sessions using the key; unbanning restores the
     * prior state (used if it was ever activated, otherwise unused).
     */
    public function toggleKeyStatus($id)
    {
        $id = (int) $id;
        $k  = new KeyModel();
        $row = $k->find($id);
        if (! $row) {
            return redirect()->back()->with('error', 'Key not found.');
        }

        if (! $this->canManageKey($row)) {
            return redirect()->back()->with('error', "You can only manage your own keys or your sellers' keys.");
        }

        if ($row['status'] === 'banned') {
            $restore = ! empty($row['used_at']) ? 'used' : 'unused';
            $k->update($id, ['status' => $restore]);
            return redirect()->back()->with('success', "Key unbanned (restored to {$restore}).");
        }

        $k->update($id, ['status' => 'banned']);
        \Config\Database::connect()->table('api_sessions')->where('key_id', $id)->delete();

        return redirect()->back()->with('success', 'Key banned. Any live sessions were ended.');
    }

    /**
     * Export the admin's whole key scope as CSV (honours ?q= and ?status=).
     */
    public function exportKeys()
    {
        $uid    = (int) session()->get('user_id');
        $uname  = (string) session()->get('username');
        $search = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));

        $db = \Config\Database::connect();
        $builder = $db->table('keys k')
            ->select('k.key, k.status, k.level, k.duration_hours, k.product, k.hwid, k.used_at, k.expires_at, k.created_at, u.username AS creator, u.name AS creator_name')
            ->join('users u', 'u.id = k.created_by', 'left')
            ->groupStart()
            ->where('k.created_by', $uid)
            ->orWhere('u.created_by', $uname)
            ->groupEnd()
            ->orderBy('k.id', 'DESC');

        if ($search !== '') {
            $builder->groupStart()
                ->like('k.key', $search)
                ->orLike('u.username', $search)
                ->orLike('u.name', $search)
                ->groupEnd();
        }
        if (in_array($status, ['unused', 'used', 'banned'], true)) {
            $builder->where('k.status', $status);
        }

        $rows = $builder->get()->getResultArray();
        $csv  = $this->toCsv(
            ['key', 'status', 'level', 'duration_hours', 'product', 'hwid', 'used_at', 'expires_at', 'created_at', 'creator', 'creator_name'],
            $rows
        );

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="keys-' . date('Ymd-His') . '.csv"')
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setBody($csv);
    }

}
