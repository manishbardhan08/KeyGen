<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="kg-hero kg-fade">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <div class="kg-hero-eyebrow">Vault Owner</div>
      <h1 class="kg-page-title">Hello, <?= esc(explode(' ', session('name'))[0]) ?> 👋</h1>
      <p class="kg-page-sub mb-0">Manage your admins and keep the platform healthy.</p>
    </div>
    <a href="/owner/admins" class="btn btn-kg-primary"><?= kg_icon('plus', 15) ?> New Admin</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <?php
    $cards = [
      ['Admins',  $totalAdmins,  'users', 'kg-tile-primary'],
      ['Sellers', $totalSellers, 'bag',   'kg-tile-info'],
    ];
    foreach ($cards as [$label, $value, $ic, $tile]):
  ?>
  <div class="col-6">
    <div class="kg-stat">
      <div class="kg-stat-head">
        <div class="kg-icon-tile <?= $tile ?>"><?= kg_icon($ic, 16) ?></div>
        <span class="kg-stat-label"><?= esc($label) ?></span>
      </div>
      <div class="kg-stat-value"><?= (int) $value ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <div class="col-12 col-lg-7">
    <div class="card overflow-hidden h-100">
      <div class="d-flex align-items-center justify-content-between px-4 py-3" style="border-bottom:1px solid var(--kg-border)">
        <div class="d-flex align-items-center gap-3">
          <div class="kg-icon-tile kg-tile-primary"><?= kg_icon('users', 16) ?></div>
          <div>
            <h3 class="kg-card-title">Recent Admins</h3>
            <p class="kg-card-sub mb-0">The latest admins on the platform.</p>
          </div>
        </div>
        <a href="/owner/admins" class="text-decoration-none fw-semibold" style="font-size:12.5px">See all →</a>
      </div>

      <?php if (empty($recentAdmins)): ?>
        <div class="kg-empty">
          <div class="kg-icon-tile"><?= kg_icon('users', 22) ?></div>
          <p class="mb-1" style="color:var(--kg-text-2)">No admins yet</p>
          <p class="mb-0" style="font-size:12.5px;color:var(--kg-text-3)">Create your first admin to see it here.</p>
        </div>
      <?php else: ?>
      <div class="kg-table-wrap">
        <table class="kg-table">
          <thead><tr><th>Name</th><th>Username</th><th>Expires</th></tr></thead>
          <tbody>
          <?php foreach ($recentAdmins as $a): ?>
            <tr>
              <td class="fw-semibold" style="color:var(--kg-text)"><?= esc($a['name']) ?></td>
              <td class="kg-mono" style="font-size:11.5px">@<?= esc($a['username']) ?></td>
              <td><?= esc($a['expires_at'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-12 col-lg-5">
    <div class="row g-3">
      <div class="col-12">
        <a href="/owner/admins" class="kg-action-card d-block">
          <div class="kg-action-head">
            <div class="kg-icon-tile"><?= kg_icon('users', 17) ?></div>
            <h3 class="kg-card-title">Manage Admins</h3>
          </div>
          <p class="kg-action-desc">Create admins with API keys &amp; custom durations.</p>
          <span class="kg-action-link">Open →</span>
        </a>
      </div>
      <div class="col-12">
        <a href="/owner/profile" class="kg-action-card d-block">
          <div class="kg-action-head">
            <div class="kg-icon-tile"><?= kg_icon('user', 17) ?></div>
            <h3 class="kg-card-title">My Profile</h3>
          </div>
          <p class="kg-action-desc">Update your name and change your password.</p>
          <span class="kg-action-link">Open →</span>
        </a>
      </div>
    </div>
  </div>
</div>

<?= $this->endSection() ?>
