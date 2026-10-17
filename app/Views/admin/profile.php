<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<?php
  $expiresRaw = $user['expires_at'] ?? null;
  $expired    = $expiresRaw && strtotime($expiresRaw) < time();
  $swOn       = (int) ($user['software_enabled'] ?? 1) === 1;
  $initial    = strtoupper(substr((string) ($user['name'] ?: $user['username']), 0, 1));

  $cards = [
    ['Total Keys',  $totalKeys,    'key',   'kg-tile-primary'],
    ['Unused Keys', $unusedKeys,   'spark', 'kg-tile-info'],
    ['Sellers',     $totalSellers, 'users', 'kg-tile-success'],
  ];
?>

<!-- ============ HERO ============ -->
<div class="kg-hero kg-fade">
  <div class="d-flex align-items-center gap-3 flex-wrap">
    <div class="kg-avatar" style="width:58px;height:58px;font-size:22px;border-radius:16px"><?= esc($initial) ?></div>
    <div class="flex-grow-1" style="min-width:0">
      <div class="kg-hero-eyebrow">Admin Account</div>
      <h1 class="kg-page-title text-truncate"><?= esc($user['name']) ?></h1>
      <p class="kg-page-sub mb-0">
        <span class="kg-mono">@<?= esc($user['username']) ?></span>
        <span style="color:var(--kg-text-3)"> · <?= esc(ucfirst($user['role'])) ?></span>
      </p>
    </div>
    <div class="d-flex flex-column align-items-end gap-2">
      <span class="kg-badge <?= $swOn ? 'kg-badge-active' : 'kg-badge-inactive' ?>"
            data-kg-sw-badge data-label-on="software online" data-label-off="software offline">
        <span class="dot"></span><?= $swOn ? 'software online' : 'software offline' ?>
      </span>
      <span class="kg-badge <?= $expired ? 'kg-badge-inactive' : 'kg-badge-neutral' ?>">
        <span class="dot"></span><?= $expired ? 'account expired' : 'expires ' . esc($expiresRaw ?? 'never') ?>
      </span>
    </div>
  </div>
</div>

