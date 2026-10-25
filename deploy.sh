#!/usr/bin/env bash
#
# CoffeeProject production deploy. Run on the server as the app user, from the repo
# root (GitHub Actions does this over SSH after the checks pass):
#
#   ./deploy.sh [branch]
#
# Everything lives in main() so bash parses the whole script before running it;
# the script can safely update itself when it resets to the new commit.

set -euo pipefail

main() {
    local branch="${1:-main}"
    local root
    root="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
    local app="$root/src"

    cd "$root"
    echo "==> Fetching origin/$branch"
    git fetch --prune origin "$branch"

    local current target
    current="$(git rev-parse HEAD)"
    target="$(git rev-parse "origin/$branch")"
    echo "==> Deploying ${current:0:7} -> ${target:0:7}"

    cd "$app"
    echo "==> Maintenance mode on"
    php artisan down --retry=30 || true
    # Always bring the site back, even if a step below fails.
    # Expanded now: $app is local to main() and gone by the time EXIT fires.
    # shellcheck disable=SC2064
    trap "cd '$app' && php artisan up" EXIT

    cd "$root"
    # The server never has local edits; src/.env and storage/ are untracked.
    git reset --hard "$target"

    cd "$app"
    echo "==> Composer"
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

    # The previous release's route/config caches would hide new routes from
    # Wayfinder during the frontend build. "Caches" below rebuilds them.
    echo "==> Clearing caches"
    php artisan optimize:clear

    echo "==> Frontend build"
    npm ci --no-audit --no-fund
    npm run build

    echo "==> Migrations"
    php artisan migrate --force

    echo "==> Caches"
    php artisan optimize
    php artisan storage:link >/dev/null 2>&1 || true

    echo "==> Restarting queue workers and PHP-FPM"
    php artisan queue:restart
    # OPcache has validate_timestamps=0 in production, so reload PHP-FPM to
    # pick up the new code. Allowed without a password by deploy/sudoers.
    sudo /usr/bin/systemctl reload php8.5-fpm

    echo "==> Deployed $(git -C "$root" rev-parse --short HEAD)"
}

main "$@"
