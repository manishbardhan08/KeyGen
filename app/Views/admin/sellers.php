<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="kg-hero kg-fade">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <div class="kg-hero-eyebrow">Admin Console</div>
      <h1 class="kg-page-title">Sellers</h1>
      <p class="kg-page-sub mb-0">Resellers who distribute your keys.</p>
    </div>
    <span class="kg-badge kg-badge-neutral"><span class="dot"></span><?= count($sellers) ?> total</span>
  </div>
</div>

<div class="card kg-card-pad mb-4">
  <div class="kg-card-head">
    <div class="kg-icon-tile"><?= kg_icon('plus', 17) ?></div>
    <div>
      <h3 class="kg-card-title">Create Seller</h3>
      <p class="kg-card-sub">Seller uses balance to generate keys.</p>
    </div>
  </div>
  <form method="post" action="/admin/sellers/create" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-12 col-md-6"><input name="name" placeholder="Full name" required class="form-control"></div>
    <div class="col-12 col-md-6"><input name="username" placeholder="Username" required class="form-control"></div>
    <div class="col-12 col-md-6"><input name="password" type="text" placeholder="Password" required class="form-control"></div>
    <div class="col-12 col-md-6"><input name="balance" type="number" placeholder="Starting balance" value="0" class="form-control"></div>
    <div class="col-12 col-md-6"><input name="duration" type="number" min="1" max="36500" placeholder="Duration (days)" required class="form-control"></div>
    <div class="col-12 col-md-6">
      <label class="kg-field-label">Levels this seller can sell</label>
      <div class="d-flex flex-wrap gap-3">
        <?php $sellable = array_values(array_filter($levels ?? [], static fn ($l) => (int) ($l['is_admin_only'] ?? 0) !== 1)); ?>
        <?php if ($sellable === []): ?>
          <span style="font-size:12px;color:var(--kg-text-3)">No sellable levels yet — create levels first.</span>
        <?php else: foreach ($sellable as $l): ?>
          <label class="d-flex align-items-center gap-2" style="font-size:13px;color:var(--kg-text-2);cursor:pointer">
            <input type="checkbox" name="sell_levels[]" value="<?= (int) $l['level_number'] ?>" class="form-check-input mt-0">
            Level <?= (int) $l['level_number'] ?> · <?= esc($l['name']) ?>
          </label>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <div class="col-12">
      <label class="kg-field-label">Key permissions</label>
      <div class="d-flex flex-wrap gap-3">
        <label class="d-flex align-items-center gap-2" style="font-size:13px;color:var(--kg-text-2);cursor:pointer"><input type="checkbox" name="can_extend" value="1" class="form-check-input mt-0"> Extend key</label>
        <label class="d-flex align-items-center gap-2" style="font-size:13px;color:var(--kg-text-2);cursor:pointer"><input type="checkbox" name="can_ban" value="1" class="form-check-input mt-0"> Ban / unban key</label>
        <label class="d-flex align-items-center gap-2" style="font-size:13px;color:var(--kg-text-2);cursor:pointer"><input type="checkbox" name="can_reset_hwid" value="1" class="form-check-input mt-0"> Reset HWID</label>
        <label class="d-flex align-items-center gap-2" style="font-size:13px;color:var(--kg-text-2);cursor:pointer"><input type="checkbox" name="can_delete" value="1" class="form-check-input mt-0"> Delete key</label>
      </div>
    </div>
    <div class="col-12 col-md-6"><input name="phone" placeholder="Phone (optional)" class="form-control"></div>
    <div class="col-12"><button class="btn btn-kg-primary w-100 mt-1">+ Create Seller</button></div>
  </form>
</div>

