# KeyGen — Production Deployment Guide

A CodeIgniter 4 license-key console. This document covers going live safely.

---

## 1. Pre-flight (code)

All of these should already be true in the repo:

- [x] `CI_ENVIRONMENT = production` (set in `.env`)
- [x] `app.baseURL` = real HTTPS domain with trailing slash
- [x] `app.forceGlobalSecureRequests = true`
- [x] `csrf` filter global, `except => api/v1/*`
- [x] All state-changing routes are **POST** (deletes, logout, resets)
- [x] `apiguard` filter on `api/v1/*`
- [x] `secureheaders` filter enabled + CSP on
  - `script-src` is **strict** — no `'unsafe-inline'`. Every inline `<script>` carries a
    `{csp-script-nonce}` tag that CodeIgniter swaps for a per-response nonce
    (`autoNonce = true`), and inline `on*=` handlers were replaced with `data-*` hooks +
    `addEventListener`.
  - `style-src` keeps `'unsafe-inline'` on purpose: the views use inline `style="…"`
    attributes, which a nonce cannot cover (CSS injection is far less dangerous than JS).
  - The policy is **enforced** in production and **report-only** elsewhere
    (`reportOnly = (ENVIRONMENT !== 'production')`), so a missed tag can never break the
    panel while developing — but always add `{csp-script-nonce}` to a new inline script.
- [x] Panel write-throttle on signed-in owner/admin/seller
      POST/PUT/PATCH/DELETE (`Filters/PanelThrottleFilter.php`)
- [x] API session tokens stored as SHA-256, never raw
- [x] API keys stored as hash + encrypted display copy
- [x] Balance debit is atomic (conditional UPDATE in a transaction)
- [x] Seller/admin delete cascades keys + sessions
- [x] `.gitignore` excludes `.env`, logs, sessions, vendor

---

## 2. Environment file

```bash
cp .env.production.example .env
```

Edit `.env` and set **at minimum**:

| Key | Value |
|-----|-------|
| `CI_ENVIRONMENT` | `production` |
| `app.baseURL` | `https://your-domain.com/` |
| `app.forceGlobalSecureRequests` | `true` |
| `database.default.username` | dedicated app user (NOT `root`) |
| `database.default.password` | strong, unique |
| `encryption.key` | fresh `php spark key:generate` |

Generate the encryption key **once** and **never change it** afterwards —
it decrypts stored API-key display copies. Losing it makes them unreadable.

---

## 3. Database user (least privilege)

Do **not** run the app as `root`. Create a scoped user:

```sql
CREATE USER 'keygen_app'@'127.0.0.1' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT SELECT, INSERT, UPDATE, DELETE ON keygen.* TO 'keygen_app'@'127.0.0.1';
FLUSH PRIVILEGES;
```

No `DROP`/`ALTER` needed at runtime — migrations run as a deploy step under
a separate admin account.

---

## 4. Deploy steps

```bash
# 1. install production deps (no dev packages)
composer install --no-dev --optimize-autoloader

# 2. run migrations (as an admin DB user, once)
php spark migrate

# 3. clear any stale caches
php spark cache:clear
rm -rf writable/cache/* writable/session/*

# 4. fix permissions (web-server owns writable)
chown -R www-data:www-data writable
chmod -R 775 writable

# 5. point the web root at /public  (see §5)
```

---

## 5. Web server

The document root **must** be `public/`. Never expose the app root —
`.env`, `app/`, `writable/` would all be readable.

### Apache

`public/.htaccess` already rewrites to `index.php`. Set the vhost:

```apache
<VirtualHost *:443>
    ServerName your-domain.com
    DocumentRoot /var/www/keygen/public

    SSLEngine on
    SSLCertificateFile      /etc/letsencrypt/live/your-domain.com/fullchain.pem
    SSLCertificateKeyFile   /etc/letsencrypt/live/your-domain.com/privkey.pem

    <Directory /var/www/keygen/public>
        AllowOverride All
        Require all granted
    </Directory>

    # HSTS (only after HTTPS works everywhere)
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</VirtualHost>
```

### Nginx + PHP-FPM

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    root /var/www/keygen/public;

    ssl_certificate     /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    index index.php;
    location / { try_files $uri $uri/ /index.php$is_args$args; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    # never serve dotfiles
    location ~ /\. { deny all; }
}
```

Get a certificate with `certbot --nginx -d your-domain.com`.

### cPanel / shared hosting

If you cannot point the document root at `public/` (typical on cPanel), use the
**split layout**: keep the app at `~/keygen` and place only the *contents* of
`public/` in `~/public_html`. `.env`, `app/` and `vendor/` stay outside the web
root. It needs two path edits (`public_html/index.php` and `spark`) and is
documented step-by-step in
[PRODUCTION.md → cPanel](PRODUCTION.md#alternative-cpanel-shared-hosting).

---

## 6. Scheduled jobs (cron)

```cron
# prune expired API sessions — daily 03:00
0 3 * * * cd /var/www/keygen && php spark sessions:prune >> writable/logs/prune.log 2>&1

