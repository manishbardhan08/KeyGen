<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
$uid   = (int)    session()->get('user_id');
$uname = (string) session()->get('username');
?>

<div class="kg-hero kg-fade">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <div class="kg-hero-eyebrow">Admin Console</div>
      <h1 class="kg-page-title">All Keys</h1>
      <p class="kg-page-sub mb-0">Every license key in your scope.</p>
    </div>
    <span class="kg-badge kg-badge-neutral"><span class="dot"></span><?= count($keys) ?> total</span>
  </div>
</div>

<div class="card kg-card-pad mb-4">
  <form method="get" action="/admin/keys/list" class="row g-3 align-items-end">
    <div class="col-12 col-sm-7">
      <label class="kg-field-label">Search</label>
      <input name="q" type="text" value="<?= esc($search ?? '') ?>" placeholder="key value or creator username" class="form-control">
    </div>
    <div class="col-12 col-sm-5">
      <label class="kg-field-label">Status</label>
      <select name="status" class="form-select">
        <option value=""       <?= ($status ?? '')===''?'selected':'' ?>>All statuses</option>
        <option value="unused" <?= ($status ?? '')==='unused'?'selected':'' ?>>Unused</option>
        <option value="used"   <?= ($status ?? '')==='used'?'selected':'' ?>>Used</option>
        <option value="banned" <?= ($status ?? '')==='banned'?'selected':'' ?>>Banned</option>
      </select>
    </div>
    <div class="col-12 d-flex gap-2 flex-wrap">
      <button class="btn btn-kg-primary px-4">Search</button>
      <?php if (($search ?? '') !== '' || ($status ?? '') !== ''): ?>
        <a href="/admin/keys/list" class="btn btn-kg-ghost px-4">Clear</a>
      <?php endif; ?>
      <a href="<?= site_url('admin/keys/export') . '?' . http_build_query(['q' => $search ?? '', 'status' => $status ?? '']) ?>"
         class="btn btn-kg-ghost px-4 ms-auto"><?= kg_icon('key', 14) ?> Export CSV</a>
    </div>
  </form>
</div>

<div class="card kg-card-pad mb-4">
  <div class="kg-card-head">
    <div class="kg-icon-tile"><?= kg_icon('clock', 17) ?></div>
    <div>
      <h3 class="kg-card-title">Extend Active Keys</h3>
      <p class="kg-card-sub">Applies to <b>active</b> keys only (activated &amp; not yet expired) in your scope — your own and your sellers'. Expired &amp; unused keys are left untouched.</p>
    </div>
  </div>
  <form method="post" action="/admin/keys/extend"
        data-confirm-title="Extend all active keys?"
        data-confirm-text="Every active key you own or your sellers created will get these extra hours. Expired & unused keys are not touched. Continue?"
        class="row g-3 align-items-end">
    <?= csrf_field() ?>
    <div class="col-12 col-sm-4">
      <label class="kg-field-label">Add hours</label>
      <input name="hours" type="number" min="1" max="87600" value="24" class="form-control">
    </div>
    <div class="col-12 col-sm-8">
      <button class="btn btn-kg-primary w-100"><?= kg_icon('clock', 15) ?> Extend all active keys</button>
    </div>
    <div class="col-12">
      <p class="mb-0" style="font-size:11.5px;color:var(--kg-text-3)">
        <b>+N hours</b> are added to each active key's current expiry. Only active keys are updated.
      </p>
    </div>
  </form>
</div>

<div class="card kg-card-pad mb-4" style="border-color:rgba(229,72,77,.3)">
  <div class="kg-card-head">
    <div class="kg-icon-tile kg-tile-danger"><?= kg_icon('trash', 17) ?></div>
    <div class="flex-grow-1">
      <h3 class="kg-card-title">Danger zone</h3>
      <p class="kg-card-sub">Permanently delete keys (live sessions included). Choose the <b>type</b> of keys and <b>whose</b> keys to delete.</p>
    </div>
    <span class="kg-badge <?= ($expiredCount ?? 0) > 0 ? 'kg-badge-inactive' : 'kg-badge-neutral' ?>">
      <span class="dot"></span><?= (int) ($expiredCount ?? 0) ?> expired
    </span>
  </div>

  <form method="post" action="/admin/keys/purge"
        data-confirm-title="Delete these keys?"
        data-confirm-text="The selected keys will be permanently deleted (with their live sessions). This cannot be undone. Continue?">
    <?= csrf_field() ?>
    <div class="row g-3 align-items-end">
      <div class="col-12 col-md-4">
        <label class="kg-field-label">Key type</label>
        <select name="type" class="form-select">
          <option value="all">All keys</option>
          <option value="expired">Expired keys</option>
          <option value="unused">Unused keys</option>
          <option value="active">Active keys</option>
          <option value="banned">Banned keys</option>
        </select>
      </div>
      <div class="col-12 col-md-5">
        <label class="kg-field-label">Whose keys</label>
        <select name="owner" class="form-select">
          <option value="all">All owners (me + all sellers)</option>
          <option value="self">Just me</option>
          <?php foreach (($creators ?? []) as $c): if (! empty($c['self'])) { continue; } ?>
            <option value="<?= (int) $c['id'] ?>">@<?= esc($c['username']) ?> · <?= (int) $c['count'] ?> key(s)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <button class="btn btn-kg-danger w-100"><?= kg_icon('trash', 15) ?> Delete keys</button>
      </div>
    </div>
  </form>
</div>