<!-- ============ STATS ============ -->
<div class="row g-3 mb-4">
  <?php foreach ($cards as [$label, $value, $ic, $tile]): ?>
    <div class="col-12 col-sm-4">
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
  <!-- ============ ACCOUNT ============ -->
  <div class="col-12 col-lg-6">
    <div class="card kg-card-pad h-100">
      <div class="kg-card-head">
        <div class="kg-icon-tile"><?= kg_icon('user', 17) ?></div>
        <div>
          <h3 class="kg-card-title">Account</h3>
          <p class="kg-card-sub">Your identity &amp; contact details.</p>
        </div>
      </div>

      <div class="mb-2">
        <div class="kg-row"><span class="k">Username</span><span class="v kg-mono"><?= esc($user['username']) ?></span></div>
        <div class="kg-row"><span class="k">Role</span><span class="v"><?= esc(ucfirst($user['role'])) ?></span></div>
        <div class="kg-row">
          <span class="k">Expires</span>
          <span class="v"><?= $expired ? '<span style="color:var(--kg-danger)">Expired</span>' : esc($expiresRaw ?? 'Never') ?></span>
        </div>
        <div class="kg-row"><span class="k">Member since</span><span class="v"><?= esc($user['created_at'] ?? '—') ?></span></div>
      </div>

      <div class="kg-divider"></div>

      <form method="post" action="/admin/profile/update" class="d-grid gap-3">
        <?= csrf_field() ?>
        <div>
          <label class="kg-field-label">Full name</label>
          <input name="name" type="text" value="<?= esc($user['name']) ?>" required class="form-control">
        </div>
        <div>
          <label class="kg-field-label">Phone number</label>
          <input name="phone" type="text" value="<?= esc($user['phone'] ?? '') ?>" placeholder="+91 98765 43210" class="form-control">
        </div>
        <button class="btn btn-kg-primary w-100 mt-1">Save changes</button>
      </form>
    </div>
  </div>

  <!-- ============ PASSWORD ============ -->
  <div class="col-12 col-lg-6">
    <div class="card kg-card-pad h-100">
      <div class="kg-card-head">
        <div class="kg-icon-tile kg-tile-warning"><?= kg_icon('shield', 17) ?></div>
        <div>
          <h3 class="kg-card-title">Change Password</h3>
          <p class="kg-card-sub">Use at least 8 characters.</p>
        </div>
      </div>
      <form method="post" action="/admin/profile/password" class="d-grid gap-3">
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

  <!-- ============ API KEY ============ -->
  <div class="col-12 col-lg-6">
    <div class="card kg-card-pad h-100">
      <div class="kg-card-head">
        <div class="kg-icon-tile kg-tile-primary"><?= kg_icon('spark', 17) ?></div>
        <div>
          <h3 class="kg-card-title">API Key</h3>
          <p class="kg-card-sub">Authenticates your software against the licensing API.</p>
        </div>
      </div>

      <?php $__plain = decrypt_api_key($user['api_key_display'] ?? null); ?>
      <?php if ($__plain): ?>
        <div class="kg-apikey-box">
          <div class="kg-apikey-head">
            <span>Your key</span>
            <span class="d-flex gap-3">
              <button type="button" class="kg-apikey-copy" id="kg-apikey-toggle">reveal</button>
              <button type="button" data-copy="<?= esc($__plain) ?>" class="kg-apikey-copy">copy</button>
            </span>
          </div>
          <div class="kg-apikey-body" id="kg-apikey-body" data-key="<?= esc($__plain) ?>">••••••••••••••••••••••••</div>
        </div>
        <div class="kg-apikey-hint">keep it secret — anyone with this key can issue licenses as you</div>
      <?php else: ?>
        <div class="kg-apikey-empty">Key not visible yet — click <b>Regenerate Key</b> below to create &amp; view one</div>
      <?php endif; ?>

      <form method="post" action="/admin/profile/regen" class="mt-3"
            data-confirm-title="Regenerate API key?"
            data-confirm-text="The old key stops working immediately. Continue?">
        <?= csrf_field() ?>
        <button class="btn btn-kg-ghost w-100"><?= kg_icon('clock', 15) ?> Regenerate Key</button>
      </form>
    </div>
  </div>

  <!-- ============ PRODUCT ============ -->
  <div class="col-12 col-lg-6">
    <div class="card kg-card-pad h-100">
      <div class="kg-card-head">
        <div class="kg-icon-tile kg-tile-info"><?= kg_icon('bag', 17) ?></div>
        <div>
          <h3 class="kg-card-title">Product</h3>
          <p class="kg-card-sub">Your software / product name. Applies to all new keys.</p>
        </div>
      </div>
      <form method="post" action="/admin/profile/product" class="d-grid gap-3">
        <?= csrf_field() ?>
        <div>
          <label class="kg-field-label">Product name</label>
          <input name="product" type="text" required maxlength="100" value="<?= esc($user['product'] ?? '') ?>" placeholder="e.g. MyApp Pro" class="form-control">
        </div>
        <button class="btn btn-kg-primary w-100">Save product</button>
      </form>

      <div class="kg-divider"></div>
      <div class="kg-row" style="padding-top:0">
        <span class="k">Keys will carry</span>
        <span class="v kg-mono"><?= esc($user['product'] ?? '') !== '' ? esc($user['product']) : '—' ?></span>
      </div>
    </div>
  </div>

  <!-- ============ SOFTWARE ACCESS ============ -->
  <div class="col-12">
    <div class="card kg-card-pad">
      <div class="kg-card-head">
        <div class="kg-icon-tile <?= $swOn ? 'kg-tile-success' : 'kg-tile-danger' ?>"><?= kg_icon('shield', 17) ?></div>
        <div class="flex-grow-1">
          <h3 class="kg-card-title">Software Access</h3>
          <p class="kg-card-sub">Master switch — controls login for all your keys &amp; sellers.</p>
        </div>
        <span class="kg-badge <?= $swOn ? 'kg-badge-active' : 'kg-badge-inactive' ?>" id="kg-sw-badge"
              data-kg-sw-badge data-label-on="online" data-label-off="offline">
          <span class="dot"></span><?= $swOn ? 'online' : 'offline' ?>
        </span>
      </div>

      <form method="post" action="/admin/profile/software" id="kg-software-form"
            data-confirm-title="Save software access?"
            data-confirm-text="This changes whether your clients can log in.">
        <?= csrf_field() ?>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 p-3 rounded-3 mb-3"
             style="background:var(--kg-surface-2);border:1px solid var(--kg-border)">
          <div>
            <div class="fw-semibold" style="color:var(--kg-text)">Software login</div>
            <div style="font-size:12.5px;color:var(--kg-text-2)" id="kg-sw-hint">
              <?php if ($swOn): ?>
                Clients can authenticate normally.
              <?php else: ?>
                Clients get <span class="kg-mono">503</span> + your message below.
              <?php endif; ?>
            </div>
          </div>
          <div class="form-check form-switch m-0" style="font-size:1.35rem">
            <input class="form-check-input" type="checkbox" role="switch" name="software_enabled"
                   id="kg-sw" value="1" <?= $swOn ? 'checked' : '' ?>>
          </div>
        </div>

        <div class="row g-3 align-items-end">
          <div class="col-12 col-md-8">
            <label class="kg-field-label">Offline message (shown to clients when off)</label>
            <input name="offline_message" type="text" maxlength="255" class="form-control"
                   value="<?= esc($user['offline_message'] ?? '') ?>"
                   placeholder="Server off, kindly wait.">
            <p class="mt-1 mb-0" style="font-size:11px;color:var(--kg-text-3)">
              Leave blank to use the default: “Server off, kindly wait.”
            </p>
          </div>
          <div class="col-12 col-md-4">
            <button class="btn btn-kg-primary w-100">Save software access</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- ============ API DOCS ============ -->
  <div class="col-12">
    <div class="card kg-card-pad">
      <div class="kg-card-head">
        <div class="kg-icon-tile"><?= kg_icon('layers', 17) ?></div>
        <div>
          <h3 class="kg-card-title">Using Your API Key</h3>
          <p class="kg-card-sub">From your software, POST JSON to these endpoints.</p>
        </div>
        <div class="ms-auto d-flex gap-2">
          <a href="/admin/docs" class="btn btn-kg-primary"><?= kg_icon('shield', 15) ?> Full reference</a>
          <a href="/admin/levels" class="btn btn-kg-ghost">Manage levels →</a>
        </div>
      </div>
      <div class="row g-4">
        <div class="col-12 col-md-6">
          <div class="text-uppercase mb-2" style="font-size:11px;letter-spacing:.1em;color:var(--kg-text-3)">1 · Authenticate</div>
          <pre class="kg-code"><span class="mth">POST</span> /api/v1/auth
{
  <span class="key">"api_key"</span>:     "&lt;your key&gt;",
  <span class="key">"license_key"</span>: "XXXXX-XXXXX-XXXXX-XXXXX",
  <span class="key">"hwid"</span>:        "&lt;machine id&gt;",
  <span class="key">"product"</span>:     "&lt;your product name&gt;"
}</pre>
        </div>
        <div class="col-12 col-md-6">
          <div class="text-uppercase mb-2" style="font-size:11px;letter-spacing:.1em;color:var(--kg-text-3)">2 · Validate Each Run</div>
          <pre class="kg-code"><span class="mth">POST</span> /api/v1/validate
{
  <span class="key">"token"</span>: "&lt;token from step 1&gt;",
  <span class="key">"hwid"</span>:  "&lt;same hwid&gt;"
}</pre>
        </div>
        <div class="col-12">
          <div class="text-uppercase mb-2" style="font-size:11px;letter-spacing:.1em;color:var(--kg-text-3)">3 · Logout</div>
          <pre class="kg-code"><span class="mth">POST</span> /api/v1/logout  { <span class="key">"token"</span>: "&lt;token&gt;" }</pre>
        </div>
      </div>
    </div>
  </div>
</div>

<script {csp-script-nonce}>
(function(){
  var body   = document.getElementById('kg-apikey-body');
  var toggle = document.getElementById('kg-apikey-toggle');
  if (!body || !toggle) return;
  var key = body.getAttribute('data-key') || '';
  var mask = '•'.repeat(Math.min(28, Math.max(16, key.length || 24)));
  var shown = false;
  function paint(){
    body.textContent   = shown ? key : mask;
    toggle.textContent = shown ? 'hide' : 'reveal';
  }
  toggle.addEventListener('click', function(){ shown = !shown; paint(); });
  paint();
})();
</script>

<?= $this->endSection() ?>