<div class="card overflow-hidden">
  <div class="d-flex align-items-center justify-content-between px-4 py-3" style="border-bottom:1px solid var(--kg-border)">
    <h3 class="kg-card-title">Sellers</h3>
    <span class="text-uppercase" style="font-size:11px;letter-spacing:.1em;color:var(--kg-text-3)"><?= count($sellers) ?> total</span>
  </div>
  <?php if (empty($sellers)): ?>
    <div class="kg-empty">
      <div class="kg-icon-tile"><?= kg_icon('bag', 22) ?></div>
      <p class="mb-1" style="color:var(--kg-text-2)">No sellers yet</p>
      <p class="mb-0" style="font-size:12.5px;color:var(--kg-text-3)">Create your first seller above.</p>
    </div>
  <?php else: ?>
  <div class="kg-table-wrap">
    <table class="kg-table">
      <thead><tr><th>Name</th><th>Username</th><th>Phone</th><th>Sells</th><th>Created By</th><th>Balance</th><th>Expires</th><th class="text-end" data-nosort>Action</th></tr></thead>
      <tbody>
      <?php foreach ($sellers as $s): ?>
        <?php $banned = (int) ($s['is_banned'] ?? 0) === 1; ?>
        <tr class="<?= $banned ? 'kg-row-banned' : '' ?>">
          <td class="fw-semibold" style="color:var(--kg-text)">
            <?= esc($s['name']) ?>
            <?php if ($banned): ?>
              <span class="kg-badge kg-badge-banned"><span class="dot"></span>banned</span>
            <?php endif; ?>
          </td>
          <td><?= esc($s['username']) ?></td>
          <td><?= esc($s['phone'] ?? '—') ?></td>
          <td>
            <?php $slv = parse_level_list($s['sell_levels'] ?? null); ?>
            <?php if ($slv !== []): ?>
              <span class="d-inline-flex flex-wrap gap-1">
                <?php foreach ($slv as $lv): ?>
                  <span class="kg-badge kg-badge-info"><span class="dot"></span>L<?= (int) $lv ?></span>
                <?php endforeach; ?>
              </span>
            <?php else: ?>
              <span style="color:var(--kg-text-3)">—</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($s['created_by'])): ?>
              <div class="fw-semibold" style="font-size:13px;color:var(--kg-text)"><?= esc($s['created_by']) ?></div>
              <div class="text-uppercase" style="font-size:10.5px;letter-spacing:.08em;color:var(--kg-text-3)"><?= esc($s['created_by_role'] ?? '—') ?></div>
            <?php else: ?><span style="color:var(--kg-text-3)">—</span><?php endif; ?>
          </td>
          <td>
            <span class="d-inline-flex align-items-center gap-1 fw-semibold" style="color:var(--kg-success);font-size:13px">
              <?= kg_icon('coin', 13) ?> <?= (int)$s['balance'] ?>
            </span>
          </td>
          <td><?= esc($s['expires_at']) ?></td>
          <td class="text-end">
            <span class="kg-actions">
            <button type="button" class="kg-act kg-act-recharge" title="Recharge balance"
                    data-recharge-seller="<?= $s['id'] ?>" data-seller-name="<?= esc($s['username']) ?>" data-seller-balance="<?= (int) $s['balance'] ?>">
              <?= kg_icon('coin', 15) ?>
            </button>
            <button type="button" class="kg-act kg-act-extend" title="Extend duration"
                    data-extend-seller="<?= $s['id'] ?>" data-seller-name="<?= esc($s['username']) ?>" data-seller-expires="<?= esc($s['expires_at'] ?? '') ?>">
              <?= kg_icon('clock', 15) ?>
            </button>
            <a href="/admin/sellers/edit/<?= $s['id'] ?>" class="kg-act kg-act-edit text-decoration-none" title="Edit seller">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            </a>
            <form method="post" action="/admin/sellers/ban/<?= $s['id'] ?>"
                  data-confirm-title="<?= $banned ? 'Unban this seller?' : 'Ban this seller?' ?>"
                  data-confirm-text="<?= $banned ? 'Access will be restored — they can sign in and generate keys again.' : 'They will not be able to sign in or generate keys, and their live sessions end.' ?>">
              <?= csrf_field() ?>
              <button type="submit" class="kg-act <?= $banned ? 'kg-act-unban' : 'kg-act-ban' ?>"
                      title="<?= $banned ? 'Banned — click to unban' : 'Active — click to ban' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
              </button>
            </form>
            <form method="post" action="/admin/sellers/delete/<?= $s['id'] ?>"
                  data-confirm-title="Delete this seller?"
                  data-confirm-text="@<?= esc($s['username']) ?>'s keys and live sessions will also be deleted. Continue?">
              <?= csrf_field() ?>
              <button type="submit" class="kg-act kg-act-delete" title="Delete">
                <?= kg_icon('trash', 15) ?>
              </button>
            </form>
            </span>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?= $this->endSection() ?>