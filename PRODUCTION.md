# KeyGen — Production Runbook

Step-by-step instructions to take this app live on a fresh Ubuntu VPS.
Follow the phases **in order** and tick them off. Every command is copy-paste
ready.

> Deep reference & background: [`DEPLOYMENT.md`](DEPLOYMENT.md).
> This file is the short, ordered "do this now" runbook.
>
> **On cPanel / shared hosting instead of a VPS?** You can skip Phases 3, 7 and 8
> and follow the [cPanel section](#alternative-cpanel-shared-hosting) at the end
> of this file (the rest — `.env`, migrations, cron, backups — still applies).

---

## Placeholders used below

Replace these with your own values **everywhere** before running a command:

| Placeholder | Meaning | Example |
|---|---|---|
| `your-domain.com` | your real domain (A record → server IP) | `keys.mysite.com` |
| `/var/www/keygen` | project path on the server | keep as-is |
| `STRONG_DB_PASSWORD` | a strong, unique DB password | `x7Kp...` |
| `ubuntu` | your deploy/SSH user | your VPS user |

---

## Phase 0 — What you need

- [ ] Ubuntu 22.04 or 24.04 VPS (1 vCPU / 1 GB RAM is enough to start)
- [ ] A domain with an **A record** pointing to the server IP
- [ ] SSH access as a sudo user
- [ ] The project in a git remote **or** upload the folder via `scp`

---

## Phase 1 — Install the server software

```bash
sudo apt update && sudo apt upgrade -y

sudo apt install -y nginx mariadb-server git unzip \
  php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-intl \
  php8.2-curl php8.2-xml php8.2-gd \
  certbot python3-certbot-nginx

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

Check:

```bash
php -v            # must be 8.1 or newer
nginx -v
composer -V
```

---

## Phase 2 — Create the database

```bash
sudo mysql
```

```sql
CREATE DATABASE keygen CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- runtime app user (least privilege)
CREATE USER 'keygen_app'@'127.0.0.1' IDENTIFIED BY 'STRONG_DB_PASSWORD';
GRANT SELECT, INSERT, UPDATE, DELETE ON keygen.* TO 'keygen_app'@'127.0.0.1';

-- privileges needed once for `php spark migrate`
GRANT CREATE, ALTER, INDEX, DROP, REFERENCES ON keygen.* TO 'keygen_app'@'127.0.0.1';

FLUSH PRIVILEGES;
EXIT;
```

> After your first successful deploy you can **revoke** the second `GRANT`
> (migrations will then need an admin again). See DEPLOYMENT.md §3.

---

## Phase 3 — Get the code

```bash
sudo mkdir -p /var/www/keygen
sudo chown -R $USER:www-data /var/www/keygen

# git clone:
git clone <YOUR_REPO_URL> /var/www/keygen

# ...or upload from your machine:
# rsync -avz --exclude vendor --exclude .env ./ user@server:/var/www/keygen/

cd /var/www/keygen
```

---

## Phase 4 — Install PHP dependencies

```bash
cd /var/www/keygen
composer install --no-dev --optimize-autoloader
```

This creates `vendor/`. If you uploaded via `rsync` without `vendor/`, this
step is what builds it.

---

## Phase 5 — Create the `.env`

```bash
cd /var/www/keygen
cp .env.production.example .env
php spark key:generate        # writes a fresh encryption.key into .env
nano .env
```

Now edit these **4 things** (search for `CHANGE` in the file):

| Key | What to set |
|---|---|
| `app.baseURL` | `'https://your-domain.com/'` — **with trailing slash** |
| `database.default.password` | your `STRONG_DB_PASSWORD` |
| `encryption.key` | already done by `key:generate` ✅ |
| `OWNER_NAME` / `OWNER_USERNAME` / `OWNER_PASSWORD` | your owner login |

Already correct by default (production-safe):

```ini
CI_ENVIRONMENT = production
app.forceGlobalSecureRequests = true
app.autoLogoutMinutes = 30
app.rememberMeDays = 0
session.expiration = 1800
logger.threshold = 4
```

> ⚠️ `encryption.key` must **never change** after this. It decrypts stored
> API-key copies. Back it up somewhere safe (password manager).

---

## Phase 6 — Create the tables + owner

```bash
cd /var/www/keygen

php spark migrate                 # creates all tables
php spark db:seed OwnerSeeder     # creates the owner from OWNER_* in .env
php spark cache:clear

# sanity: is the DB reachable + how many migrations?
php spark migrate:status
```

Expected: the seeder prints
`[SECURITY] Created owner: name='...', username='...'`.

Re-running the seeder is safe — if the username exists it just skips.

---

## Phase 7 — Permissions

```bash
cd /var/www/keygen

sudo chown -R $USER:www-data /var/www/keygen
sudo find /var/www/keygen -type d -exec chmod 750 {} \;
sudo find /var/www/keygen -type f -exec chmod 640 {} \;
sudo chmod 640 /var/www/keygen/.env
sudo chmod -R 775 /var/www/keygen/writable

# let the deploy user (cron) and php-fpm share the writable dir
sudo usermod -aG www-data $USER
```

> `writable/{cache,logs,session,debugbar}` must exist — the repo ships
> `.gitkeep` files so they are created automatically. A missing
> `writable/cache` makes `php spark` fail **silently** in production.

---

## Phase 8 — Nginx

```bash
sudo nano /etc/nginx/sites-available/keygen
```

```nginx
server {
    listen 80;
    server_name your-domain.com;

    root /var/www/keygen/public;
    index index.php;

    client_max_body_size 12m;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    # never serve dotfiles (.env, .git, ...)
    location ~ /\. { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/keygen /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
```

The web root **must** be `public/` — never the project root, or `.env` and
`app/` become downloadable.

---

## Phase 9 — HTTPS (Let's Encrypt)

```bash
sudo certbot --nginx -d your-domain.com
```

Certbot edits the vhost to add 443 + an HTTP→HTTPS redirect and installs a
renewal timer. Test it once:

```bash
curl -sI http://your-domain.com/ | grep -i location   # → https://...
```

---

## Phase 10 — Cron

```bash
crontab -e
```

```cron
# nightly DB backup — 02:00, keep 14 days
0 2 * * * /var/www/keygen/deploy/backup-db.sh >> /var/www/keygen/writable/logs/backup.log 2>&1

# prune expired API sessions — 03:00
0 3 * * * cd /var/www/keygen && php spark sessions:prune >> writable/logs/prune.log 2>&1
```

`deploy/backup-db.sh` ships with the repo. Backups land in `deploy/backups/`
(git-ignored).

---

## Phase 11 — Verify

```bash
# security headers present
curl -sI https://your-domain.com/login | grep -iE 'content-security|strict-transport'

# HTTP redirects to HTTPS
curl -sI http://your-domain.com/ | grep -i location

# delete routes are NOT reachable by GET (expect 404/405, not a delete)
curl -sI https://your-domain.com/admin/sellers/delete/1 | head -1

# API throttle works (expect some 429s in the burst)
for i in $(seq 1 80); do curl -s -o /dev/null -w "%{http_code} " -X POST https://your-domain.com/api/v1/auth; done; echo
```

Then in a browser:

- [ ] `/login` loads over HTTPS
- [ ] log in as the **owner** → dashboard loads
- [ ] idle for 30 min (or temporarily set `app.autoLogoutMinutes = 1`) →
      "Still there?" warning → logout → `/login`
- [ ] create an admin + a seller, generate a key, delete a row
- [ ] dark-mode toggle works

---

## Phase 12 — Backups (do this now)

- [ ] `deploy/backups/` is being written nightly (check after the first night)
- [ ] copy of `.env` secured offline
- [ ] `encryption.key` secured offline (without it, encrypted
      `api_key_display` values cannot be decrypted)
- [ ] a restore test done once:

```bash
mysql -u root -p keygen < deploy/backups/keygen-YYYYmmdd-HHMMSS.sql
```

---

## Phase 13 — Updating the app later

```bash
cd /var/www/keygen

# 1. maintenance (optional)
#    edit .env → CI_ENVIRONMENT = development to show errors during the pull

# 2. pull + deps
git pull
composer install --no-dev --optimize-autoloader

# 3. schema + caches
php spark migrate
php spark cache:clear

# 4. re-apply permissions (after a pull, new files may be root/other-owned)
sudo chown -R $USER:www-data /var/www/keygen
sudo chmod -R 775 /var/www/keygen/writable

# 5. reload php (opcache)
sudo systemctl reload php8.2-fpm
```

---

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `php spark` prints **nothing** and exits 1 | `writable/cache` (or logs/session) missing in production | `mkdir -p writable/{cache,logs,session,debugbar}` then retry (repo `.gitkeep` prevents this) |
| **500 / blank page** in production | error is hidden | check `writable/logs/log-*.log`; temporarily set `CI_ENVIRONMENT = development` |
| "The action you requested is not allowed." | CSRF token stale | reload the page; `security.regenerate` must stay `false` |
| Mixed-content / assets over `http://` | `app.baseURL` wrong | set `https://your-domain.com/` **with** trailing slash |
| Login loops back to `/login` | session dir not writable | `chmod -R 775 writable` + `chown www-data` |
| 502 Bad Gateway | php-fpm socket path mismatch | match `fastcgi_pass` to your real `php8.2-fpm.sock` |
| API returns **426** | cleartext HTTP rejected in prod | call the API over HTTPS |
| Rate-limit hits the proxy, not the client | behind Cloudflare/proxy | set `app.proxyIPs` (see `.env.production.example`) |

---

## Quick reference — keys the app reads from `.env`

| Key | Purpose |
|---|---|
| `CI_ENVIRONMENT` | `production` hides errors, enables secure cookies |
| `app.baseURL` | absolute URLs (with trailing slash) |
| `app.forceGlobalSecureRequests` | force HTTPS + HSTS |
| `app.allowedHostnames` | host-header guard (optional) |
| `app.proxyIPs` | real client IP behind proxy (optional) |
| `app.autoLogoutMinutes` | browser idle logout (minutes) |
| `app.rememberMeDays` | remember-me days; `0` = hard logout |
| `database.default.*` | DB connection |
| `encryption.key` | encrypts stored API-key copies — never change |
| `session.expiration` | server idle timeout (seconds) |
| `OWNER_NAME` / `OWNER_USERNAME` / `OWNER_PASSWORD` | owner created by `db:seed OwnerSeeder` |
| `logger.threshold` | `1` errors only … `4` everything |

---

# Alternative: cPanel shared hosting

Use this **instead of the VPS phases** if you are on cPanel. There is no
`apt`/`nginx`/`systemd`: cPanel is Apache (or LiteSpeed) + PHP-FPM with
`~/public_html` as the web root, driven from a control panel instead of the shell.
This section is self-contained for the parts that differ (files, web root, PHP,
database, migrations, permissions); the `.env` **values**, scheduled jobs and
backups are the same as the VPS runbook — you just trigger them from cPanel's UI
(*Cron Jobs*, *phpMyAdmin*).

## Why the "split layout"

cPanel normally gives you **one** web root (`~/public_html`) and no clean way to
point it at the app's `public/` folder. The split layout works around that
**without** changing the document root, and — unlike dumping the whole app inside
`public_html` — it keeps `.env`, `app/` and `vendor/` **outside the web root**, so
nothing sensitive is exposed even if `.htaccess` is ignored.

```
/home/USER/
├── keygen/                   ← the whole project, OUTSIDE the web root
│   ├── app/                    (incl. app/Views/errors/html/ — the branded error pages)
│   ├── system/  vendor/  writable/  tests/  deploy/
│   ├── .env
│   ├── spark  composer.json  composer.lock  preload.php  phpunit.xml.dist
│   └── public/                 ← emptied: its *contents* move to public_html
└── public_html/              ← the web root: only public/'s contents
    ├── index.php
    ├── .htaccess
    ├── assets/
    ├── favicon.ico
    └── robots.txt
```

The two paths are wired together by **two small edits** (Step 2).

## Step 1 — Put the files in place

Upload `keygen.zip` (the whole repo) to `/home/USER/` and extract it, then:

1. open `keygen/public/` → select **everything** → **Cut** → open `public_html/` → **Paste**
2. delete the now-empty `keygen/public/`
3. in `public_html/`, delete any default `index.html` / `default.html` / placeholder
4. `keygen/.env` must end up as `/home/USER/keygen/.env` (create it here in Step 3)

> Never move `keygen/` itself under `public_html/`.

## Step 2 — Edit two files (required)

`index.php` and `spark` assume `app/` sits **next to** `public/`; here they sit two
levels apart. Fix both.

**`public_html/index.php`** — the require line:

```php
// before
require FCPATH . '../app/Config/Paths.php';
// after
require FCPATH . '../keygen/app/Config/Paths.php';
```

**`keygen/spark`** — `public/` is hard-coded in two places:

```php
// before
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
// after
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'public_html' . DIRECTORY_SEPARATOR);
```

```php
// before  (the line whose own comment says "change this line if you move your application folder")
require FCPATH . '../app/Config/Paths.php';
// after
require __DIR__ . '/app/Config/Paths.php';
```

That is everything — `.env`, `writable/`, `system/` and the error views all resolve
through `Paths.php` automatically.

## Step 3 — `.env`

`cp .env.production.example keygen/.env`, then set at minimum (search for `CHANGE`):

| Key | Value |
|---|---|
| `app.baseURL` | `'https://your-domain.com/'` — **trailing slash required** |
| `database.default.*` | cPanel DB name / user / password |
| `encryption.key` | run `php spark key:generate` (Step 6) |
| `OWNER_NAME` / `OWNER_USERNAME` / `OWNER_PASSWORD` | your owner login |

> A wrong/absent `app.baseURL` is the #1 cause of weirdness on cPanel — it breaks
> asset URLs, redirects **and** the error-page buttons, which all use `base_url()`.

## Step 4 — PHP version & extensions

cPanel → **MultiPHP Manager**: select **PHP 8.1+** for the domain.
cPanel → **MultiPHP INI Editor** (or *Select PHP Version → Extensions*): enable

```
intl  mbstring  openssl  curl  dom  libxml  simplexml  mysqli  gd  xml
```

`intl` is a hard requirement (`composer install` and the app both need it).

## Step 5 — Database

cPanel → **MySQL Databases**: create a database + user and **add the user to the
database** (all privileges). cPanel prefixes names with your account, e.g.
`USER_keygen`. Put those exact names in `keygen/.env`.

## Step 6 — Tables + owner

**With Terminal** (cPanel → *Advanced → Terminal*):

```bash
cd ~/keygen
php spark migrate
php spark db:seed OwnerSeeder
php spark cache:clear
```

**Without Terminal** — use a throwaway Cron Job (cPanel → *Cron Jobs*), one minute
apart, then delete the job:

```
/usr/local/bin/php /home/USER/keygen/spark migrate
/usr/local/bin/php /home/USER/keygen/spark db:seed OwnerSeeder
```

> The PHP binary may differ per host. Check with `which php` in Terminal, or use the
> MultiPHP build at `/opt/cpanel/ea-php82/root/usr/bin/php`.
> Last resort: import the SQL from a local `php spark db:table --export` dump via
> **phpMyAdmin**, and insert the owner row manually.

## Step 7 — Permissions

```bash
chmod -R 755 /home/USER/keygen/writable
```

PHP runs as your cPanel user, so `writable/` just needs to be writable by you
(`755` on dirs, `644` on files). No `chown www-data` on cPanel.

## Step 8 — Verify

```bash
curl -sI https://your-domain.com/login | grep -iE 'content-security|strict-transport'
curl -sIL https://your-domain.com/.env | grep -iE 'HTTP/|content-type'   # expect 404/403, never text/plain
```

Then in a browser:

- [ ] `/login` loads over HTTPS and you can sign in as **owner**
- [ ] the URL never shows `/public/`
- [ ] open a bad URL (e.g. `/does-not-exist`) → the branded **404** page appears
- [ ] the browser console shows **no** CSP violations

> The branded error pages live in `keygen/app/Views/errors/html/` — outside the
> web root here, so there is nothing to configure.

## Updating later (cPanel)

```bash
cd ~/keygen
git pull                       # or re-upload the changed files
composer install --no-dev --optimize-autoloader
php spark migrate
php spark cache:clear
```

Then re-copy `keygen/public/*` into `public_html/` and **re-apply the
`index.php` edit from Step 2** — that file is the one piece that lives outside the
repo's `public/` folder.

> **Prefer zero edits?** Keep the project intact at `~/keygen` (with its `public/`
> folder) and make the web root a symlink instead:
> ```bash
> rm -rf ~/public_html && ln -s ~/keygen/public ~/public_html
> ```
> Then `index.php` and `spark` stay stock and survive updates untouched. This needs
> your host to allow symlinked docroots (`FollowSymLinks`), which most cPanel hosts
> do but a few disable. Re-check `~/public_html` after cPanel updates.

## cPanel troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| `app.baseURL` still `localhost`, assets 404 | `.env` in the wrong folder | it must be at `~/keygen/.env` (one level above `app/`) |
| 500 on every page | missing `intl`/`mbstring`, or `writable/` not writable | enable extensions (Step 4); `chmod -R 755 ~/keygen/writable` |
| `php spark` prints **nothing**, exits 1 | `writable/cache` missing, or Step 2 edits not saved | create `writable/{cache,logs,session,debugbar}`; re-check Step 2 |
| Login loops / "action is not allowed" | serving over **http** while production cookies are `Secure` | enable a Let's Encrypt cert (cPanel → *SSL/TLS Status → Run AutoSSL*); or set `CI_ENVIRONMENT = development` temporarily to confirm |
| Page 500s only when `Options` appears | host blocks `Options` overrides | comment out `Options +FollowSymLinks` in `public_html/.htaccess` |
| Old CI4 welcome page shows | default `index.html` left in `public_html` | delete it (Step 1.3) |
