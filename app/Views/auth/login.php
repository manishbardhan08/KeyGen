<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · KeyVault</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/app.css') ?: time() ?>">
<script src="<?= base_url('assets/js/app.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/app.js') ?: time() ?>"></script>
</head>
<body>
<div class="login-wrap">
  <button type="button" class="login-theme-toggle" id="login-theme-toggle" aria-label="Toggle dark mode" title="Toggle theme">
    <svg class="i-sun" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m4.93 19.07 1.41-1.41"/><path d="m17.66 6.34 1.41-1.41"/></svg>
    <svg class="i-moon" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
  </button>
  <div class="login-glow a"></div>
  <div class="login-glow b"></div>

  <!-- marketing panel (desktop) -->
  <div class="login-hero kg-fade">
    <div class="hero-badge">
      <span class="dot"></span> Next-Gen Software Protection
    </div>
    <h2 class="hero-title">Protect your software with <span class="g">elegant</span> license keys.</h2>
    <p class="hero-sub">Generate, distribute and validate keys across devices — with HWID binding, real-time validation and a clean control panel.</p>
    <div class="hero-features">
      <div class="hero-feature">
        <div class="tile"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
        <div><div class="ft">HWID binding</div><div class="fd">Each key locks to a device on first activation.</div></div>
      </div>
      <div class="hero-feature">
        <div class="tile"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></div>
        <div><div class="ft">Real-time API</div><div class="fd">Validate on every run with a single POST request.</div></div>
      </div>
      <div class="hero-feature">
        <div class="tile"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
        <div><div class="ft">Reseller network</div><div class="fd">Admins → sellers, each with their own scope.</div></div>
      </div>
    </div>
  </div>

  <!-- auth card -->
  <div class="login-card kg-fade">
    <div class="d-flex align-items-center gap-3 justify-content-center mb-4">
      <div class="kg-brand-mark" style="width:46px;height:46px;border-radius:13px">
        <svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="3.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/></svg>
      </div>
      <div>
        <div class="kg-brand-name" style="font-size:18px">KeyVault</div>
        <div class="kg-brand-sub">Secure Console</div>
      </div>
    </div>

    <h1 class="text-center fw-bold" style="font-size:22px;letter-spacing:-.03em;color:var(--kg-text);margin-bottom:4px">Welcome back</h1>
    <p class="text-center mb-4" style="font-size:13.5px;color:var(--kg-text-2)">Sign in to access your vault</p>

    <?php if (session()->getFlashdata('error')): ?>
      <div class="kg-flash kg-flash-error mb-4">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0 mt-1"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span><?= esc(session()->getFlashdata('error')) ?></span>
      </div>
    <?php endif; ?>

    <?php if (service('request')->getGet('timeout')): ?>
      <div class="kg-flash kg-flash-error mb-4">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0 mt-1"><circle cx="12" cy="12" r="10"/><polyline points="12 7 12 12 15 14"/></svg>
        <span>You were signed out due to inactivity. Please sign in again.</span>
      </div>
    <?php endif; ?>

    <form method="post" action="/login" class="d-grid gap-3">
      <?= csrf_field() ?>
      <div>
        <label class="kg-field-label">Username</label>
        <div class="field-ic">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input name="username" autocomplete="username" autofocus required class="form-control" placeholder="your-username">
        </div>
      </div>
      <div>
        <label class="kg-field-label">Password</label>
        <div class="field-ic has-toggle">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input id="pw" name="password" type="password" autocomplete="current-password" required class="form-control" placeholder="••••••••">
          <button type="button" class="toggle-pw" data-toggle-pw="pw" aria-label="Show password" aria-pressed="false" tabindex="-1">
            <svg class="i-eye" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="i-eye-off" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
          </button>
        </div>
      </div>
      <div class="login-meta mt-1">
        <label class="d-flex align-items-center gap-2" style="cursor:pointer">
          <input type="checkbox" name="remember" value="1" class="form-check-input mt-0"> Remember me
        </label>
      </div>
      <button class="btn btn-kg-primary w-100" style="padding:12px">Sign in →</button>
    </form>

    <p class="text-center mb-0 mt-4" style="font-size:11.5px;color:var(--kg-text-3)">
      Protected by KeyVault · <span class="kg-mono">API v1</span>
    </p>
  </div>
</div>
</body>
</html>