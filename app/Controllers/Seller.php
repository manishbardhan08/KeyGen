<?php
namespace App\Controllers;
use App\Models\UserModel;
use App\Models\KeyModel;
use App\Models\KeyPlanModel;
use App\Models\KeyLevelModel;
use App\Traits\KeyManagementTrait;
use App\Traits\PasswordTrait;

class Seller extends BaseController
{
    use KeyManagementTrait;
    use PasswordTrait;

    public function dashboard()
    {
        $uid  = (int) session()->get('user_id');
        $now  = date('Y-m-d H:i:s');
        $soon = date('Y-m-d H:i:s', strtotime('+7 days'));

        return view('seller/dashboard', [
            'balance'      => (int) (new UserModel())->find($uid)['balance'],
            'totalKeys'    => (new KeyModel())->where('created_by', $uid)->countAllResults(),
            'unusedKeys'   => (new KeyModel())->where('created_by', $uid)->where('status', 'unused')->countAllResults(),
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
        $me  = (new UserModel())->find($uid);

        // Sellers can only buy durations their creating admin has priced.
        $plans = $this->plansForSeller($me);
        $admin = $this->adminForSeller($me);

        // flat plan list for the cascading Duration → Level dropdowns
        $plansJs = array_map(static fn ($p) => [
            'id'    => (int) $p['id'],
            'hours' => (int) $p['duration_hours'],
            'level' => (int) ($p['level_number'] ?? 0),
            'price' => (int) $p['price'],
        ], $plans);

        return view('seller/keys', [
            'keys'   => (new KeyModel())->where('created_by', $uid)->orderBy('id', 'DESC')->findAll(),
            'plans'  => $plans,
            'plansJs' => $plansJs,
            'balance' => (int) ($me['balance'] ?? 0),
            'sellerLevels' => parse_level_list($me['sell_levels'] ?? null),
            'levelNames' => $admin ? (new KeyLevelModel())->nameMap((int) $admin['id']) : [],
        ]);
    }

    /**
     * The admin who created this seller (their plan/level source), or null.
     *
     * @param array<string,mixed>|null $seller
     * @return array<string,mixed>|null
     */
    private function adminForSeller(?array $seller): ?array
    {
        if (! $seller || empty($seller['created_by'])) {
            return null;
        }
        return (new UserModel())
            ->where('username', $seller['created_by'])
            ->where('role', 'admin')
            ->first();
    }

    /**
     * Resolve a seller's creating admin and return that admin's active plans
     * LIMITED to the levels this admin allowed the seller to sell. A seller
     * with no allowed levels (or no admin) gets an empty list — the view then
     * shows a "ask your admin" empty state.
     *
     * @param array<string,mixed>|null $seller
     * @return array<int, array<string,mixed>>
     */
    private function plansForSeller(?array $seller): array
    {
        if (! $seller || empty($seller['created_by'])) {
            return [];
        }
        $u     = new UserModel();
        $admin = $u->where('username', $seller['created_by'])->where('role', 'admin')->first();
        if (! $admin) {
            return [];
        }

        $allowed = parse_level_list($seller['sell_levels'] ?? null);
        if ($allowed === []) {
            return []; // admin hasn't allowed any level for this seller yet
        }

        $plans = (new KeyPlanModel())->activeForAdmin((int) $admin['id']);

        return array_values(array_filter(
            $plans,
            static fn ($p) => in_array((int) ($p['level_number'] ?? 0), $allowed, true)
        ));
    }

    /**
     * The key permissions the admin granted this seller.
     *
     * @param array<string,mixed>|null $seller
     * @return array<string, bool>
     */
    private function sellerPerms(?array $seller): array
    {
        return [
            'extend' => (int) ($seller['can_extend'] ?? 0) === 1,
            'ban'    => (int) ($seller['can_ban'] ?? 0) === 1,
            'reset'  => (int) ($seller['can_reset_hwid'] ?? 0) === 1,
            'delete' => (int) ($seller['can_delete'] ?? 0) === 1,
        ];
    }

    /**
     * True when the currently signed-in seller holds the given key permission.
     */
    private function can(string $perm): bool
    {
        $me = (new UserModel())->find(session()->get('user_id'));

        return $me !== null && (int) ($me[$perm] ?? 0) === 1;
    }

    public function generateKeys()
    {
        $count    = max(1, min(50, (int) $this->request->getPost('count')));
        $duration = (int) $this->request->getPost('duration'); // HOURS
        $level    = (int) $this->request->getPost('level');
        $devices  = max(1, min(100, (int) $this->request->getPost('devices'))); // device limit per key

        // optional human-meaningful tag; the prefix is always your username
        $tag = normalize_key_part((string) $this->request->getPost('key_tag'), 16);

        $uid   = (int)    session()->get('user_id');
        $uname = (string) session()->get('username');
        $urole = (string) session()->get('role');

        $db = \Config\Database::connect();
        $u  = new UserModel();
        $me = $u->find($uid);
        if (! $me) {
            return redirect()->back()->with('error', 'Account not found.');
        }
        if ((int) ($me['is_banned'] ?? 0) === 1) {
            return redirect()->back()->with('error', 'Your account is banned. Please contact your admin.');
        }

        // Resolve the chosen (duration + level) against the seller's own
        // admin's active plans. This is tamper-proof: the price/duration/level
        // all come from the DB, never from the request.
        $plans = $this->plansForSeller($me);
        $plan  = null;
        foreach ($plans as $p) {
            if ((int) $p['duration_hours'] === $duration
                && (int) ($p['level_number'] ?? 0) === $level) {
                $plan = $p;
                break;
            }
        }
        if (! $plan) {
            return redirect()->back()->with('error', 'Please choose a valid duration and level.');
        }

        // Duration + price come from the chosen plan. The plan list is already
        // filtered to the levels THIS admin allowed for this seller, so the
        // plan's level is exactly the level the seller is permitted to sell.
        $duration = (int) $plan['duration_hours'];
        $level    = (int) ($plan['level_number'] ?? 0);

        $cost     = $count * (int) $plan['price'];

        // Sellers don't own a product — keys always inherit the creating admin's
        // product (sellers only distribute the admin's software).
        $product = '';
        if (! empty($me['created_by'])) {
            $admin = $u->where('username', $me['created_by'])->first();
            if ($admin && ! empty($admin['product'])) {
                $product = trim((string) $admin['product']);
            }
        }

        // ---- atomic debit + insert (prevents double-spend via concurrency) ----
        $db->transStart();

        // A free plan (price 0) must NOT go through the balance UPDATE: the row
        // value would be unchanged, and with MySQL's default foundRows=false
        // affectedRows() reports 0 changed rows — which we would misread as
        // "not enough balance". Only debit when there is something to debit.
        if ($cost > 0) {
            $db->table('users')
               ->where('id', $uid)
               ->where('balance >=', $cost)
               ->update(['balance' => new \CodeIgniter\Database\RawSql("balance - {$cost}")]);

            if ($db->affectedRows() < 1) {
                $db->transRollback();
                return redirect()->back()->with('error', "Not enough balance (need {$cost}).");
            }
        }

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
        (new KeyModel())->insertBatch($rows);

        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->with('error', 'Could not generate keys. Please try again.');
        }

        return redirect()->to('/seller/keys')->with('success', "{$count} key(s) of " . kg_duration($duration, true) . ' generated.');
    }

    public function profile()
    {
        return view('seller/profile', ['user' => (new UserModel())->find(session()->get('user_id'))]);
    }

    public function changePassword()
    {
        $err = $this->applyPasswordChange((int) session()->get('user_id'));
        if ($err !== null) {
            return redirect()->back()->with('error', $err);
        }

        return redirect()->back()->with('success', 'Password updated.');
    }

    public function allKeys()
    {
        $uid    = (int) session()->get('user_id');
        $search = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));

