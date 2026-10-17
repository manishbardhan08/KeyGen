#!/usr/bin/env bash
# ============================================================
#  KeyVault — nightly database backup
#  Usage:  ./deploy/backup-db.sh
#  Cron:   0 2 * * * /var/www/authgen/deploy/backup-db.sh
# ============================================================
set -euo pipefail

# Resolve app root (this script lives in <root>/deploy)
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="$APP_DIR/.env"
OUT_DIR="$APP_DIR/deploy/backups"
KEEP_DAYS="${KEEP_DAYS:-14}"

mkdir -p "$OUT_DIR"

# --- read DB creds from .env (no hardcoding in the script) ---
get() { grep -E "^${1}[[:space:]]*=" "$ENV_FILE" | head -1 | sed -E "s/^${1}[[:space:]]*=[[:space:]]*//; s/^['\"]//; s/['\"]$//"; }

DB_HOST="$(get 'database.default.hostname')"
DB_PORT="$(get 'database.default.port')"
DB_NAME="$(get 'database.default.database')"
DB_USER="$(get 'database.default.username')"
DB_PASS="$(get 'database.default.password')"

STAMP="$(date +%Y%m%d-%H%M%S)"
FILE="$OUT_DIR/authgen-$STAMP.sql"

echo "[$(date '+%F %T')] dumping $DB_NAME → $FILE"

MYSQL_PWD="$DB_PASS" mysqldump \
  --host="$DB_HOST" --port="${DB_PORT:-3306}" \
  --user="$DB_USER" --single-transaction --quick --routines \
  "$DB_NAME" > "$FILE"

gzip -f "$FILE"
echo "[$(date '+%F %T')] done ($(du -h "$FILE.gz" | cut -f1))"

# --- prune old backups ---
find "$OUT_DIR" -name 'authgen-*.sql.gz' -mtime +"$KEEP_DAYS" -delete
echo "[$(date '+%F %T')] pruned backups older than ${KEEP_DAYS}d"