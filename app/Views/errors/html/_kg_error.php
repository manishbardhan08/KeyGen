<?php
/**
 * KeyGen — shared shell for the branded HTML error pages.
 *
 * This file is a PARTIAL: it is `require`d from the real error views
 * (error_400.php, error_403.php, error_404.php, error_429.php, production.php)
 * which set the variables below before including it. It is intentionally NOT
 * named `error_*.php`, so CodeIgniter's exception handler never picks it up
 * directly.
 *
 * Design notes
 * ------------
 *  - Everything (CSS included) is inline so the page still renders when the
 *    database, the assets bundle, or a CDN is unavailable — which is exactly
 *    when error pages matter most.
 *  - Dark mode follows the OS (`prefers-color-scheme`) because the panel's
 *    localStorage theme cannot be read without JavaScript, and error responses
 *    must stay script-free.
 *  - No internals are ever echoed: the exception message and stack are never
 *    printed, in any environment. (In development an uncaught 500 still lands
 *    on CodeIgniter's full trace page; a 404 simply means "no route".)
 *
 * Expected variables (all optional, sensible defaults applied below):
 *  @var string|null $kgCode    HTTP status shown big, e.g. "404"
 *  @var string|null $kgHeading Short human title, e.g. "Page not found"
 *  @var string|null $kgText    One-line explanation for the visitor
 *  @var string|null $kgTone    Accent: "primary" | "warning" | "danger" | "info"
 */

// The `url` helper is not autoloaded for the exception handler, so make sure
// base_url() exists before we lean on it.
if (! function_exists('base_url')) {
    helper('url');
}

