<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="kg-hero kg-fade">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <div class="kg-hero-eyebrow">Seller Panel</div>
      <h1 class="kg-page-title">All Keys</h1>
      <p class="kg-page-sub mb-0">Every key you've generated.</p>
    </div>
    <span class="kg-badge kg-badge-neutral"><span class="dot"></span><?= count($keys) ?> total</span>
  </div>
</div>

<div class="card kg-card-pad mb-4">
  <form method="get" action="/seller/keys/list" class="row g-3 align-items-end">
    <div class="col-12 col-sm-7">
      <label class="kg-field-label">Search</label>
      <input name="q" type="text" value="<?= esc($search) ?>" placeholder="key value" class="form-control">
    </div>
    <div class="col-12 col-sm-5">
      <label class="kg-field-label">Status</label>
      <select name="status" class="form-select">
        <option value=""        <?= $status===''?'selected':'' ?>>All statuses</option>
        <option value="unused"  <?= $status==='unused'?'selected':'' ?>>Unused</option>
        <option value="used"    <?= $status==='used'?'selected':'' ?>>Used</option>
        <option value="banned"  <?= $status==='banned'?'selected':'' ?>>Banned</option>
      </select>
    </div>
    <div class="col-12 d-flex gap-2 flex-wrap">
      <button class="btn btn-kg-primary px-4">Search</button>
      <?php if ($search !== '' || $status !== ''): ?>
        <a href="/seller/keys/list" class="btn btn-kg-ghost px-4">Clear</a>
      <?php endif; ?>
      <a href="<?= site_url('seller/keys/export') . '?' . http_build_query(['q' => $search, 'status' => $status]) ?>"
         class="btn btn-kg-ghost px-4 ms-auto"><?= kg_icon('key', 14) ?> Export CSV</a>
    </div>
  </form>
</div>

<div class="card overflow-hidden">
  <?php if (empty($keys)): ?>
    <div class="kg-empty">
      <div class="kg-icon-tile"><?= kg_icon('key', 22) ?></div>
      <p class="mb-1" style="color:var(--kg-text-2)">No keys found</p>
      <p class="mb-0" style="font-size:12.5px;color:var(--kg-text-3)">
        <?= ($search !== '' || $status !== '') ? 'Try a different filter.' : 'Generate your first key from Generate Keys.' ?>
      </p>
    </div>
  <?php else: ?>
  <div class="kg-table-wrap">
    <table class="kg-table">
      <thead>
        <tr><th>Key</th><th>Status</th><th>Level</th><th>Expires</th><th>Duration</th><th>HWID</th><th class="text-end" data-nosort>Actions</th></tr>
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
        $kl = (int) ($k['level'] ?? 0);
      ?>
        <tr class="<?= $banned ? 'kg-row-banned' : '' ?>">
          <td><button data-copy="<?= esc($k['key']) ?>" class="kg-copy"><?= esc($k['key']) ?><span data-copy-label style="color:var(--kg-text-3);font-size:10px">copy</span></button></td>
          <td><span class="kg-badge <?= $badge ?>"><span class="dot"></span><?= esc($statusLabel) ?></span></td>
          <td>
            <?php if ($kl > 0): ?>
              <span class="kg-badge kg-badge-info"><span class="dot"></span>L<?= $kl ?><?= isset($levelNames[$kl]) ? ' · ' . esc($levelNames[$kl]) : '' ?></span>
            <?php else: ?>
              <span style="color:var(--kg-text-3)">—</span>
            <?php endif; ?>
          </td>
          <td <?= $expired ? 'style="color:var(--kg-warning);font-weight:600"' : '' ?>><?= esc($k['expires_at'] ?? '—') ?></td>
          <td><?= esc(kg_duration((int) ($k['duration_hours'] ?? 0))) ?></td>
          <td class="kg-mono" style="font-size:11px"><?= esc($k['hwid'] ? substr($k['hwid'],0,14).'...' : 'unbound') ?></td>
          <td class="text-end">
            <?php $p = $perms ?? ['extend'=>false,'ban'=>false,'reset'=>false,'delete'=>false]; ?>
            <?php if (! ($p['extend'] || $p['reset'] || $p['ban'] || $p['delete'])): ?>
              <span style="color:var(--kg-text-3)">—</span>
            <?php else: ?>
              <span class="kg-actions">
              <?php if ($p['extend']): ?>
                <button type="button" data-extend-key="<?= $k['id'] ?>" data-extend-key-name="<?= esc($k['key']) ?>" data-extend-key-action="/seller/keys/extend"
                        class="kg-act kg-act-extend" title="Extend expiry (add days)">
                  <?= kg_icon('clock', 15) ?>
                </button>
              <?php endif; ?>
              <?php if ($p['reset'] && $k['status'] === 'used'): ?>
                <button type="button" data-reset-key="<?= $k['id'] ?>" data-reset-key-name="<?= esc($k['key']) ?>" data-reset-key-hwid="<?= esc($k['hwid'] ?? '') ?>" data-reset-action="/seller/keys/reset-hwid"
                        class="kg-act kg-act-reset" title="Reset HWID / re-bind device">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                </button>
              <?php endif; ?>
              <?php if ($p['ban']): ?>
                <form method="post" action="/seller/keys/toggle/<?= $k['id'] ?>"
                      data-confirm-title="<?= $k['status'] === 'banned' ? 'Unban this key?' : 'Ban this key?' ?>"
                      data-confirm-text="<?= $k['status'] === 'banned' ? 'The key will work again for the customer.' : 'The key stops validating and any live sessions end.' ?>">
                  <?= csrf_field() ?>
                  <button type="submit" class="kg-act <?= $banned ? 'kg-act-unban' : 'kg-act-ban' ?>"
                          title="<?= $banned ? 'Banned — click to unban' : 'Active — click to ban' ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
                  </button>
                </form>
              <?php endif; ?>
              <?php if ($p['delete']): ?>
                <button type="button" data-del-key="<?= $k['id'] ?>" data-del-key-name="<?= esc($k['key']) ?>" data-del-action="/seller/keys/delete"
                        class="kg-act kg-act-delete" title="Delete key">
                  <?= kg_icon('trash', 15) ?>
                </button>
              <?php endif; ?>
              </span>
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