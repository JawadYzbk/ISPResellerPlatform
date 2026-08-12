#!/bin/sh

set -eu

lock_hash="$(sha256sum package-lock.json | awk '{print $1}')"
stamp="node_modules/.isp-package-lock.sha256"

if [ ! -f "$stamp" ] || [ "$(cat "$stamp")" != "$lock_hash" ]; then
    npm ci
    printf '%s\n' "$lock_hash" > "$stamp"
fi

source_hash="$({
    sha256sum package.json package-lock.json vite.config.js
    find resources public -type f ! -path 'public/build/*' -print0 | sort -z | xargs -0 sha256sum
} | sha256sum | awk '{print $1}')"
build_stamp="node_modules/.isp-build.sha256"

if [ ! -f "$build_stamp" ] || [ "$(cat "$build_stamp")" != "$source_hash" ] || [ ! -f public/build/manifest.json ]; then
    npm run build
    printf '%s\n' "$source_hash" > "$build_stamp"
fi

exec npm run dev -- --host 0.0.0.0
