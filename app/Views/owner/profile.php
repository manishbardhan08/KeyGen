<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="kg-hero kg-fade">
  <div class="d-flex align-items-center gap-3 flex-wrap">
    <div class="kg-avatar" style="width:58px;height:58px;font-size:22px;border-radius:16px"><?= esc(strtoupper(substr((string) ($user['name'] ?: $user['username']), 0, 1))) ?></div>
    <div class="flex-grow-1" style="min-width:0">
      <div class="kg-hero-eyebrow">Vault Owner</div>
      <h1 class="kg-page-title text-truncate"><?= esc($user['name']) ?></h1>
      <p class="kg-page-sub mb-0">
        <span class="kg-mono">@<?= esc($user['username']) ?></span>
        <span style="color:var(--kg-text-3)"> · <?= esc(ucfirst($user['role'])) ?></span>
      </p>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-12 col-lg-6">
    <div class="card kg-card-pad h-100">
      <div class="kg-card-head">
        <div class="kg-icon-tile"><?= kg_icon('shield', 17) ?></div>
        <div>
          <h3 class="kg-card-title">Account Info</h3>
          <p class="kg-card-sub">Your owner identity.</p>
        </div>
      </div>
      <?php foreach ([
        ['Name', $user['name']],
        ['Username', $user['username']],
        ['Role', ucfirst($user['role'])],
        ['Created', $user['created_at']],
      ] as [$k,$v]): ?>
        <div class="kg-row">
          <span class="k"><?= esc($k) ?></span>
          <span class="v"><?= esc($v) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card kg-card-pad h-100">
      <div class="kg-card-head">
        <div class="kg-icon-tile kg-tile-warning"><?= kg_icon('key', 17) ?></div>
        <div>
          <h3 class="kg-card-title">Change Password</h3>
          <p class="kg-card-sub">Use at least 8 characters.</p>
        </div>
      </div>
      <form method="post" action="/owner/profile/password" class="d-grid gap-3">
        <?= csrf_field() ?>
        <div>
          <label class="kg-field-label">Current password</label>
          <input name="current_password" type="password" autocomplete="current-password" required class="form-control">
        </div>
        <div>
          <label class="kg-field-label">New password</label>
          <input name="new_password" type="password" autocomplete="new-password" minlength="8" required class="form-control">
        </div>
        <div>
          <label class="kg-field-label">Confirm new password</label>
          <input name="confirm_password" type="password" autocomplete="new-password" minlength="8" required class="form-control">
        </div>
        <button class="btn btn-kg-primary w-100 mt-1">Update password</button>
      </form>
    </div>
  </div>
</div>

<?= $this->endSection() ?>