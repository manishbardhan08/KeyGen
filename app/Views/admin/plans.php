<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="kg-hero kg-fade">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <div class="kg-hero-eyebrow">Admin Console</div>
      <h1 class="kg-page-title">Key Plans</h1>
      <p class="kg-page-sub mb-0">Set the durations your sellers can sell, and the balance price for each.</p>
    </div>
    <a href="/admin/levels" class="btn btn-kg-ghost"><?= kg_icon('layers', 15) ?> Levels</a>
  </div>
</div>

<div class="row g-4">
  <!-- add / update a plan -->
  <div class="col-12 col-lg-5">
    <div class="card kg-card-pad h-100">
      <div class="kg-card-head">
        <div class="kg-icon-tile kg-tile-primary"><?= kg_icon('plus', 17) ?></div>
        <div>
          <h3 class="kg-card-title">Add / Update Plan</h3>
          <p class="kg-card-sub">Saving the same <b>duration + level</b> updates its price. Different level = different plan.</p>
        </div>
      </div>
      <form method="post" action="/admin/plans/save" class="d-grid gap-3">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-6">
            <label class="kg-field-label">Duration (hours)</label>
            <input name="duration_hours" type="number" min="1" max="87600" value="720" required class="form-control">
          </div>
          <div class="col-6">
            <label class="kg-field-label">Price (balance)</label>
            <input name="price" type="number" min="0" value="5" required class="form-control">
          </div>
        </div>
        <div>
          <label class="kg-field-label">Level this plan sells</label>
          <select name="level_number" class="form-select">
            <option value="0">No level</option>
            <?php foreach ($levels as $l): if ((int) ($l['is_admin_only'] ?? 0) === 1) { continue; } ?>
              <option value="<?= (int) $l['level_number'] ?>">Level <?= (int) $l['level_number'] ?> · <?= esc($l['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="mt-1 mb-0" style="font-size:11px;color:var(--kg-text-3)">
            Keys sold through this plan carry this level.
            <?php if (empty($levels)): ?>
              <a href="/admin/levels" class="fw-semibold">Create levels first →</a>
            <?php endif; ?>
          </p>
        </div>
        <label class="d-flex align-items-center gap-2" style="font-size:13px;color:var(--kg-text-2);cursor:pointer">
          <input type="checkbox" name="is_active" class="form-check-input mt-0" checked> Active (sellers can buy this)
        </label>
        <button class="btn btn-kg-primary w-100 mt-1">Save plan</button>
      </form>

      <div class="kg-divider"></div>
      <p class="mb-2" style="font-size:12px;color:var(--kg-text-3)">
        Quick add:
      </p>
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ([1, 6, 24, 168, 720] as $h): ?>
          <button type="button" class="kg-pill" onclick="kgFillPlan(<?= $h ?>)"><?= esc(kg_duration($h, true)) ?></button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- current plans -->
  <div class="col-12 col-lg-7">
    <div class="card overflow-hidden h-100">
      <div class="d-flex align-items-center justify-content-between px-4 py-3" style="border-bottom:1px solid var(--kg-border)">
        <div>
          <h3 class="kg-card-title">Your Plans</h3>
          <p class="kg-card-sub mb-0"><?= (int) $sellerCount ?> seller(s) will see these prices.</p>
        </div>
        <span class="text-uppercase" style="font-size:11px;letter-spacing:.1em;color:var(--kg-text-3)"><?= count($plans) ?> total</span>
      </div>

      <?php if (empty($plans)): ?>
        <div class="kg-empty">
          <div class="kg-icon-tile kg-tile-warning"><?= kg_icon('coin', 22) ?></div>
          <p class="mb-1" style="color:var(--kg-text-2)">No plans yet</p>
          <p class="mb-0" style="font-size:12.5px;color:var(--kg-text-3)">Add your first duration on the left. Until you do, your sellers can't generate keys.</p>
        </div>
      <?php else: ?>
      <div class="kg-table-wrap">
        <table class="kg-table">
          <thead>
            <tr><th>Duration</th><th>Level</th><th>Price</th><th>Status</th><th class="text-end" data-nosort>Actions</th></tr>
          </thead>
          <tbody>
          <?php
            $levelNames = [];
            foreach ($levels as $l) { $levelNames[(int) $l['level_number']] = $l['name']; }
          ?>
          <?php foreach ($plans as $p):
            $pn = (int) ($p['level_number'] ?? 0);
          ?>
            <tr>
              <td class="fw-semibold" style="color:var(--kg-text)"><?= esc(kg_duration((int) ($p['duration_hours'] ?? 0), true)) ?></td>
              <td>
                <?php if ($pn > 0): ?>
                  <span class="kg-badge kg-badge-info"><span class="dot"></span>L<?= $pn ?><?= isset($levelNames[$pn]) ? ' · ' . esc($levelNames[$pn]) : '' ?></span>
                <?php else: ?>
                  <span style="color:var(--kg-text-3)">—</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="d-inline-flex align-items-center gap-1 fw-semibold" style="color:var(--kg-success);font-size:13px">
                  <?= kg_icon('coin', 13) ?> <?= (int) $p['price'] ?>
                </span>
              </td>
              <td>
                <?php if ((int) $p['is_active'] === 1): ?>
                  <span class="kg-badge kg-badge-active"><span class="dot"></span>active</span>
                <?php else: ?>
                  <span class="kg-badge kg-badge-neutral"><span class="dot"></span>disabled</span>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <span class="kg-actions">
                <button type="button" class="kg-act kg-act-edit" title="Edit"
                        onclick="kgEditPlan(<?= (int) ($p['duration_hours'] ?? 0) ?>, <?= (int) $p['price'] ?>, <?= (int) $p['is_active'] ?>, <?= (int) ($p['level_number'] ?? 0) ?>)">
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                </button>
                <form method="post" action="/admin/plans/delete/<?= $p['id'] ?>"
                      data-confirm-title="Delete this plan?"
                      data-confirm-text="The <?= esc(kg_duration((int) ($p['duration_hours'] ?? 0), true)) ?> plan will be removed. Sellers will no longer be able to buy it.">
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

<script>
function kgFillPlan(hours){
  const f = document.querySelector('form[action$="/plans/save"]');
  f.querySelector('[name=duration_hours]').value = hours;
  f.querySelector('[name=duration_hours]').focus();
}
function kgEditPlan(hours, price, active, level){
  const f = document.querySelector('form[action$="/plans/save"]');
  f.querySelector('[name=duration_hours]').value = hours;
  f.querySelector('[name=price]').value = price;
  f.querySelector('[name=is_active]').checked = active === 1;
  if (f.querySelector('[name=level_number]')) f.querySelector('[name=level_number]').value = level || 0;
  f.scrollIntoView({behavior:'smooth', block:'center'});
}
</script>

<?= $this->endSection() ?>