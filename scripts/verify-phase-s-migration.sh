#!/usr/bin/env bash

set -Eeuo pipefail

readonly PRE_PHASE_S_COMMIT="80f475f3a56d0afc6e83ed3bdb8599aedb622ccb"
readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
readonly REPOSITORY_ROOT="$(git -C "${SCRIPT_DIR}" rev-parse --show-toplevel)"
readonly PHASE_S_MIGRATION="${REPOSITORY_ROOT}/database/migrations/2026_08_14_010000_add_smart_purchase_automation_controls.php"

temporary_root="$(mktemp -d "${TMPDIR:-/tmp}/retailpos-phase-s-migration.XXXXXX")"
database_path="${temporary_root}/phase-s-upgrade.sqlite"
previous_root="${temporary_root}/previous"
phase_s_path="${temporary_root}/phase-s"

cleanup() {
    local exit_code=$?
    rm -rf -- "${temporary_root}"
    exit "${exit_code}"
}
trap cleanup EXIT INT TERM

fail() {
    printf 'Phase S migration verification failed: %s\n' "$*" >&2
    exit 1
}

git -C "${REPOSITORY_ROOT}" cat-file -e "${PRE_PHASE_S_COMMIT}^{commit}" 2>/dev/null \
    || fail "pre-Phase-S commit ${PRE_PHASE_S_COMMIT} is unavailable"
[[ -f "${PHASE_S_MIGRATION}" ]] || fail "Phase S migration is missing"

mkdir -p -- "${previous_root}" "${phase_s_path}"
touch -- "${database_path}"

git -C "${REPOSITORY_ROOT}" archive "${PRE_PHASE_S_COMMIT}" database/migrations \
    | tar -xf - -C "${previous_root}"
cp -- "${PHASE_S_MIGRATION}" "${phase_s_path}/"

export APP_ENV=testing
export APP_DEBUG=false
export APP_KEY="base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA="
export DB_CONNECTION=sqlite
export DB_DATABASE="${database_path}"
export CACHE_STORE=array
export SESSION_DRIVER=array
export QUEUE_CONNECTION=sync
export MAIL_MAILER=array
export LOG_CHANNEL=stderr
export SAAS_ENTITLEMENT_ENFORCEMENT=false

printf 'Using isolated SQLite database: %s\n' "${database_path}"
printf 'Migrating the pre-Phase-S schema at %s...\n' "${PRE_PHASE_S_COMMIT:0:7}"
php "${REPOSITORY_ROOT}/artisan" migrate \
    --path="${previous_root}/database/migrations" \
    --realpath \
    --force \
    --no-interaction

printf 'Applying the current Phase S migration...\n'
php "${REPOSITORY_ROOT}/artisan" migrate \
    --path="${phase_s_path}" \
    --realpath \
    --force \
    --no-interaction

printf 'Removing only the Phase S migration record and verifying a safe retry...\n'
php -r '
$pdo = new PDO("sqlite:".$argv[1]);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("DELETE FROM migrations WHERE migration = \"2026_08_14_010000_add_smart_purchase_automation_controls\"");
' "${database_path}"

php "${REPOSITORY_ROOT}/artisan" migrate \
    --path="${phase_s_path}" \
    --realpath \
    --force \
    --no-interaction

php -r '
$pdo = new PDO("sqlite:".$argv[1]);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$migrationCount = (int) $pdo->query("SELECT COUNT(*) FROM migrations WHERE migration = \"2026_08_14_010000_add_smart_purchase_automation_controls\"")->fetchColumn();
$integrity = $pdo->query("PRAGMA integrity_check")->fetchColumn();
$foreignFailures = $pdo->query("PRAGMA foreign_key_check")->fetchAll(PDO::FETCH_ASSOC);

if ($migrationCount !== 1 || $integrity !== "ok" || $foreignFailures !== []) {
    fwrite(STDERR, "Phase S migration verification failed database integrity checks.\n");
    exit(1);
}

fwrite(STDOUT, "Phase S upgrade and idempotent retry passed with database integrity intact.\n");
' "${database_path}"
