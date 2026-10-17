<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="kg-hero kg-fade">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <div class="kg-hero-eyebrow">Admin Console</div>
      <h1 class="kg-page-title">Generate Keys</h1>
      <p class="kg-page-sub mb-0">Create new license keys for your software.</p>
    </div>
    <a href="/admin/keys/list" class="btn btn-kg-ghost"><?= kg_icon('key', 15) ?> All Keys</a>
  </div>
</div>

<div class="card kg-card-pad mb-4">
  <div class="kg-card-head">
    <div class="kg-icon-tile"><?= kg_icon('spark', 17) ?></div>
    <div>
      <h3 class="kg-card-title">New Batch</h3>
      <p class="kg-card-sub">The prefix is always your username. The tag is optional — leave it blank if you like. Key length is the same either way.</p>
    </div>
  </div>
  <form method="post" action="/admin/keys/generate" class="row g-3 align-items-end">
    <?= csrf_field() ?>
    <div class="col-12">
      <div class="row g-3">
        <div class="col-12 col-sm-4">
          <label class="kg-field-label">Key prefix</label>
          <input class="form-control kg-mono" value="<?= esc(session('username')) ?>" readonly style="background:var(--kg-surface-2)">
        </div>
        <div class="col-12 col-sm-4">
          <label class="kg-field-label">Custom tag <span style="font-weight:400;color:var(--kg-text-3)">(optional)</span></label>
          <input name="key_tag" id="kg-tag" maxlength="16" class="form-control kg-mono" placeholder="RAHUL / BATCH1">
        </div>
        <div class="col-12 col-sm-4">
          <label class="kg-field-label">Preview</label>
          <input id="kg-prev" readonly class="form-control kg-mono" value="—" style="background:var(--kg-surface-2)">
        </div>
      </div>
      <p class="mt-2 mb-1" style="font-size:11.5px;color:var(--kg-text-3)">
        Tag accepts A–Z and 0–9 only (auto-uppercased). The tag is optional — total key length stays the same either way.
      </p>
    </div>
    <div class="col-6 col-md-3">
      <label class="kg-field-label">How many keys?</label>
      <input name="count" type="number" min="1" max="100" value="1" class="form-control">
    </div>
    <div class="col-6 col-md-3">
      <label class="kg-field-label">Valid for (hours)</label>
      <input name="duration" type="number" min="1" value="720" class="form-control">
    </div>
    <div class="col-6 col-md-3">
      <label class="kg-field-label">Devices per key</label>
      <input name="devices" type="number" min="1" max="100" value="1" class="form-control">
    </div>
    <div class="col-6 col-md-3">
      <label class="kg-field-label">Level</label>
      <select name="level" class="form-select" <?= empty($levels) ? 'disabled' : '' ?>>
        <option value="0">No level</option>
        <?php foreach ($levels as $l): ?>
          <option value="<?= (int) $l['level_number'] ?>">Level <?= (int) $l['level_number'] ?> · <?= esc($l['name']) ?><?= (int) ($l['is_admin_only'] ?? 0) === 1 ? ' · admin-only' : '' ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12">
      <button class="btn btn-kg-primary w-100">✦ Generate</button>
      <p class="mt-2 mb-0" style="font-size:11.5px;color:var(--kg-text-3)">
        <b>Devices per key</b>: how many devices this key may activate on (e.g. 1 = single device, 3 = up to 3 devices).
      </p>
    </div>
    <?php if (empty($levels)): ?>
      <div class="col-12">
        <p class="mb-0" style="font-size:12px;color:var(--kg-text-3)">
          Tip: define your software's levels under <a href="/admin/levels" class="fw-semibold">License Levels</a>. Then every key carries a level.
        </p>
      </div>
    <?php endif; ?>
  </form>
</div>