# nightly DB backup — keep 14 days
0 2 * * * /var/www/keygen/deploy/backup-db.sh >> writable/logs/backup.log 2>&1
```

`deploy/backup-db.sh` is included in this repo.

---

## 7. Post-deploy verification

```bash
# security headers present
curl -sI https://your-domain.com/login | grep -iE 'content-security|x-frame|strict-transport'

# HTTP redirects to HTTPS
curl -sI http://your-domain.com/ | grep -i location

# deletes are NOT reachable by GET (should be 404/405, not a delete)
curl -sI https://your-domain.com/admin/sellers/delete/1 | head -1

# API throttle works (should return 429 after the burst)
for i in $(seq 1 80); do curl -s -o /dev/null -w "%{http_code} " -X POST https://your-domain.com/api/v1/auth; done; echo
```

Then log in as owner / admin / seller and confirm:
- every page loads,
- generating keys debits balance correctly,
- a delete button asks for confirmation and then removes the row,
- dark-mode toggle works.

---

## 8. Backups & restore

Backups land in `deploy/backups/` (git-ignored). Restore:

```bash
mysql -u root -p keygen < deploy/backups/keygen-YYYYmmdd-HHMMSS.sql
```

Back up **`.env`** and the **encryption key** out-of-band too — the DB dump
alone cannot decrypt `api_key_display` without the same key.

---

## 9. Key pricing plans

Each **admin** defines the durations they sell and the price (in balance) for
each. **Sellers only see plans from the admin who created them** and pay the
admin's fixed price — they cannot invent durations or prices.

| Role | What they can do |
|------|------------------|
| Owner | manage admins (no key pricing) |
| Admin | manage sellers; set key plans at `/admin/plans`; generate their own keys free-form |
| Seller | buy keys from their admin's plans at `/seller/keys` |

Table `key_plans`:

| column | meaning |
|--------|---------|
| `admin_id` | owning admin (FK → users.id) |
| `duration_days` | the sold duration |
| `price` | balance debited per key |
| `is_active` | 1 = buyable, 0 = hidden |

Guards:
- `(admin_id, duration_days)` is **UNIQUE** — save updates, never duplicates.
- On generate, the chosen `plan_id` is resolved against **the seller's own
  admin's active plans only** — a tampered `plan_id` is rejected with no debit.
- Debit + key insert run in a single transaction (atomic; no double-spend).

If a seller's admin has no plans, the generate screen shows an empty state
("ask your admin") and generation is blocked.

---

## 10. Software on/off (per-admin kill switch)

Each **admin** can switch their own software access on/off from
`/admin/profile` → **Software Access**. This is scoped: it affects only that
admin's keys and their sellers' keys.

| State | API behaviour |
|-------|---------------|
| **ON** (default) | `/api/v1/auth` and `/validate` work normally |
| **OFF** | both return **HTTP 503** with `Retry-After: 300` and a JSON body |

Response body when off:

```json
{ "status": "offline", "message": "Server off, kindly wait." }
```

The admin can set a custom `message` (max 255 chars); blank falls back to the
default text. **Live sessions are blocked too** — flipping the switch off stops
already-running clients, not just new logins. The client software should show
the `message` and a "wait" screen instead of a login form when it receives a
`503` / `status: "offline"`.

Storage:
- `users.software_enabled` (1/0) — the switch, per admin
- `users.offline_message` — custom text
- `api_sessions.governing_admin_id` — which admin governs a session, so
  `validate()` can enforce the switch without re-resolving the chain each call

Client handling (suggested pseudo-code):

```
resp = POST /api/v1/validate { token, hwid }
if resp.http == 503 and resp.body.status == "offline":
    show screen(resp.body.message)   # "Server off, kindly wait"
    do NOT show the login form
    retry after a short delay
