# KeyGen

A CodeIgniter 4 license-key management console. It issues, distributes and
validates software license keys with **HWID (device) binding**, and exposes a
small JSON API that client software calls on every run.

```
Owner ──creates──► Admin ──creates──► Seller
                     │                  │
                     └──── keys ────────┘   (sellers buy from the admin's plans)
                              ▲
     client software ────► /api/v1/{auth,validate,logout}
```

## Roles

| Role | Can do |
|------|--------|
| **Owner** | Create/delete admins, reset their passwords. No key pricing. |
| **Admin** | Manage sellers; define license **levels** and **key plans**; generate keys; per-admin software on/off switch and API key. |
| **Seller** | Buy keys from the creating admin's plans (balance is debited); limited by per-seller permissions (extend / ban / reset HWID / delete). |

## Features

- **Device binding** — each key locks to 1..N HWIDs on first activation.
- **Admin-defined levels** — admin A's "Level 1" is independent of admin B's.
- **Per-admin plans** — sellers only buy the durations/levels their admin priced.
- **Software kill-switch** — an admin can take their software offline; live
  sessions receive `503` + a custom message.
- **Licensing API** (`POST /api/v1/auth|validate|logout`) — SHA-256-hashed
  session tokens, no key/owner enumeration, per-IP *and* per-credential rate
  limits, HTTPS-only in production.
- CSV export, dark/light theme, idle auto-logout, "remember me".

## Requirements

- PHP 8.1+ with `intl`, `mbstring`, `curl` (for tests)
- MySQL / MariaDB
- Composer

## Setup

```bash
composer install
cp .env.production.example .env      # then edit it (see below)
php spark migrate
php spark db:seed OwnerSeeder         # creates the first owner
php spark serve                       # http://localhost:8080
```

Minimum `.env` values:

| Key | Value |
|-----|-------|
| `CI_ENVIRONMENT` | `development` or `production` |
| `app.baseURL` | your URL, with trailing slash |
| `database.default.*` | DB connection |
| `encryption.key` | `php spark key:generate` |

In **production**, the owner seeder refuses to run unless you pass a real
password:

```bash
OWNER_PASSWORD='a-strong-password' php spark db:seed OwnerSeeder
```

## Encryption-key rotation

`encryption.key` decrypts the stored API-key display copies. If you ever need
to rotate it, do **not** just change `.env` — re-encrypt first:

```bash
php spark keygen:reencrypt        # prints the new encryption.key line for .env
```

## API

```
POST /api/v1/auth      { api_key, license_key, hwid, product }
POST /api/v1/validate  { token, hwid }
POST /api/v1/logout    { token }
```

All responses are `Cache-Control: no-store`. In production, plain-HTTP calls are
rejected with `426 Upgrade Required` so credentials never travel in the clear.

Full request/response reference, error codes, rate limits and client examples
(cURL, PHP, Python, C#, Node.js): see [API.md](API.md). Admins can also read the
same reference in-app at **Admin → Developer → API Docs** (`/admin/docs`).

## Scheduled jobs

```cron
# prune expired API sessions daily
0 3 * * * cd /path/to/app && php spark sessions:prune >> writable/logs/prune.log 2>&1
```

## Deployment

See [DEPLOYMENT.md](DEPLOYMENT.md) for the full production guide (web server,
least-privilege DB user, TLS/HSTS, backups).

## Tests

```bash
composer test        # phpunit
```

## License

Built on [CodeIgniter 4](https://codeigniter.com) (MIT). See `LICENSE`.
