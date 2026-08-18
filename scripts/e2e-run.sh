#!/usr/bin/env bash
#
# Provision the disposable E2E WordPress instance, run Playwright against
# it, and tear it down again regardless of the test result.
#
# Usage: scripts/e2e-run.sh [playwright args...]

set -uo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

"$repo_root/scripts/e2e-env.sh" up || exit 1
trap '"$repo_root/scripts/e2e-env.sh" down' EXIT

pnpm exec playwright test "$@"
