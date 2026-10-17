<?php
$role  = session('role') ?? 'guest';
$name  = session('name') ?? 'User';
$path  = trim(service('uri')->getPath(), '');

// nav
$navItems = [];
if ($role === 'owner') {
    $navItems = [
        'Overview' => [['/owner/dashboard', 'grid', 'Dashboard']],
        'Manage'   => [['/owner/admins', 'users', 'Admins']],
    ];
} elseif ($role === 'admin') {
    $navItems = [
        'Overview' => [['/admin/dashboard', 'grid', 'Dashboard']],
        'Manage'   => [
            ['/admin/sellers',   'users', 'Sellers'],
            ['/admin/keys',      'spark', 'Generate Keys'],
            ['/admin/keys/list', 'key',   'All Keys'],
            ['/admin/plans',     'coin',  'Key Plans'],
            ['/admin/levels',    'layers','Levels'],
        ],
        'Developer' => [
            ['/admin/docs', 'shield', 'API Docs'],
        ],
    ];
} elseif ($role === 'seller') {
    $navItems = [
        'Overview' => [['/seller/dashboard', 'grid', 'Dashboard']],
        'Manage'   => [
            ['/seller/keys',      'spark', 'Generate Keys'],
            ['/seller/keys/list', 'key',   'All Keys'],
        ],
    ];
}
if ($role !== 'guest') {
    $navItems['Account'] = [['/' . $role . '/profile', 'user', 'Profile']];
}

// breadcrumb title
$pageTitle = match (true) {
    str_contains($path, 'docs')      => 'API Docs',
    str_contains($path, 'generate')  => 'Generate Keys',
    str_contains($path, 'dashboard') => 'Dashboard',
    str_contains($path, 'profile')   => 'Profile',
    str_contains($path, 'admins')    => 'Admins',
    str_contains($path, 'sellers')   => 'Sellers',
    str_contains($path, 'plans')     => 'Key Plans',
    str_contains($path, 'levels')    => 'License Levels',
    str_contains($path, 'list')      => 'All Keys',
    str_contains($path, 'keys')      => 'Keys',
    default                          => 'Panel',
};
$roleLabel = ucfirst($role);
$idleMinutes = (int) (config('App')->autoLogoutMinutes ?? 30);
?>
<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($pageTitle) ?> · KeyVault</title>