<div class="card overflow-hidden">
  <?php if (empty($keys)): ?>
    <div class="kg-empty">
      <div class="kg-icon-tile"><?= kg_icon('key', 22) ?></div>
      <p class="mb-1" style="color:var(--kg-text-2)">No keys found</p>
      <p class="mb-0" style="font-size:12.5px;color:var(--kg-text-3)">
        <?= (($search ?? '') !== '' || ($status ?? '') !== '') ? 'Try a different filter.' : 'No keys have been generated yet.' ?>
      </p>
    </div>
  <?php else: ?>
  <div class="kg-table-wrap">
    <table class="kg-table">
      <thead>
        <tr>
          <th>Key</th><th>Creator</th><th>Status</th><th>Level</th><th>Product</th>
          <th>Expires</th><th>Duration</th><th>Devices</th><th>HWID</th><th class="text-end" data-nosort>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($keys as $k):
        $expired = key_is_expired($k);
        $banned  = key_is_banned($k);
        if ($expired) {
          $badge = 'kg-badge-expired'; $statusLabel = 'expired';
        } else {
          $badge = match($k['status']) { 'used'=>'kg-badge-active','unused'=>'kg-badge-inactive','banned'=>'kg-badge-banned',default=>'kg-badge-neutral' };
          $statusLabel = match($k['status']) { 'used'=>'active','unused'=>'inactive','banned'=>'banned',default=>$k['status'] };
        }
        $creatorCreatedBy = $k['creator_created_by'] ?? '';
        $ownKey = ((int) $k['created_by'] === $uid)
               || (!empty($k['creator_role']) && $k['creator_role'] === 'seller' && $creatorCreatedBy === $uname);
      ?>
        <tr class="<?= $banned ? 'kg-row-banned' : '' ?>">
          <td>
            <button data-copy="<?= esc($k['key']) ?>" class="kg-copy">
              <?= esc($k['key']) ?><span data-copy-label style="color:var(--kg-text-3);font-size:10px">copy</span>
            </button>
          </td>
          <td>
            <div class="fw-semibold" style="font-size:13px;color:var(--kg-text)"><?= esc($k['creator_name'] ?? '—') ?></div>
            <div class="kg-mono" style="font-size:11px;color:var(--kg-text-3)">
              <?= esc($k['creator_username'] ?? '—') ?><?php if (!empty($k['creator_role'])): ?> · <?= esc($k['creator_role']) ?><?php endif; ?>
            </div>
          </td>
          <td><span class="kg-badge <?= $badge ?>"><span class="dot"></span><?= esc($statusLabel) ?></span></td>
          <td>
            <?php $kl = (int) ($k['level'] ?? 0); ?>
            <?php if ($kl > 0): ?>
              <span class="kg-badge kg-badge-info"><span class="dot"></span>L<?= $kl ?><?= isset($levelNames[$kl]) ? ' · ' . esc($levelNames[$kl]) : '' ?></span>
            <?php else: ?>
              <span style="color:var(--kg-text-3)">—</span>
            <?php endif; ?>
          </td>
          <td><span class="kg-badge kg-badge-info"><?= esc($k['product'] ?: '—') ?></span></td>
          <td <?= $expired ? 'style="color:var(--kg-warning);font-weight:600"' : '' ?>><?= esc($k['expires_at'] ?? '—') ?></td>
          <td><?= esc(kg_duration((int) ($k['duration_hours'] ?? 0))) ?></td>
          <td>
            <?php $du = (int) ($k['devices_used'] ?? 0); $dl = (int) ($k['device_limit'] ?? 1); ?>
            <span class="kg-badge <?= $du >= $dl ? 'kg-badge-inactive' : 'kg-badge-neutral' ?>"><span class="dot"></span><?= $du ?>/<?= $dl ?></span>
          </td>
          <td class="kg-mono" style="font-size:11px"><?= esc($k['hwid'] ? substr($k['hwid'],0,14).'...' : 'unbound') ?></td>
          <td class="text-end">
            <?php if ($ownKey): ?>
              <span class="kg-actions">
              <button type="button" data-extend-key="<?= $k['id'] ?>" data-extend-key-name="<?= esc($k['key']) ?>"
                      class="kg-act kg-act-extend" title="Extend expiry (add days)">
                <?= kg_icon('clock', 15) ?>
              </button>
              <?php if ($k['status'] === 'used'): ?>
                <button type="button" data-reset-key="<?= $k['id'] ?>" data-reset-key-name="<?= esc($k['key']) ?>"
                        data-reset-key-hwid="<?= esc($k['hwid'] ?? '') ?>"
                        class="kg-act kg-act-reset" title="Reset HWID / re-bind device">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                </button>
              <?php endif; ?>
              <form method="post" action="/admin/keys/toggle/<?= $k['id'] ?>"
                    data-confirm-title="<?= $k['status'] === 'banned' ? 'Unban this key?' : 'Ban this key?' ?>"
                    data-confirm-text="<?= $k['status'] === 'banned' ? 'The key will work again for its owner.' : 'The key stops validating and any live sessions end.' ?>">
                <?= csrf_field() ?>
                <button type="submit" class="kg-act <?= $banned ? 'kg-act-unban' : 'kg-act-ban' ?>"
                        title="<?= $banned ? 'Banned — click to unban' : 'Active — click to ban' ?>">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
                </button>
              </form>
              <button type="button" data-del-key="<?= $k['id'] ?>" data-del-key-name="<?= esc($k['key']) ?>" data-del-action="/admin/keys/delete"
                      class="kg-act kg-act-delete" title="Delete key">
                <?= kg_icon('trash', 15) ?>
              </button>
              </span>
            <?php else: ?>
              <span style="color:var(--kg-text-3)">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if (! empty($keys)): ?>
  <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
    <span class="text-muted" style="font-size:12.5px">Showing keys</span>
    <?php if (isset($pager)): ?>
      <?= $pager->links('default', 'default_full') ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<?= $this->endSection() ?>