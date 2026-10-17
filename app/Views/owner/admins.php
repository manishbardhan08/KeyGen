<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="kg-hero kg-fade">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
    <div>
      <div class="kg-hero-eyebrow">Vault Owner</div>
      <h1 class="kg-page-title">Admins</h1>
      <p class="kg-page-sub mb-0">Create and manage admin accounts.</p>
    </div>
    <span class="kg-badge kg-badge-neutral"><span class="dot"></span><?= count($admins) ?> total</span>
  </div>
</div>

<div class="card kg-card-pad mb-4">
  <div class="kg-card-head">
    <div class="kg-icon-tile"><?= kg_icon('plus', 17) ?></div>
    <div>
      <h3 class="kg-card-title">Create Admin</h3>
      <p class="kg-card-sub">API key is auto-assigned on creation.</p>
    </div>
  </div>
  <form method="post" action="/owner/admins/create" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-12 col-md-6"><input name="name" placeholder="Full name" required class="form-control"></div>
    <div class="col-12 col-md-6"><input name="username" placeholder="Username" required class="form-control"></div>
    <div class="col-12 col-md-6"><input name="password" type="text" placeholder="Password" required class="form-control"></div>
    <div class="col-12 col-md-6"><input name="duration" type="number" min="1" max="36500" placeholder="Duration (days)" required class="form-control"></div>
    <div class="col-12"><input name="phone" placeholder="Phone (optional)" class="form-control"></div>
    <div class="col-12"><button class="btn btn-kg-primary w-100 mt-1">+ Create Admin</button></div>
  </form>
</div>

<div class="card overflow-hidden">
  <?php if (empty($admins)): ?>
    <div class="kg-empty">
      <div class="kg-icon-tile"><?= kg_icon('users', 22) ?></div>
      <p class="mb-1" style="color:var(--kg-text-2)">No admins yet</p>
      <p class="mb-0" style="font-size:12.5px;color:var(--kg-text-3)">Create your first admin above.</p>
    </div>
  <?php else: ?>
  <div class="kg-table-wrap">
    <table class="kg-table">
      <thead><tr><th>Name</th><th>Username</th><th>Phone</th><th>Expires</th><th>API Key</th><th class="text-end"></th></tr></thead>
      <tbody>
      <?php foreach ($admins as $a): ?>
        <tr>
          <td class="fw-semibold" style="color:var(--kg-text)"><?= esc($a['name']) ?></td>
          <td><?= esc($a['username']) ?></td>
          <td><?= esc($a['phone'] ?? '—') ?></td>
          <td><?= esc($a['expires_at'] ?? '—') ?></td>
          <td>
            <?php $__plain = decrypt_api_key($a['api_key_display'] ?? null); ?>
            <?php if ($__plain): ?>
              <button data-copy="<?= esc($__plain) ?>" class="kg-copy">
                <?= esc(substr($__plain, 0, 10)) ?>...<span data-copy-label style="color:var(--kg-text-3);font-size:10px">copy</span>
              </button>
            <?php else: ?>
              <span style="font-size:11px;color:var(--kg-text-3)">not set — regenerate</span>
            <?php endif; ?>
          </td>
          <td class="text-end">
            <span class="kg-actions">
            <button type="button" data-pw-open data-pw-id="<?= $a['id'] ?>" data-pw-user="<?= esc($a['username']) ?>"
                    class="kg-act kg-act-key" title="Change password">
              <?= kg_icon('key', 15) ?>
            </button>
            <form method="post" action="/owner/admins/delete/<?= $a['id'] ?>"
                  data-confirm-title="Delete this admin?"
                  data-confirm-text="@<?= esc($a['username']) ?>'s account, their sellers, keys and live sessions will be deleted. Continue?">
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

<script {csp-script-nonce}>
(function(){
  document.querySelectorAll('[data-pw-open]').forEach(btn => {
    btn.addEventListener('click', () => {
      const id   = btn.getAttribute('data-pw-id');
      const user = btn.getAttribute('data-pw-user');
      const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
      Swal.fire({
        title: 'Change password',
        html: `<div style="text-align:left;font-size:13px;color:var(--kg-text-3);margin-bottom:10px">
                 Setting a new password for <span style="font-family:var(--kg-mono,monospace);color:#7c6cff">${window.kgEsc(user)}</span>
               </div>
               <input id="kg-pw1" type="password" class="swal2-input" placeholder="New password" minlength="8">
               <input id="kg-pw2" type="password" class="swal2-input" placeholder="Confirm password" minlength="8">`,
        showCancelButton: true, confirmButtonText: 'Update password', cancelButtonText: 'Cancel',
        reverseButtons: true, focusConfirm: false,
        background: dark ? '#12131f' : '#fff', color: dark ? '#f1f2f9' : '#14152b',
        customClass: { popup: 'kg-swal-popup', confirmButton: 'kg-swal-primary', cancelButton: 'kg-swal-cancel' },
        buttonsStyling: false,
        preConfirm: () => {
          const p1 = document.getElementById('kg-pw1').value;
          const p2 = document.getElementById('kg-pw2').value;
          if (!p1 || p1.length < 8) { Swal.showValidationMessage('Password must be at least 8 characters'); return false; }
          if (p1 !== p2)            { Swal.showValidationMessage('Passwords do not match'); return false; }
          return { p1, p2 };
        }
      }).then(r => {
        if (!r.isConfirmed) return;
        const form = document.createElement('form');
        form.method = 'post'; form.action = '/owner/admins/password/' + id;
        const csrf = document.createElement('input'); csrf.name = '<?= csrf_token() ?>'; csrf.value = '<?= csrf_hash() ?>';
        const f1 = document.createElement('input'); f1.name = 'new_password';     f1.value = r.value.p1;
        const f2 = document.createElement('input'); f2.name = 'confirm_password'; f2.value = r.value.p2;
        form.appendChild(csrf); form.appendChild(f1); form.appendChild(f2);
        document.body.appendChild(form); form.submit();
      });
    });
  });
})();
</script>

<?= $this->endSection() ?>