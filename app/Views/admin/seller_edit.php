<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="kg-hero kg-fade">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <div class="kg-hero-eyebrow">Admin · Sellers</div>
      <h1 class="kg-page-title">Edit Seller</h1>
      <p class="kg-page-sub mb-0">Updating <span class="kg-mono" style="color:var(--kg-primary)"><?= esc($seller['username']) ?></span></p>
    </div>
    <a href="/admin/sellers" class="btn btn-kg-ghost">← Back to sellers</a>
  </div>
</div>

<form method="post" action="/admin/sellers/update/<?= $seller['id'] ?>"
      data-confirm-title="Save changes?"
      data-confirm-text="Update seller #<?= $seller['id'] ?> · <?= esc($seller['username']) ?>?"
      class="row g-4">
  <?= csrf_field() ?>

  <div class="col-12 col-lg-6">
    <div class="card kg-card-pad h-100">
      <div class="kg-card-head">
        <div class="kg-icon-tile"><?= kg_icon('user', 17) ?></div>
        <h3 class="kg-card-title">Identity</h3>
      </div>
      <?php if (!empty($seller['created_by'])): ?>
        <div class="mb-3 px-3 py-2 rounded-3" style="background:var(--kg-surface-2);border:1px solid var(--kg-border);font-size:12px">
          Created by <span class="fw-semibold"><?= esc($seller['created_by']) ?></span>
          <span style="color:var(--kg-text-3)">· <?= esc($seller['created_by_role'] ?? '') ?></span>
        </div>
      <?php endif; ?>
      <div class="mb-3">
        <label class="kg-field-label">Full name</label>
        <input name="name" type="text" value="<?= esc($seller['name']) ?>" required class="form-control">
      </div>
      <div class="mb-3">
        <label class="kg-field-label">Username</label>
        <input type="text" value="<?= esc($seller['username']) ?>" readonly disabled class="form-control">
        <p class="mt-1 mb-0" style="font-size:11px;color:var(--kg-text-3)">Username cannot be changed.</p>
      </div>
      <div class="mb-3">
        <label class="kg-field-label">Phone number</label>
        <input name="phone" type="text" value="<?= esc($seller['phone'] ?? '') ?>" placeholder="+91 98765 43210" class="form-control">
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card kg-card-pad h-100">
      <div class="kg-card-head">
        <div class="kg-icon-tile"><?= kg_icon('coin', 17) ?></div>
        <h3 class="kg-card-title">Balance &amp; Duration</h3>
      </div>
      <div class="mb-3">
        <label class="kg-field-label">Remaining balance</label>
        <div class="d-flex align-items-center gap-2">
          <input type="text" value="<?= (int) $seller['balance'] ?>" readonly disabled class="form-control kg-mono" style="max-width:170px;background:var(--kg-surface-2)">
          <span style="font-size:11.5px;color:var(--kg-text-3)">spent by the seller when generating keys</span>
        </div>
        <p class="mt-1 mb-0" style="font-size:11px;color:var(--kg-text-3)">Recharge is available from the sellers list (Actions).</p>
      </div>
      <div class="mb-3">
        <label class="kg-field-label">Levels this seller can sell</label>
        <div class="d-flex flex-wrap gap-3">
          <?php
            $current  = parse_level_list($seller['sell_levels'] ?? null);
            $sellable = array_values(array_filter($levels ?? [], static fn ($l) => (int) ($l['is_admin_only'] ?? 0) !== 1));
          ?>
          <?php if ($sellable === []): ?>
            <span style="font-size:12px;color:var(--kg-text-3)">No sellable levels yet — create levels first.</span>
          <?php else: foreach ($sellable as $l): $n = (int) $l['level_number']; ?>
            <label class="d-flex align-items-center gap-2" style="font-size:13px;color:var(--kg-text-2);cursor:pointer">
              <input type="checkbox" name="sell_levels[]" value="<?= $n ?>" class="form-check-input mt-0" <?= in_array($n, $current, true) ? 'checked' : '' ?>>
              Level <?= $n ?> · <?= esc($l['name']) ?>
            </label>
          <?php endforeach; endif; ?>
        </div>
        <p class="mt-1 mb-0" style="font-size:11px;color:var(--kg-text-3)">The seller can generate keys only for these levels.</p>
      </div>
      <div class="mb-3">
        <label class="kg-field-label">Key permissions</label>
        <div class="d-flex flex-wrap gap-3">
          <label class="d-flex align-items-center gap-2" style="font-size:13px;color:var(--kg-text-2);cursor:pointer"><input type="checkbox" name="can_extend" value="1" class="form-check-input mt-0" <?= (int) ($seller['can_extend'] ?? 0) === 1 ? 'checked' : '' ?>> Extend key</label>
          <label class="d-flex align-items-center gap-2" style="font-size:13px;color:var(--kg-text-2);cursor:pointer"><input type="checkbox" name="can_ban" value="1" class="form-check-input mt-0" <?= (int) ($seller['can_ban'] ?? 0) === 1 ? 'checked' : '' ?>> Ban / unban key</label>
          <label class="d-flex align-items-center gap-2" style="font-size:13px;color:var(--kg-text-2);cursor:pointer"><input type="checkbox" name="can_reset_hwid" value="1" class="form-check-input mt-0" <?= (int) ($seller['can_reset_hwid'] ?? 0) === 1 ? 'checked' : '' ?>> Reset HWID</label>
          <label class="d-flex align-items-center gap-2" style="font-size:13px;color:var(--kg-text-2);cursor:pointer"><input type="checkbox" name="can_delete" value="1" class="form-check-input mt-0" <?= (int) ($seller['can_delete'] ?? 0) === 1 ? 'checked' : '' ?>> Delete key</label>
        </div>
      </div>
      <div class="mb-3">
        <label class="kg-field-label">New password <span style="font-weight:400;text-transform:none;color:var(--kg-text-3)">(optional)</span></label>
        <input name="password" type="text" placeholder="Leave blank to keep current" class="form-control">
        <p class="mt-1 mb-0" style="font-size:11px;color:var(--kg-text-3)">Minimum 8 characters if setting.</p>
      </div>
    </div>
  </div>

  <div class="col-12 d-flex flex-wrap gap-3 justify-content-end">
    <a href="/admin/sellers" class="btn btn-kg-ghost px-4">Cancel</a>
    <button type="submit" class="btn btn-kg-primary px-4">Save changes</button>
  </div>
</form>

<?= $this->endSection() ?>