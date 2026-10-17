<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="kg-hero kg-fade">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <div class="kg-hero-eyebrow">Seller Panel</div>
      <h1 class="kg-page-title">Generate Keys</h1>
      <p class="kg-page-sub mb-0">Pick a plan from your admin — balance is debited per key.</p>
    </div>
    <div class="text-end">
      <div class="text-uppercase" style="font-size:11px;letter-spacing:.1em;color:var(--kg-text-3)">Balance</div>
      <div class="fw-bold d-inline-flex align-items-center gap-1" style="font-size:22px;color:var(--kg-success)">
        <?= kg_icon('coin', 17) ?> <span id="kg-balance"><?= (int) $balance ?></span>
      </div>
    </div>
  </div>
</div>

<?php if (empty($plans)): ?>
  <div class="card kg-card-pad">
    <div class="kg-empty">
      <div class="kg-icon-tile kg-tile-warning"><?= kg_icon('coin', 26) ?></div>
      <p class="mb-1 fw-semibold" style="color:var(--kg-text)">No plans available yet</p>
      <p class="mb-0" style="font-size:13px;color:var(--kg-text-2)">
        Your admin hasn't assigned you a sellable level (or added a plan for it) yet.
        Please ask them to grant you a level, then come back here to generate keys.
      </p>
    </div>
  </div>
<?php else: ?>

<div class="card kg-card-pad mb-4">
  <div class="kg-card-head">
    <div class="kg-icon-tile kg-tile-primary"><?= kg_icon('spark', 17) ?></div>
    <div>
      <h3 class="kg-card-title">New Batch</h3>
      <p class="kg-card-sub">Choose a plan, set how many keys, and generate. The tag is optional — key length stays the same.</p>
    </div>
  </div>
  <form method="post" action="/seller/keys/generate" id="kg-plan-form" class="row g-3 align-items-end">
    <?= csrf_field() ?>
    <div class="col-12">
      <div class="row g-3">
        <div class="col-12 col-sm-4">
          <label class="kg-field-label">Key prefix</label>
          <input class="form-control kg-mono" value="<?= esc(session('username')) ?>" readonly style="background:var(--kg-surface-2)">
        </div>
        <div class="col-12 col-sm-4">
          <label class="kg-field-label">Custom tag <span style="font-weight:400;color:var(--kg-text-3)">(optional)</span></label>
          <input name="key_tag" id="kg-tag" maxlength="16" class="form-control kg-mono" placeholder="CUSTOMER / BATCH">
        </div>
        <div class="col-12 col-sm-4">
          <label class="kg-field-label">Preview</label>
          <input id="kg-prev" readonly class="form-control kg-mono" value="—" style="background:var(--kg-surface-2)">
        </div>
      </div>
      <p class="mt-2 mb-1" style="font-size:11.5px;color:var(--kg-text-3)">
        The prefix is always your username. Tag is optional (A–Z, 0–9) — total key length stays the same.
      </p>
    </div>
    <div class="col-12 col-md-3">
      <label class="kg-field-label">Duration</label>
      <select name="duration" id="kg-duration" class="form-select" required></select>
    </div>
    <div class="col-12 col-md-3">
      <label class="kg-field-label">Plan</label>
      <select name="level" id="kg-level" class="form-select" required></select>
      <p class="mt-1 mb-0" style="font-size:11px;color:var(--kg-text-3)">
        <?php if (($sellerLevels ?? []) !== []): ?>
          You can sell:
          <b><?php
            $labels = [];
            foreach ($sellerLevels as $lv) {
                $labels[] = 'Level ' . (int) $lv . (isset($levelNames[$lv]) ? ' · ' . esc($levelNames[$lv]) : '');
            }
            echo implode(', ', $labels);
          ?></b>.
        <?php else: ?>
          No levels assigned yet — ask your admin.
        <?php endif; ?>
      </p>
    </div>
    <div class="col-6 col-md-2">
      <label class="kg-field-label">How many keys?</label>
      <input name="count" id="kg-count" type="number" min="1" max="50" value="1" class="form-control">
    </div>
    <div class="col-6 col-md-2">
      <label class="kg-field-label">Devices per key</label>
      <input name="devices" id="kg-devices" type="number" min="1" max="100" value="1" class="form-control">
      <p class="mt-1 mb-0" style="font-size:11px;color:var(--kg-text-3)">Devices this key may activate on.</p>
    </div>
    <div class="col-6 col-md-2">
      <label class="kg-field-label">Total cost</label>
      <div class="form-control d-flex align-items-center" style="background:var(--kg-surface-2)">
        <span class="d-inline-flex align-items-center gap-1 fw-bold" style="color:var(--kg-success)">
          <?= kg_icon('coin', 15) ?> <span id="kg-total">0</span>
        </span>
      </div>
    </div>
    <div class="col-12">
      <button class="btn btn-kg-primary w-100 mt-1" id="kg-submit">✦ Generate</button>
      <p class="mt-2 mb-0 text-center" style="font-size:12px;color:var(--kg-text-3)" id="kg-hint"></p>
    </div>
  </form>
