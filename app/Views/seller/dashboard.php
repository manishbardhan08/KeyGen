<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="kg-hero kg-fade">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <div class="kg-hero-eyebrow">Seller Panel</div>
      <h1 class="kg-page-title">Welcome back, <?= esc(explode(' ', session('name'))[0]) ?> 👋</h1>
      <p class="kg-page-sub mb-0">Generate and manage the keys your customers use.</p>
    </div>
    <a href="/seller/keys" class="btn btn-kg-primary"><?= kg_icon('spark', 15) ?> Generate Keys</a>
  </div>
</div>

<div class="row g-3 mb-4">
  <?php
    $cards = [
      ['Balance',   (int) $balance, 'coin',  'kg-tile-success'],
      ['Keys Made', $totalKeys,     'key',   'kg-tile-primary'],
      ['Unused',    $unusedKeys,    'spark', 'kg-tile-info'],
    ];
    foreach ($cards as [$label, $value, $ic, $tile]):
  ?>
  <div class="col-6 col-lg-4">
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

<?php
  $chartMax   = 0;
  foreach ($chart as $d) { $chartMax = max($chartMax, $d['count']); }
  $chartTotal = array_sum(array_column($chart, 'count'));
?>
<div class="card kg-card-pad mb-4">
  <div class="kg-card-head">
    <div class="kg-icon-tile kg-tile-info"><?= kg_icon('spark', 16) ?></div>
    <div class="flex-grow-1">
      <h3 class="kg-card-title">Keys generated</h3>
      <p class="kg-card-sub mb-0">Last 14 days</p>
    </div>
    <span class="kg-badge kg-badge-neutral"><span class="dot"></span><?= (int) $chartTotal ?> in 14d</span>
  </div>
  <div class="kg-chart">
    <?php foreach ($chart as $d):
      $h = $chartMax > 0 ? max(4, (int) round($d['count'] / $chartMax * 100)) : 4;
    ?>
      <div class="bar <?= $d['count'] === 0 ? 'muted' : '' ?>" style="height:<?= $h ?>%"
           title="<?= esc($d['day']) ?> · <?= (int) $d['count'] ?> key(s)"></div>
    <?php endforeach; ?>
  </div>
  <div class="kg-chart-x">
    <?php foreach ($chart as $d): ?><span><?= esc($d['label']) ?></span><?php endforeach; ?>
  </div>
</div>

<div class="row g-4">
  <div class="col-12 col-lg-7">
    <div class="card overflow-hidden h-100">
      <div class="d-flex align-items-center justify-content-between px-4 py-3" style="border-bottom:1px solid var(--kg-border)">
        <div class="d-flex align-items-center gap-3">
          <div class="kg-icon-tile kg-tile-primary"><?= kg_icon('key', 16) ?></div>
          <div>
            <h3 class="kg-card-title">Recent Keys</h3>
            <p class="kg-card-sub mb-0">Your latest generated keys.</p>
          </div>
        </div>
        <a href="/seller/keys/list" class="text-decoration-none fw-semibold" style="font-size:12.5px">See all →</a>
      </div>

      <?php if (empty($recentKeys)): ?>
        <div class="kg-empty">
          <div class="kg-icon-tile"><?= kg_icon('key', 22) ?></div>
          <p class="mb-1" style="color:var(--kg-text-2)">No keys yet</p>
          <p class="mb-0" style="font-size:12.5px;color:var(--kg-text-3)">Generate your first batch to see it here.</p>
        </div>
      <?php else: ?>
      <div class="kg-table-wrap">
        <table class="kg-table">
          <thead><tr><th>Key</th><th>Level</th><th>Status</th><th>Expires</th></tr></thead>
          <tbody>
          <?php foreach ($recentKeys as $k):
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
              <td><button data-copy="<?= esc($k['key']) ?>" class="kg-copy"><?= esc($k['key']) ?></button></td>
              <td>
                <?php if ($kl > 0): ?><span class="kg-badge kg-badge-info"><span class="dot"></span>L<?= $kl ?></span>
                <?php else: ?><span style="color:var(--kg-text-3)">—</span><?php endif; ?>
              </td>
              <td><span class="kg-badge <?= $badge ?>"><span class="dot"></span><?= esc($statusLabel) ?></span></td>
              <td class="kg-mono" style="font-size:11.5px<?= $expired ? ';color:var(--kg-warning);font-weight:600' : '' ?>"><?= esc($k['expires_at'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-12 col-lg-5">
    <div class="card kg-card-pad mb-4">
      <div class="kg-card-head">
        <div class="kg-icon-tile kg-tile-warning"><?= kg_icon('clock', 16) ?></div>
        <div>
          <h3 class="kg-card-title">Expiring Soon</h3>
          <p class="kg-card-sub">Active keys ending in the next 7 days.</p>
        </div>
      </div>
      <?php if (empty($expiringSoon)): ?>
        <div class="kg-empty" style="padding:26px 10px">
          <div class="kg-icon-tile kg-tile-success"><?= kg_icon('clock', 20) ?></div>
          <p class="mb-0" style="font-size:12.5px;color:var(--kg-text-3)">No keys expiring in the next 7 days.</p>
        </div>
      <?php else: ?>
        <?php foreach ($expiringSoon as $k):
          $days = (int) ceil((strtotime($k['expires_at']) - time()) / 86400);
        ?>
          <div class="kg-row">
            <span class="k kg-mono" style="font-size:11.5px"><?= esc($k['key']) ?></span>
            <span class="v"><span class="kg-badge <?= $days <= 2 ? 'kg-badge-inactive' : 'kg-badge-banned' ?>"><span class="dot"></span><?= $days ?>d</span></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <a href="/seller/keys" class="kg-action-card d-block">
      <div class="kg-action-head">
        <div class="kg-icon-tile"><?= kg_icon('spark', 17) ?></div>
        <h3 class="kg-card-title">Generate New Keys</h3>
      </div>
      <p class="kg-action-desc">Spend balance to mint keys your customers activate.</p>
      <span class="kg-action-link">Open →</span>
    </a>
  </div>
</div>

<?= $this->endSection() ?>