        $builder = (new \App\Models\KeyModel())
            ->where('created_by', $uid)
            ->orderBy('id', 'DESC');

        if ($search !== '') {
            $builder->like('key', $search);
        }
        if (in_array($status, ['unused','used','banned'], true)) {
            $builder->where('status', $status);
        }

        $keys = $builder->paginate(20);

        $me    = (new \App\Models\UserModel())->find($uid);
        $admin = $this->adminForSeller($me);

        return view('seller/keys_list', [
            'keys'   => $keys,
            'search' => $search,
            'status' => $status,
            'perms'  => $this->sellerPerms($me),
            'levelNames' => $admin ? (new KeyLevelModel())->nameMap((int) $admin['id']) : [],
            'pager' => \Config\Services::pager(),
        ]);
    }


    public function resetHwid()
    {
        if (! $this->can('can_reset_hwid')) {
            return redirect()->back()->with('error', 'You do not have permission to reset HWID.');
        }

        $id = (int) $this->request->getPost('id');
        if ($id <= 0) {
            return redirect()->back()->with('error', 'Invalid key id.');
        }

        $k = new \App\Models\KeyModel();
        $row = $k->find($id);
        if (! $row) {
            return redirect()->back()->with('error', 'Key not found.');
        }

        // seller can only reset own keys
        if ((int) $row['created_by'] !== (int) session()->get('user_id')) {
            return redirect()->back()->with('error', 'You can only reset your own keys.');
        }

        // only clear HWID; keep status='used', used_at, expires_at
        $k->update($id, ['hwid' => null]);

        // unbind all devices + kill live sessions for this key
        $db = \Config\Database::connect();
        $db->table('key_devices')->where('key_id', $id)->delete();
        $db->table('api_sessions')->where('key_id', $id)->delete();

        return redirect()->back()->with('success',
            'HWID cleared. Expiry stays the same — key can now activate on a new device.');
    }

    /**
     * Ban / unban one of the seller's own keys.
     */
    public function toggleKeyStatus($id)
    {
        if (! $this->can('can_ban')) {
            return redirect()->back()->with('error', 'You do not have permission to ban keys.');
        }

        $id  = (int) $id;
        $uid = (int) session()->get('user_id');
        $k   = new KeyModel();
        $row = $k->find($id);

        if (! $row || (int) $row['created_by'] !== $uid) {
            return redirect()->back()->with('error', 'You can only manage your own keys.');
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
     * Extend ONE of the seller's own keys (requires can_extend).
     */
    public function extendKey($id)
    {
        if (! $this->can('can_extend')) {
            return redirect()->back()->with('error', 'You do not have permission to extend keys.');
        }

        $id    = (int) $id;
        $hours = max(1, min(87600, (int) $this->request->getPost('hours')));
        $uid   = (int) session()->get('user_id');

        $k   = new KeyModel();
        $row = $k->find($id);
        if (! $row || (int) $row['created_by'] !== $uid) {
            return redirect()->back()->with('error', 'You can only extend your own keys.');
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

    /**
     * Delete ONE of the seller's own keys (requires can_delete).
     */
    public function deleteKey($id)
    {
        if (! $this->can('can_delete')) {
            return redirect()->back()->with('error', 'You do not have permission to delete keys.');
        }

        $id  = (int) $id;
        $uid = (int) session()->get('user_id');

        $k   = new KeyModel();
        $row = $k->find($id);
        if (! $row || (int) $row['created_by'] !== $uid) {
            return redirect()->back()->with('error', 'You can only delete your own keys.');
        }

        $db = \Config\Database::connect();
        $db->transStart();
        $db->table('api_sessions')->where('key_id', $id)->delete();
        $k->delete($id);
        $db->transComplete();

        if ($db->transStatus() === false) {
            return redirect()->back()->with('error', 'Could not delete key. Please try again.');
        }

        return redirect()->back()->with('success', 'Key removed.');
    }

    /**
     * Export the seller's own keys as CSV (honours ?q= and ?status=).
     */
    public function exportKeys()
    {
        $uid    = (int) session()->get('user_id');
        $search = trim((string) $this->request->getGet('q'));
        $status = trim((string) $this->request->getGet('status'));

        $builder = \Config\Database::connect()->table('keys')
            ->select('key, status, level, duration_hours, product, hwid, used_at, expires_at, created_at')
            ->where('created_by', $uid)
            ->orderBy('id', 'DESC');

        if ($search !== '') {
            $builder->like('key', $search);
        }
        if (in_array($status, ['unused', 'used', 'banned'], true)) {
            $builder->where('status', $status);
        }

        $rows = $builder->get()->getResultArray();
        $csv  = $this->toCsv(
            ['key', 'status', 'level', 'duration_hours', 'product', 'hwid', 'used_at', 'expires_at', 'created_at'],
            $rows
        );

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="keys-' . date('Ymd-His') . '.csv"')
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setBody($csv);
    }

}
