#!/usr/bin/env bash
# Call the blog API as the editor agent.
#
#   api.sh GET  /posts status=draft triage=none per_page=20   # query pairs, URL-encoded for you
#   api.sh GET  /posts/kofe-v-dzhezve
#   api.sh POST /posts/kofe-v-dzhezve/publish
#   api.sh PATCH /posts/kofe-v-dzhezve body.json               # JSON body from a file
#   python3 -I make_body.py | api.sh PATCH /posts/x -         # or from stdin
#
# BLOG_API_URL and BLOG_API_TOKEN come from the environment or the repo's .env.
set -euo pipefail

root="$(cd "$(dirname "$0")/../../.." && pwd)"
for var in BLOG_API_URL BLOG_API_TOKEN; do
  if [ -z "${!var:-}" ] && [ -f "$root/.env" ]; then
    value="$(grep -E "^${var}=" "$root/.env" | tail -1 | cut -d= -f2- || true)"
    export "$var=$value"
  fi
done
: "${BLOG_API_URL:?Set BLOG_API_URL in .env}"
: "${BLOG_API_TOKEN:?Set BLOG_API_TOKEN in .env (php artisan api:agent-token blog-editor)}"

method="$1"; path="$2"; shift 2
args=(-sS -X "$method" -H "Authorization: Bearer $BLOG_API_TOKEN" -H "Accept: application/json" -w '\nHTTP %{http_code}\n')

if [ "$method" = "GET" ]; then
  args+=(-G)
  for pair in "$@"; do args+=(--data-urlencode "$pair"); done
elif [ $# -gt 0 ]; then
  if [ "$1" = "-" ]; then
    args+=(-H "Content-Type: application/json" --data-binary @-)
  else
    args+=(-H "Content-Type: application/json" --data-binary "@$1")
  fi
fi

curl "${args[@]}" "${BLOG_API_URL%/}$path"
