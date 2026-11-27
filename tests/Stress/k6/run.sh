#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
K6="$(find "$ROOT/vendor/pestphp/pest-plugin-stressless/bin" -type f -name k6 | head -1)"

if [[ -z "${K6}" ]]; then
    echo "k6 not found. Run \`composer stress\` once to let Stressless download it." >&2
    exit 1
fi

cd "$ROOT"
exec "${K6}" run tests/Stress/k6/multi-user.js "$@"
