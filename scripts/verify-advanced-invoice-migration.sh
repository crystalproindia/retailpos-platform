#!/usr/bin/env bash

set -Eeuo pipefail

readonly LIVE_PHASE_S_COMMIT="a72be4afcc7e816082056ec4b184edba58e35bf3"
readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
readonly REPOSITORY_ROOT="$(git -C "${SCRIPT_DIR}" rev-parse --show-toplevel)"
readonly ADVANCED_MIGRATION="${REPOSITORY_ROOT}/database/migrations/2026_08_15_010000_add_advanced_invoice_customization_foundation.php"

temporary_root="$(mktemp -d "${TMPDIR:-/tmp}/retailpos-advanced-invoice.XXXXXX")"
database_path="${temporary_root}/advanced-invoice.sqlite"
live_root="${temporary_root}/live"
advanced_path="${temporary_root}/advanced"

cleanup() {
    local exit_code=$?
    rm -rf -- "${temporary_root}"
    exit "${exit_code}"
}
trap cleanup EXIT INT TERM

fail() {
    printf 'Advanced invoice migration verification failed: %s\n' "$*" >&2
    exit 1
}

git -C "${REPOSITORY_ROOT}" cat-file -e "${LIVE_PHASE_S_COMMIT}^{commit}" 2>/dev/null \
    || fail "live Phase S commit ${LIVE_PHASE_S_COMMIT} is unavailable"
[[ -f "${ADVANCED_MIGRATION}" ]] || fail "advanced invoice migration is missing"

mkdir -p -- "${live_root}" "${advanced_path}"
touch -- "${database_path}"

git -C "${REPOSITORY_ROOT}" archive "${LIVE_PHASE_S_COMMIT}" database/migrations \
    | tar -xf - -C "${live_root}"
cp -- "${ADVANCED_MIGRATION}" "${advanced_path}/"

export APP_ENV=testing
export APP_DEBUG=false
export APP_KEY="base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA="
export APP_CONFIG_CACHE="${temporary_root}/config.php"
export DB_CONNECTION=sqlite
export DB_DATABASE="${database_path}"
export CACHE_STORE=array
export SESSION_DRIVER=array
export QUEUE_CONNECTION=sync
export MAIL_MAILER=array
export LOG_CHANNEL=stderr
export SAAS_ENTITLEMENT_ENFORCEMENT=false

printf 'Migrating exact live Phase S schema at %s...\n' "${LIVE_PHASE_S_COMMIT:0:7}"
php "${REPOSITORY_ROOT}/artisan" migrate \
    --path="${live_root}/database/migrations" \
    --realpath \
    --force \
    --no-interaction

php -r '
$pdo = new PDO("sqlite:".$argv[1]);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$now = "2026-08-15 10:00:00";
$pdo->exec("INSERT INTO companies (name, currency, timezone, is_active, created_at, updated_at) VALUES (\"Historical Tenant\", \"INR\", \"Asia/Kolkata\", 1, \"$now\", \"$now\")");
$companyId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO branches (company_id, name, code, is_primary, is_active, created_at, updated_at) VALUES ($companyId, \"Historical Outlet\", \"HIST\", 1, 1, \"$now\", \"$now\")");
$branchId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO crm_invoices (company_id, branch_id, invoice_number, currency, tax_total, grand_total, balance_due, status, created_at, updated_at) VALUES ($companyId, $branchId, \"RPOS-INV-2026-00001\", \"INR\", 18.00, 118.00, 118.00, \"draft\", \"$now\", \"$now\")");
' "${database_path}"

printf 'Applying advanced invoice migration...\n'
php "${REPOSITORY_ROOT}/artisan" migrate \
    --path="${advanced_path}" \
    --realpath \
    --force \
    --no-interaction

printf 'Verifying rollback/reapply boundary without dropping forward data...\n'
php "${REPOSITORY_ROOT}/artisan" migrate:rollback \
    --path="${advanced_path}" \
    --realpath \
    --step=1 \
    --force \
    --no-interaction
php "${REPOSITORY_ROOT}/artisan" migrate \
    --path="${advanced_path}" \
    --realpath \
    --force \
    --no-interaction

php -r '
$pdo = new PDO("sqlite:".$argv[1]);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$invoice = $pdo->query("SELECT invoice_number, tax_mode, tax_total, grand_total FROM crm_invoices WHERE invoice_number = \"RPOS-INV-2026-00001\"")->fetch(PDO::FETCH_ASSOC);
$migrationCount = (int) $pdo->query("SELECT COUNT(*) FROM migrations WHERE migration = \"2026_08_15_010000_add_advanced_invoice_customization_foundation\"")->fetchColumn();
$integrity = $pdo->query("PRAGMA integrity_check")->fetchColumn();
$foreignFailures = $pdo->query("PRAGMA foreign_key_check")->fetchAll(PDO::FETCH_ASSOC);

if (($invoice["invoice_number"] ?? null) !== "RPOS-INV-2026-00001"
    || ($invoice["tax_mode"] ?? null) !== "gst"
    || (float) ($invoice["tax_total"] ?? -1) !== 18.0
    || (float) ($invoice["grand_total"] ?? -1) !== 118.0
    || $migrationCount !== 1
    || $integrity !== "ok"
    || $foreignFailures !== []) {
    fwrite(STDERR, "Advanced invoice migration verification failed integrity or historical-data checks.\n");
    exit(1);
}

fwrite(STDOUT, "Exact Phase S upgrade, rollback/reapply, and historical invoice checks passed.\n");
' "${database_path}"