```

Guarded by: the switch is POST + CSRF (admin UI), and the API gate returns
before any key/session mutation, so no balance or key state changes while off.

---

## 11. License levels (admin-defined)

Each **admin** defines their own set of levels for their software — **as many as
they want** (e.g. `1 = Basic`, `2 = Pro`, `3 = Enterprise`, …). Levels are
scoped per admin: admin A's "Level 1" is independent of admin B's.

| Role | What they can do |
|------|------------------|
| Admin | define levels at `/admin/levels`; assign a level when generating keys at `/admin/keys`; set which level a seller plan sells at `/admin/plans` |
| Seller | the level comes **from the admin's plan** — the seller cannot choose or invent one |

Tables / columns:

| storage | meaning |
|---------|---------|
| `key_levels.admin_id` | owning admin (FK → users.id) |
| `key_levels.level_number` | the number carried on keys/plans |
| `key_levels.name` | human label ("Pro") |
| `keys.level` | the level granted by this key (`0` = no level) |
| `key_plans.level_number` | the level a seller buys through this plan (`0` = none) |

Guards:
- `(admin_id, level_number)` is **UNIQUE** in `key_levels` — save updates the name.
- A key/plan can only use a level the **same admin** defined; a tampered number is
  rejected (`Pick a level you created`).
- A level that is still referenced by any key or plan **cannot be deleted**
  (the admin must reassign/clear first).

The licensing API returns the level on both calls so the client can unlock the
right feature set:

```json
// POST /api/v1/auth  and  POST /api/v1/validate
{ "status": "ok", "level": 2, "level_name": "Pro", ... }
```

`level` is the number (stable key for the client); `level_name` is the admin's
label (may be `null` for level 0 or an unnamed level).

---

## 12. Hardening checklist (done in code)

| Item | Where |
|------|-------|
| CSRF global (API excepted) | `app/Config/Filters.php` |
| Security headers + CSP + HSTS + Permissions-Policy | `Filters/SecurityHeadersFilter.php`, `ContentSecurityPolicy.php` |
| HTTPS forced (non-API) | `Config/App.php` + `.env` + `Filters.php` |
| API rejects cleartext HTTP in prod (426) | `Filters/ApiGuardFilter.php` |
| API rate limit: per-IP **and** per-credential | `Filters/ApiGuardFilter.php` |
| API responses `no-store` (token never cached) | `Filters/ApiGuardFilter.php` |
| No key/owner enumeration in API errors | `Controllers/Api.php` |
| Session owner/key re-checked on every validate | `Controllers/Api.php` |
| Secure/HttpOnly/SameSite cookies | `Config/Cookie.php` |
| Session IP match + regen-destroy | `Config/Session.php` |
| Login throttle | `Controllers/Auth.php` |
| Panel write throttle (per user + per IP) | `Filters/PanelThrottleFilter.php` |
| Strict `script-src` via nonces (`'unsafe-inline'` removed) | `Config/ContentSecurityPolicy.php` + views |
| Branded error pages; no stack/message leaked in prod | `app/Views/errors/html/*.php` |
| GET→POST destructive routes | `Config/Routes.php` |
| Atomic balance debit | `Controllers/Seller.php` |
| Cascade deletes | `Controllers/Admin.php`, `Owner.php` |
| Password min 8 | all controllers + views |
| Hashed API tokens/keys (SHA-256) | `Api.php`, `keygen_helper.php` |

Remaining **optional** improvements: 2FA for owner/admin, an audit trail of
destructive actions, and alerting on new-device activations.

---

## 13. API security (DDoS / MITM)

The public API (`/api/v1/*`) is protected by `App\Filters\ApiGuardFilter`.

**MITM / eavesdropping**
- In production, an API call over plain HTTP is **rejected with `426 Upgrade
  Required`** — it is never redirected. Credentials (api_key, license_key,
  token) therefore never travel in the clear, not even for one hop.
- `Strict-Transport-Security` (HSTS, 1 year, includeSubDomains) is sent on
  every HTTPS response in production, so browsers pin HTTPS and SSL-strip
  attacks fail.
- Session tokens are 256-bit random, stored only as SHA-256 hashes, and every
  API response is `Cache-Control: no-store` so no proxy or browser caches them.

**DDoS / brute force**
- Two-layer token-bucket limit, keyed by **client IP** (60 / min) *and* by
  **each credential** — api_key, license_key and token (30 / min each). A single
  hammering host is capped, and so is a botnet spreading requests across IPs for
  one credential. Exceeding either returns `429` with `Retry-After`.
- Login (non-API) is separately throttled per IP+username, and every signed-in
  panel write (delete/ban/purge/regenerate/…) is throttled per user and per IP
  in `Filters/PanelThrottleFilter.php`.

**Reconnaissance**
- The API never reveals whether a license key exists, whether it belongs to the
  caller, or what product it expects — all of those return the same generic
  `403 Invalid license key`. This blocks key enumeration.

> **Behind a reverse proxy / CDN (Cloudflare, nginx):** set `app.proxyIPs` in
> `.env` to the proxy address(es), e.g. `app.proxyIPs = {"127.0.0.1": "X-Forwarded-For"}`,
> otherwise both the IP rate-limit and the "is it HTTPS?" check see the proxy,
> not the real client. See `Config/App.php` for the exact format.

**External DDoS note:** application rate limiting stops brute force and small
floods, but a volumetric L3/L4 flood must be absorbed *in front of* PHP — put
Cloudflare (or your host's scrubbing) in front, and/or an nginx `limit_req`
zone. No PHP application can withstand raw packet floods alone.