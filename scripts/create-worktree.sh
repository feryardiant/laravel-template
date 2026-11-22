#!/usr/bin/env bash

# Provisions a Zed-created git worktree so it can run `php artisan dev` and
# `composer test` on its own ports, session cookie, and databases. Safe to
# re-run: the chosen ports are kept once written to `.env`.

set -euo pipefail

ZED_MAIN_GIT_WORKTREE=${ZED_MAIN_GIT_WORKTREE:-}
ZED_WORKTREE_ROOT=${ZED_WORKTREE_ROOT:-}

if [ -z "$ZED_MAIN_GIT_WORKTREE" ] || [ -z "$ZED_WORKTREE_ROOT" ]; then
    echo "This file should only be called from Zed create worktree" >&2
    exit 1
fi

main=$ZED_MAIN_GIT_WORKTREE
worktree=$ZED_WORKTREE_ROOT

if [ ! -f "$main/.env" ]; then
    echo "Missing $main/.env, configure the main checkout first" >&2
    exit 1
fi

cd "$worktree"

# Zed nests worktrees as ../_.Worktrees/{project}/{name}/{project}.
name=$(basename "$(dirname "$worktree")")
slug=$(printf '%s' "$name" | LC_ALL=C tr '[:upper:]' '[:lower:]' | LC_ALL=C tr -c 'a-z0-9' '-')
slug=${slug:0:30}

env_value() { # file key fallback
    local value
    value=$(awk -F= -v key="$2" '$1 == key { sub(/^[^=]*=/, ""); found = $0 } END { print found }' "$1" 2>/dev/null)
    value=${value%\"}
    value=${value#\"}
    printf '%s' "${value:-$3}"
}

set_env() { # file key value
    if grep -qE "^$2=" "$1" 2>/dev/null; then
        sed -i '' "s|^$2=.*|$2=$3|" "$1"
    else
        printf '%s=%s\n' "$2" "$3" >>"$1"
    fi
}

db_user=$(env_value "$main/.env" DB_USERNAME laravel)
db_slug=${slug//-/_}
db_name="wt_${db_slug}-dev"
db_test="wt_${db_slug}-test"
marker="# worktree: ${slug}"

# Untracked files the worktree cannot get from git.
for file in .env .env.testing boost.json; do
    if [ -f "$main/$file" ] && [ ! -e "$file" ]; then
        cp "$main/$file" "$file"
    fi
done

if [ ! -f .env.testing ]; then
    cp .env .env.testing
fi

# Shared agent material, written back to the main checkout.
for dir in plans skills specs; do
    if [ -d "$main/.agents/$dir" ] && [ ! -e ".agents/$dir" ]; then
        ln -s "$main/.agents/$dir" ".agents/$dir"
    fi
done

# Per-worktree runtime identity. Ports are picked once, then reused on re-runs.
if grep -qF "$marker" .env; then
    app_port=$(env_value .env APP_PORT 8100)
    dev_port=$(env_value .env DEV_PORT 5200)
else
    # shortcut: 100 slots covers the supported workflow (3 worktrees per
    # project, 2 projects); the probe sees live servers, not idle reservations.
    slot=$(( $(printf '%s' "$worktree" | cksum | cut -d' ' -f1) % 100 ))
    app_port=$((8100 + slot))
    dev_port=$((5200 + slot))

    n=0
    while lsof -nP -iTCP:"$app_port" -sTCP:LISTEN >/dev/null 2>&1; do
        app_port=$((app_port + 1))
        n=$((n + 1))
        if [ "$n" -ge 200 ]; then
            echo "No free app port near $((8100 + slot))" >&2
            exit 1
        fi
    done

    n=0
    while lsof -nP -iTCP:"$dev_port" -sTCP:LISTEN >/dev/null 2>&1; do
        dev_port=$((dev_port + 1))
        n=$((n + 1))
        if [ "$n" -ge 200 ]; then
            echo "No free vite port near $((5200 + slot))" >&2
            exit 1
        fi
    done
fi

set_env .env APP_PORT "$app_port"
set_env .env SERVER_PORT "$app_port"
set_env .env DEV_PORT "$dev_port"
set_env .env APP_URL "http://localhost:${app_port}"
set_env .env SESSION_COOKIE "laravel-template-${slug}-session"
set_env .env DB_HOST 127.0.0.1
set_env .env DB_DATABASE "$db_name"
set_env .env.testing APP_ENV testing
set_env .env.testing DB_HOST 127.0.0.1
set_env .env.testing DB_DATABASE "$db_test"
grep -qF "$marker" .env || printf '%s\n' "$marker" >>.env

# Shared infrastructure always comes from the main checkout: compose pins the
# project name, so running it here would reconfigure the main stack.
(cd "$main" && docker compose up -d --wait pgsql mailpit rustfs)

db_exists() {
    (cd "$main" && docker compose exec -T pgsql psql -U "$db_user" -d postgres -tAc \
        "SELECT 1 FROM pg_database WHERE datname = '$1'") | grep -q 1
}

for db in "$db_name" "$db_test"; do
    if ! db_exists "$db"; then
        (cd "$main" && docker compose exec -T pgsql createdb -U "$db_user" "$db")
    fi
done

# Both installs are idempotent and repair a previous run that was interrupted.
composer install --no-interaction --no-progress
npm install --no-audit --no-fund

if ! grep -qE '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

php artisan migrate --seed --force
npm run build
php artisan ide-helper:generate

printf '\nWorktree ready:\n'
printf '  preview   http://localhost:%s\n' "$app_port"
printf '  vite      http://localhost:%s\n' "$dev_port"
printf '  database  %s\n' "$db_name"
printf '  tests     %s\n\n' "$db_test"
printf '  composer run dev   # or php artisan dev\n'
printf '  composer test\n'