$kgCode    = (string) ($kgCode    ?? '500');
$kgHeading = $kgHeading ?? 'Something went wrong';
$kgText    = $kgText    ?? 'Please try again in a moment.';
$kgTone    = $kgTone    ?? 'primary';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= esc($kgCode) ?> · KeyGen</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --kg-bg:#f5f6fb; --kg-surface:#ffffff; --kg-border:#e8e9f2;
  --kg-text:#12132a; --kg-text-2:#565872; --kg-text-3:#9092ab;
  --kg-primary:#5a4bf0; --kg-accent:#8b5cf6; --kg-ring:rgba(90,75,240,.16);
  --kg-shadow:0 24px 56px -24px rgba(18,19,42,.30);
}
@media (prefers-color-scheme: dark) {
  :root {
    --kg-bg:#08090f; --kg-surface:#101120; --kg-border:rgba(255,255,255,.08);
    --kg-text:#f0f1f8; --kg-text-2:#a2a4bd; --kg-text-3:#6c6e88;
    --kg-primary:#7c6cff; --kg-accent:#a78bfa; --kg-ring:rgba(124,108,255,.28);
    --kg-shadow:0 26px 60px -24px rgba(0,0,0,.85);
  }
}
* { box-sizing:border-box; }
html, body { min-height:100%; }
body {
  margin:0; min-height:100vh; display:grid; place-items:center; padding:24px;
  font-family:'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
  color:var(--kg-text); background:var(--kg-bg);
  -webkit-font-smoothing:antialiased; text-rendering:optimizeLegibility;
}
body::before {
  content:''; position:fixed; inset:0; z-index:-1; pointer-events:none;
  background:
    radial-gradient(720px 520px at 6% -12%, rgba(90,75,240,.16), transparent 60%),
    radial-gradient(680px 520px at 104% 104%, rgba(168,85,247,.12), transparent 60%);
}
.kg-err {
  width:100%; max-width:560px; text-align:center;
  background:var(--kg-surface); border:1px solid var(--kg-border); border-radius:24px;
  box-shadow:var(--kg-shadow); padding:48px 36px 34px;
}
.kg-err-brand { display:flex; align-items:center; justify-content:center; gap:11px; margin-bottom:26px; }
.kg-err-mark {
  width:44px; height:44px; border-radius:13px;
  display:flex; align-items:center; justify-content:center; color:#fff;
  background:linear-gradient(135deg, var(--kg-accent) 0%, var(--kg-primary) 55%, #3a2cbb 100%);
  box-shadow:0 10px 24px -10px var(--kg-ring), inset 0 1px 0 rgba(255,255,255,.35);
}
.kg-err-word { font-size:17px; font-weight:800; letter-spacing:-.03em; }
.kg-err-code {
  font-size:96px; font-weight:800; line-height:1; letter-spacing:-.05em; margin:4px 0 12px;
  font-variant-numeric:tabular-nums;
  background:linear-gradient(135deg, var(--kg-primary), var(--kg-accent));
  -webkit-background-clip:text; background-clip:text; color:transparent;
}
.kg-err[data-tone="warning"] .kg-err-code { background:linear-gradient(135deg,#f59e0b,#f97316); -webkit-background-clip:text; background-clip:text; }
.kg-err[data-tone="danger"]  .kg-err-code { background:linear-gradient(135deg,#ef4444,#e5484d); -webkit-background-clip:text; background-clip:text; }
.kg-err[data-tone="info"]    .kg-err-code { background:linear-gradient(135deg,#2b6de5,#5a4bf0); -webkit-background-clip:text; background-clip:text; }
.kg-err h1 { font-size:22px; letter-spacing:-.02em; margin:0 0 10px; }
.kg-err-text { margin:0 auto; max-width:42ch; font-size:14px; line-height:1.6; color:var(--kg-text-2); }
.kg-err-actions { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; margin-top:28px; }
.kg-err-btn {
  display:inline-flex; align-items:center; justify-content:center; gap:8px;
  padding:12px 22px; border-radius:12px; font-size:14px; font-weight:600;
  text-decoration:none; cursor:pointer; border:1px solid transparent;
  transition:transform .16s ease, box-shadow .16s ease, color .16s, border-color .16s;
}
.kg-err-btn-primary {
  color:#fff; background:linear-gradient(135deg, var(--kg-accent), var(--kg-primary) 60%);
  box-shadow:0 10px 24px -12px var(--kg-ring);
}
.kg-err-btn-primary:hover { transform:translateY(-1px); box-shadow:0 14px 30px -12px var(--kg-ring); }
.kg-err-btn-ghost { color:var(--kg-text-2); background:transparent; border-color:var(--kg-border); }
.kg-err-btn-ghost:hover { color:var(--kg-text); border-color:var(--kg-ring); }
.kg-err-foot { margin:30px 0 0; font-size:10.5px; font-weight:700; letter-spacing:.16em; text-transform:uppercase; color:var(--kg-text-3); }
@media (max-width:480px) { .kg-err { padding:38px 22px 28px; } .kg-err-code { font-size:72px; } }
</style>
</head>
<body>
    <main class="kg-err" data-tone="<?= esc($kgTone) ?>" role="main">
        <div class="kg-err-brand">
            <span class="kg-err-mark" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="7.5" cy="15.5" r="3.5"/><path d="m21 2-9.6 9.6"/><path d="m15.5 7.5 3 3L22 7l-3-3"/></svg>
            </span>
            <span class="kg-err-word">KeyGen</span>
        </div>

        <div class="kg-err-code" aria-hidden="true"><?= esc($kgCode) ?></div>
        <h1><?= esc($kgHeading) ?></h1>
        <p class="kg-err-text"><?= esc($kgText) ?></p>

        <div class="kg-err-actions">
            <a class="kg-err-btn kg-err-btn-primary" href="<?= esc(base_url('/')) ?>">Go to dashboard</a>
            <a class="kg-err-btn kg-err-btn-ghost" href="<?= esc(base_url('login')) ?>">Sign in</a>
        </div>

        <p class="kg-err-foot">KeyGen · License Management System</p>
    </main>
</body>
</html>