<div class="card kg-card-pad mb-4">
  <div class="kg-card-head">
    <div class="kg-icon-tile"><?= kg_icon('clock', 17) ?></div>
    <div>
      <h3 class="kg-card-title">Quick Presets</h3>
      <p class="kg-card-sub">Click a preset to fill the form instantly.</p>
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <button type="button" data-preset='{"count":1,"duration":24}'   class="kg-pill">🔥 1 key · 1 day</button>
    <button type="button" data-preset='{"count":1,"duration":168}'  class="kg-pill">📅 1 key · 7 days</button>
    <button type="button" data-preset='{"count":1,"duration":720}' class="kg-pill">👑 1 key · 30 days</button>
    <button type="button" data-preset='{"count":10,"duration":720}' class="kg-pill">🎁 10 keys · 30 days</button>
    <button type="button" data-preset='{"count":50,"duration":720}' class="kg-pill">🚀 50 keys · 30 days</button>
  </div>
</div>

<div class="card overflow-hidden">
  <?php
    $levelNames = [];
    foreach (($levels ?? []) as $l) { $levelNames[(int) $l['level_number']] = $l['name']; }
  ?>
  <div class="d-flex align-items-center justify-content-between px-4 py-3" style="border-bottom:1px solid var(--kg-border)">
    <h3 class="kg-card-title">Your Keys</h3>
    <span class="text-uppercase" style="font-size:11px;letter-spacing:.1em;color:var(--kg-text-3)"><?= count($keys) ?> total</span>
  </div>
  <?php if (empty($keys)): ?>
    <div class="kg-empty">
      <div class="kg-icon-tile"><?= kg_icon('key', 22) ?></div>
      <p class="mb-1" style="color:var(--kg-text-2)">No keys yet</p>
      <p class="mb-0" style="font-size:12.5px;color:var(--kg-text-3)">Generate your first batch above.</p>
    </div>
  <?php else: ?>
  <div class="kg-table-wrap">
    <table class="kg-table">
      <thead>
        <tr><th>Key</th><th>Duration</th><th>Devices</th><th>Level</th><th>Status</th><th>HWID</th><th>Expires</th><th class="text-end"></th></tr>
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
          <td>
            <button data-copy="<?= esc($k['key']) ?>" class="kg-copy">
              <?= esc($k['key']) ?><span data-copy-label style="color:var(--kg-text-3);font-size:10px">copy</span>
            </button>
          </td>
          <td><?= esc(kg_duration((int) ($k['duration_hours'] ?? 0))) ?></td>
          <td><span class="kg-badge kg-badge-neutral"><span class="dot"></span><?= (int) ($k['device_limit'] ?? 1) ?></span></td>
          <td>
            <?php if ($kl > 0): ?>
              <span class="kg-badge kg-badge-info"><span class="dot"></span>L<?= $kl ?><?= isset($levelNames[$kl]) ? ' · ' . esc($levelNames[$kl]) : '' ?></span>
            <?php else: ?>
              <span style="color:var(--kg-text-3)">—</span>
            <?php endif; ?>
          </td>
          <td><span class="kg-badge <?= $badge ?>"><span class="dot"></span><?= esc($statusLabel) ?></span></td>
          <td class="kg-mono" style="font-size:11px"><?= esc($k['hwid'] ? substr($k['hwid'],0,14).'...' : '—') ?></td>
          <td <?= $expired ? 'style="color:var(--kg-warning);font-weight:600"' : '' ?>><?= esc($k['expires_at'] ?? '—') ?></td>
          <td class="text-end">
            <span class="kg-actions">
            <button type="button" data-extend-key="<?= $k['id'] ?>" data-extend-key-name="<?= esc($k['key']) ?>"
                    class="kg-act kg-act-extend" title="Extend expiry (add days)">
              <?= kg_icon('clock', 15) ?>
            </button>
            <form method="post" action="/admin/keys/delete/<?= $k['id'] ?>"
                  data-confirm-title="Delete this key?"
                  data-confirm-text="Key <?= esc($k['key']) ?> will be permanently deleted. Continue?">
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

  <?php if (! empty($keys)): ?>
  <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
    <span class="text-muted" style="font-size:12.5px">Showing keys</span>
    <?php if (isset($pager)): ?>
      <?= $pager->links('default', 'default_full') ?>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<script>
(function(){
  var t   = document.getElementById('kg-tag');
  var out = document.getElementById('kg-prev');
  if (!t || !out || !window.kgKeyPreview) return;

  var user = <?= kg_json(session('username')) ?>;
  function paint(){ out.value = window.kgKeyPreview(user, t.value); }

  t.addEventListener('input', paint);
  paint();
})();
</script>

<?= $this->endSection() ?>