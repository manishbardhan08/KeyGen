<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="kg-hero kg-fade">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <div class="kg-hero-eyebrow">Admin Console</div>
      <h1 class="kg-page-title">License Levels</h1>
      <p class="kg-page-sub mb-0">Define the levels your software uses — as many as you want. Keys carry a level, and your client software reads it to unlock features.</p>
    </div>
    <a href="/admin/plans" class="btn btn-kg-ghost"><?= kg_icon('coin', 15) ?> Plans</a>
  </div>
</div>

<div class="row g-4">
  <!-- add / update a level -->
  <div class="col-12 col-lg-5">
    <div class="card kg-card-pad h-100">
      <div class="kg-card-head">
        <div class="kg-icon-tile kg-tile-primary"><?= kg_icon('layers', 17) ?></div>
        <div>
          <h3 class="kg-card-title">Add / Update Level</h3>
          <p class="kg-card-sub">Saving an existing level number updates that level's name.</p>
        </div>
      </div>
      <form method="post" action="/admin/levels/save" class="d-grid gap-3">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-5">
            <label class="kg-field-label">Level number</label>
            <input name="level_number" id="kg-level-number" type="number" min="1" max="1000" value="<?= (int) (max(array_column($levels, 'level_number') ?: [0]) + 1) ?>" required class="form-control">
          </div>
          <div class="col-7">
            <label class="kg-field-label">Level name</label>
            <input name="name" id="kg-level-name" type="text" maxlength="60" placeholder="e.g. Admin / Pro / Basic" required class="form-control">
          </div>
        </div>
        <label class="d-flex align-items-center gap-2" style="font-size:13px;color:var(--kg-text-2);cursor:pointer">
          <input type="checkbox" name="is_admin_only" id="kg-level-adminonly" value="1" class="form-check-input mt-0">
          Admin-only — only you can generate keys at this level (sellers can't)
        </label>
        <button class="btn btn-kg-primary w-100 mt-1">Save level</button>
      </form>

      <div class="kg-divider"></div>
      <p class="mb-0" style="font-size:12px;color:var(--kg-text-3)">
        Example: <span class="kg-mono">1 = Admin</span>, <span class="kg-mono">2 = Pro</span>, <span class="kg-mono">3 = Basic</span>.
        Client software receives the <span class="kg-mono">level</span> number.
      </p>
    </div>
  </div>

  <!-- current levels -->
  <div class="col-12 col-lg-7">
    <div class="card overflow-hidden h-100">
      <div class="d-flex align-items-center justify-content-between px-4 py-3" style="border-bottom:1px solid var(--kg-border)">
        <div>
          <h3 class="kg-card-title">Your Levels</h3>
          <p class="kg-card-sub mb-0">Keys &amp; plans reference these by number. There is no default — a key made without a level simply has none.</p>
        </div>
        <span class="text-uppercase" style="font-size:11px;letter-spacing:.1em;color:var(--kg-text-3)"><?= count($levels) ?> total</span>
      </div>

      <?php if (empty($levels)): ?>
        <div class="kg-empty">
          <div class="kg-icon-tile kg-tile-warning"><?= kg_icon('layers', 22) ?></div>
          <p class="mb-1" style="color:var(--kg-text-2)">No levels yet</p>
          <p class="mb-0" style="font-size:12.5px;color:var(--kg-text-3)">Add your first level on the left. Until you do, keys are generated with no level.</p>
        </div>
      <?php else: ?>
      <div class="kg-table-wrap">
        <table class="kg-table">
          <thead>
            <tr><th>Level</th><th>Name</th><th>Keys</th><th>Plans</th><th class="text-end" data-nosort>Actions</th></tr>
          </thead>
          <tbody>
          <?php foreach ($levels as $l):
            $num = (int) $l['level_number'];
            $kc  = (int) ($keyCounts[$num]  ?? 0);
            $pc  = (int) ($planCounts[$num] ?? 0);
          ?>
            <tr>
              <td>
                <span class="kg-badge kg-badge-info"><span class="dot"></span>Level <?= $num ?></span>
                <?php if ((int) ($l['is_admin_only'] ?? 0) === 1): ?>
                  <span class="kg-badge kg-badge-banned"><span class="dot"></span>admin-only</span>
                <?php endif; ?>
              </td>
              <td class="fw-semibold" style="color:var(--kg-text)"><?= esc($l['name']) ?></td>
              <td><?= $kc ?></td>
              <td><?= $pc ?></td>
              <td class="text-end">
                <span class="kg-actions">
                <button type="button" class="kg-act kg-act-edit" title="Edit"
                        data-kg-edit-level="<?= (int) $num ?>"
                        data-name="<?= esc((string) $l['name'], 'attr') ?>"
                        data-admin-only="<?= (int) ($l['is_admin_only'] ?? 0) ?>">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                </button>
                <form method="post" action="/admin/levels/delete/<?= $l['id'] ?>"
                      data-confirm-title="Delete this level?"
                      data-confirm-text="Level <?= $num ?> (<?= esc($l['name']) ?>) will be removed. Keys and sellers using it will be set to 'No level', and this level's plans will be deleted.">
                  <?= csrf_field() ?>
                  <button type="submit" class="kg-act kg-act-delete" title="Delete"><?= kg_icon('trash', 15) ?></button>
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
  </div>
</div>

<script {csp-script-nonce}>
function kgEditLevel(num, name, adminOnly){
  document.getElementById('kg-level-number').value = num;
  document.getElementById('kg-level-name').value = name;
  document.getElementById('kg-level-adminonly').checked = (adminOnly === 1);
  document.getElementById('kg-level-name').focus();
}

// Delegated wiring (inline onclick= is not allowed under a nonce CSP).
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-kg-edit-level]');
  if (!b) return;
  kgEditLevel(
    parseInt(b.getAttribute('data-kg-edit-level'), 10),
    b.getAttribute('data-name') || '',
    parseInt(b.getAttribute('data-admin-only'), 10)
  );
});
</script>

<?= $this->endSection() ?>