</div>

<script>
(function(){
  const balance = <?= (int) $balance ?>;
  const levelNames = <?= kg_json($levelNames ?? new stdClass()) ?>;
  const plans = <?= kg_json($plansJs ?? []) ?>;

  const durSel  = document.getElementById('kg-duration');
  const lvlSel  = document.getElementById('kg-level');
  const countEl = document.getElementById('kg-count');
  const totalEl = document.getElementById('kg-total');
  const hintEl  = document.getElementById('kg-hint');
  const btn     = document.getElementById('kg-submit');
  const form    = document.getElementById('kg-plan-form');

  const levelLabel = (lv) => lv > 0 ? ('Level ' + lv + (levelNames[lv] ? ' · ' + levelNames[lv] : '')) : 'No level';
  const durLabel = (h) => h > 0 ? (h % 24 === 0 ? (h / 24) + ((h / 24) === 1 ? ' day' : ' days') : h + ' hours') : '—';

  // unique durations (hours, ascending)
  const durations = [...new Set(plans.map(p => p.hours))].sort((a,b) => a-b);

  // plans available for the current duration, sorted by level
  function plansFor(hours){ return plans.filter(p => p.hours === hours).sort((a,b) => a.level - b.level); }

  function fillDurations(){
    durSel.innerHTML = '';
    durations.forEach(d => {
      const o = document.createElement('option');
      o.value = d; o.textContent = durLabel(d);
      durSel.appendChild(o);
    });
  }

  function fillLevels(){
    const hours = parseInt(durSel.value || '0', 10);
    const options = plansFor(hours);
    lvlSel.innerHTML = '';
    options.forEach(p => {
      const o = document.createElement('option');
      o.value = p.level;
      o.textContent = levelLabel(p.level) + ' · ' + p.price + ' balance';
      o.dataset.price = p.price;
      o.dataset.planId = p.id;
      lvlSel.appendChild(o);
    });
    recalc();
  }

  function selectedPlan(){
    const hours = parseInt(durSel.value || '0', 10);
    const level = parseInt(lvlSel.value || '0', 10);
    return plans.find(p => p.hours === hours && p.level === level) || null;
  }

  function recalc(){
    const plan  = selectedPlan();
    const count = Math.max(1, Math.min(50, parseInt(countEl.value || '1', 10)));
    const price = plan ? plan.price : 0;
    const total = price * count;

    totalEl.textContent = total;

    const affordable = plan && total <= balance;
    btn.disabled = !affordable;
    btn.classList.toggle('opacity-50', !affordable);

    if (!plan) {
      hintEl.textContent = 'This duration + level combination is not available.';
      hintEl.style.color = 'var(--kg-danger)';
      return;
    }
    hintEl.textContent = affordable
      ? count + ' × ' + durLabel(plan.hours) + ' · ' + levelLabel(plan.level) + ' key(s) · ' + total + ' balance will be deducted'
      : 'Not enough balance — need ' + total + ', you have ' + balance + '.';
    hintEl.style.color = affordable ? 'var(--kg-text-3)' : 'var(--kg-danger)';
  }

  durSel.addEventListener('change', fillLevels);
  lvlSel.addEventListener('change', recalc);
  countEl.addEventListener('input', recalc);

  // submit the resolved plan id (server still re-validates it)
  form.addEventListener('submit', function(){
    const plan = selectedPlan();
    if (!plan) return;
    const hid = document.createElement('input');
    hid.type = 'hidden'; hid.name = 'plan_id'; hid.value = plan.id;
    form.appendChild(hid);
  });

  fillDurations();
  fillLevels();
})();

// key tag live preview (prefix = username, constant length)
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

<?php endif; ?>

<div class="card overflow-hidden">
  <div class="d-flex align-items-center justify-content-between px-4 py-3" style="border-bottom:1px solid var(--kg-border)">
    <h3 class="kg-card-title">Recent Keys</h3>
    <a href="/seller/keys/list" class="text-decoration-none fw-semibold" style="font-size:12.5px">See all →</a>
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
      <thead><tr><th>Key</th><th>Duration</th><th>Level</th><th>Status</th><th>Expires</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($keys, 0, 5) as $k):
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
          <td><?= esc(kg_duration((int) ($k['duration_hours'] ?? 0))) ?></td>
          <td>
            <?php if ($kl > 0): ?>
              <span class="kg-badge kg-badge-info"><span class="dot"></span>L<?= $kl ?><?= isset($levelNames[$kl]) ? ' · ' . esc($levelNames[$kl]) : '' ?></span>
            <?php else: ?>
              <span style="color:var(--kg-text-3)">—</span>
            <?php endif; ?>
          </td>
          <td><span class="kg-badge <?= $badge ?>"><span class="dot"></span><?= esc($statusLabel) ?></span></td>
          <td <?= $expired ? 'style="color:var(--kg-warning);font-weight:600"' : '' ?>><?= esc($k['expires_at'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?= $this->endSection() ?>