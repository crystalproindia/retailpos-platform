#!/usr/bin/env bash

set -Eeuo pipefail

readonly PRE_PHASE_G_COMMIT="e5f18104790d9eac8a669c79a428940dc73b33ce"
readonly PHASE_G_COMMIT="e9f46ba1732fd87861a71906d8717a5ffc732e1e"
readonly SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
readonly REPOSITORY_ROOT="$(git -C "${SCRIPT_DIR}" rev-parse --show-toplevel)"
readonly HARNESS_PHP="${SCRIPT_DIR}/phase-g-history.php"

temporary_root="$(mktemp -d "${TMPDIR:-/tmp}/retailpos-phase-g-history.XXXXXX")"
pre_phase_g_worktree="${temporary_root}/pre-phase-g"
database_path="${temporary_root}/phase-g-history.sqlite"
before_snapshot="${temporary_root}/before.json"
after_snapshot="${temporary_root}/after.json"
retry_snapshot="${temporary_root}/retry.json"
migration_path="${temporary_root}/phase-g-migrations"
worktree_created=false

cleanup() {
    local exit_code=$?

    if [[ "${worktree_created}" == "true" ]]; then
        git -C "${REPOSITORY_ROOT}" worktree remove --force "${pre_phase_g_worktree}" >/dev/null 2>&1 || true
    fi

    rm -rf -- "${temporary_root}"
    exit "${exit_code}"
}
trap cleanup EXIT INT TERM

fail() {
    printf 'Phase G history verification failed: %s\n' "$*" >&2
    exit 1
}

[[ -f "${HARNESS_PHP}" ]] || fail "support script is missing: ${HARNESS_PHP}"
git -C "${REPOSITORY_ROOT}" cat-file -e "${PRE_PHASE_G_COMMIT}^{commit}" 2>/dev/null \
    || fail "pre-Phase-G commit ${PRE_PHASE_G_COMMIT} is unavailable"
git -C "${REPOSITORY_ROOT}" cat-file -e "${PHASE_G_COMMIT}^{commit}" 2>/dev/null \
    || fail "Phase G commit ${PHASE_G_COMMIT} is unavailable"

mkdir -p -- "${migration_path}"
touch -- "${database_path}"

git -C "${REPOSITORY_ROOT}" worktree add --detach "${pre_phase_g_worktree}" "${PRE_PHASE_G_COMMIT}" >/dev/null
worktree_created=true

git -C "${REPOSITORY_ROOT}" show \
    "${PHASE_G_COMMIT}:database/migrations/2026_07_28_020000_add_multi_outlet_foundation.php" \
    > "${migration_path}/2026_07_28_020000_add_multi_outlet_foundation.php"
git -C "${REPOSITORY_ROOT}" show \
    "${PHASE_G_COMMIT}:database/migrations/2026_07_28_020100_create_outlet_stock_transfers.php" \
    > "${migration_path}/2026_07_28_020100_create_outlet_stock_transfers.php"

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
printf 'Migrating historical schema at %s...\n' "${PRE_PHASE_G_COMMIT:0:7}"
php "${HARNESS_PHP}" migrate \
    --app-root="${pre_phase_g_worktree}" \
    --repository-root="${REPOSITORY_ROOT}"

printf 'Creating deterministic historical fixture and before snapshot...\n'
php "${HARNESS_PHP}" seed \
    --app-root="${pre_phase_g_worktree}" \
    --repository-root="${REPOSITORY_ROOT}"
php "${HARNESS_PHP}" snapshot \
    --app-root="${pre_phase_g_worktree}" \
    --repository-root="${REPOSITORY_ROOT}" \
    --stage=before \
    --output="${before_snapshot}"

printf 'Applying Phase G migrations at %s to the same database...\n' "${PHASE_G_COMMIT:0:7}"
php "${HARNESS_PHP}" migrate \
    --app-root="${pre_phase_g_worktree}" \
    --repository-root="${REPOSITORY_ROOT}" \
    --migration-path="${migration_path}"
php "${HARNESS_PHP}" snapshot \
    --app-root="${pre_phase_g_worktree}" \
    --repository-root="${REPOSITORY_ROOT}" \
    --stage=after \
    --output="${after_snapshot}"

printf 'Comparing protected history and retrying the safe outlet backfill boundary...\n'
php "${HARNESS_PHP}" verify \
    --app-root="${pre_phase_g_worktree}" \
    --repository-root="${REPOSITORY_ROOT}" \
    --before="${before_snapshot}" \
    --after="${after_snapshot}" \
    --retry-output="${retry_snapshot}"

printf 'Temporary database, snapshots, migrations, and worktree will now be removed.\n'