<meta name="csrf-token" content="<?= csrf_hash() ?>" data-token-name="<?= csrf_token() ?>">
<meta name="kg-idle-minutes" content="<?= (int) $idleMinutes ?>">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/app.css') ?: time() ?>">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
window.__KG_FLASH__ = {
  error: <?= kg_json(session()->getFlashdata('error') ?: null) ?>,
  success: <?= kg_json(session()->getFlashdata('success') ?: null) ?>,
  new_api_key: <?= kg_json(session()->getFlashdata('new_api_key') ?: null) ?>
};
</script>
<script src="<?= base_url('assets/js/app.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/app.js') ?: time() ?>"></script>
</head>
<body>
<div class="kg-shell">

  <!-- ================= SIDEBAR ================= -->
  <aside class="kg-sidebar" id="kg-sidebar">
    <div class="kg-brand">
      <div class="kg-brand-mark">
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="3.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/></svg>
      </div>
      <div>
        <div class="kg-brand-name">KeyVault</div>
        <div class="kg-brand-sub">License System</div>
      </div>
    </div>

    <nav class="kg-nav">
      <?php foreach ($navItems as $label => $items): ?>
        <div class="kg-nav-label"><?= esc($label) ?></div>
        <?php foreach ($items as [$href, $ic, $lbl]):
            $t = trim($href, '/');
            $isActive = $path === $t
                     || ($lbl === 'Dashboard' && str_contains($path, 'dashboard'))
                     || ($lbl === 'Admins'    && str_contains($path, 'admins'))
                     || ($lbl === 'Sellers'   && str_contains($path, 'sellers'))
                     || ($lbl === 'All Keys'  && str_contains($path, 'keys/list'))
                     || ($lbl === 'Profile'   && str_contains($path, 'profile'));
        ?>
          <a href="<?= esc($href) ?>" class="kg-nav-item <?= $isActive ? 'active' : '' ?>">
            <?= kg_icon($ic, 17) ?>
            <span><?= esc($lbl) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>

    <div class="kg-sidebar-foot">
      <div class="kg-userchip">
        <div class="kg-avatar"><?= esc(strtoupper(substr($name, 0, 1))) ?></div>
        <div class="flex-grow-1 min-w-0" style="min-width:0">
          <div class="text-truncate fw-semibold" style="font-size:13px;color:var(--kg-text)"><?= esc($name) ?></div>
          <div class="text-truncate text-uppercase" style="font-size:10px;letter-spacing:.1em;color:var(--kg-text-3)"><?= esc($role) ?></div>
        </div>
        <a href="/logout" class="text-decoration-none" style="color:var(--kg-text-3)" title="Logout"
           onclick="event.preventDefault();document.getElementById('kg-logout-form').submit();">
          <?= kg_icon('out', 16) ?>
        </a>
      </div>
    </div>
  </aside>

  <!-- ================= MAIN ================= -->
  <div class="kg-main">

    <!-- header -->
    <header class="kg-header">
      <button type="button" class="kg-icon-btn kg-burger" data-kg-burger aria-label="Open menu">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>

      <div class="kg-breadcrumb">
        <span class="root"><?= esc($roleLabel) ?></span>
        <span class="sep">/</span>
        <span class="cur text-truncate"><?= esc($pageTitle) ?></span>
      </div>

      <div class="kg-spacer"></div>

      <!-- search (admin/seller only) -->
      <div class="kg-search kg-search-wrap" data-kg-search>
        <span class="ic">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </span>
        <input type="text" placeholder="Search keys..." data-search>
        <span class="kbd">⌘K</span>
      </div>

      <!-- clock -->
      <div class="kg-clock">
        <span class="dot"></span>
        <span id="kg-clock">--:--:--</span>
      </div>

      <!-- theme toggle -->
      <button type="button" class="kg-icon-btn" title="Toggle theme" data-theme-toggle>
        <svg class="i-sun" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m4.93 19.07 1.41-1.41"/><path d="m17.66 6.34 1.41-1.41"/></svg>
        <svg class="i-moon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </button>

      <!-- avatar + menu -->
      <div class="position-relative" data-user-menu>
        <button type="button" class="kg-avatarpill" data-user-trigger aria-haspopup="true" aria-expanded="false">
          <span class="kg-avatar kg-avatar-sm"><?= esc(strtoupper(substr($name, 0, 1))) ?></span>
          <span class="nm"><?= esc(strtolower(explode(' ', $name)[0])) ?></span>
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--kg-text-3)"><polyline points="6 9 12 15 18 9"/></svg>
        </button>

        <div class="kg-menu" data-user-panel role="menu">
          <div class="kg-menu-head">
            <div class="kg-avatar"><?= esc(strtoupper(substr($name, 0, 1))) ?></div>
            <div class="min-w-0" style="min-width:0">
              <div class="text-truncate fw-semibold" style="font-size:13px;color:var(--kg-text)"><?= esc($name) ?></div>
              <div class="text-truncate text-uppercase" style="font-size:10.5px;letter-spacing:.1em;color:var(--kg-text-3)"><?= esc($role) ?></div>
            </div>
          </div>
          <div class="kg-menu-sep"></div>
          <a href="/<?= esc($role) ?>/profile" class="kg-menu-item" role="menuitem">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span>Profile</span>
          </a>
          <div class="kg-menu-sep"></div>
          <a href="/logout" class="kg-menu-item danger" role="menuitem"
             onclick="event.preventDefault();document.getElementById('kg-logout-form').submit();">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            <span>Logout</span>
          </a>
        </div>
      </div>
    </header>

    <!-- content -->
    <main class="kg-content kg-fade">
      <?php if (session()->getFlashdata('error')): ?>
        <div class="kg-flash kg-flash-error">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0 mt-1"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <span><?= esc(session()->getFlashdata('error')) ?></span>
        </div>
      <?php endif; ?>
      <?php if (session()->getFlashdata('success')): ?>
        <div class="kg-flash kg-flash-success">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0 mt-1"><polyline points="20 6 9 17 4 12"/></svg>
          <span><?= esc(session()->getFlashdata('success')) ?></span>
        </div>
      <?php endif; ?>
      <?= $this->renderSection('content') ?>
    </main>
  </div>
</div>

<form id="kg-logout-form" method="post" action="/logout" style="display:none"><?= csrf_field() ?></form>
</body>
</html>