#!/usr/bin/env bash
set -uo pipefail
cd "$(dirname "$(readlink -f "$0")")" || exit 1

: "${ADZERCMS_URL:?instance address}"
: "${ADZERCMS_LOGIN:?operator login}"
: "${ADZERCMS_PASSWORD:?operator password}"

[ -d node_modules ] || npm install --no-audit --no-fund >/dev/null || exit 1
npx playwright install chromium-headless-shell >/dev/null 2>&1

npx playwright test "$@"
