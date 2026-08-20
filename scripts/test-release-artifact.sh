#!/usr/bin/env bash
#
# Prove the release packaging pipeline cannot ship untracked or dev-only
# content, regardless of what .distignore currently lists.
#
# Two cases, both assembled with the real, current .distignore:
#   1. A package built from tracked files only, with nothing extra, must
#      pass verify-release.sh.
#   2. The same package with a couple of untracked, working-tree-only files
#      dropped in (an env file and a credential-like dotfile that no named
#      .distignore pattern happens to cover) must be REJECTED. Nothing in
#      .distignore is taught about these two specific names - only
#      verify-release.sh's generic dotfile/dev-config backstop can catch
#      them, so this is what pins that backstop rather than a name that
#      happens to already be listed.
#
# This is what pins the fix for "release ZIP can leak ignored files and
# credentials": it fails if build-release.sh regresses back to copying the
# raw working tree, or if verify-release.sh's backstop is weakened.
#
# Usage: scripts/test-release-artifact.sh

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

log() { printf '==> %s\n' "$1"; }
fail() { printf 'FAIL: %s\n' "$1" >&2; exit 1; }

for cmd in git tar rsync zip unzip; do
	command -v "$cmd" >/dev/null 2>&1 || fail "required command not found: $cmd"
done

work="$(mktemp -d)"
cleanup() { rm -rf "$work"; }
trap cleanup EXIT

# verify-release.sh only cares that these runtime assets exist and are
# non-trivial; stubbing them keeps this test independent of composer/pnpm.
stub_runtime_assets() {
	local root="$1"
	mkdir -p "$root/vendor" "$root/assets/js" "$root/assets/css"
	: >"$root/vendor/autoload.php"
	head -c 2048 /dev/urandom >"$root/assets/js/builder.js"
	: >"$root/assets/css/builder.css"
}

log "Case 1: a tracked-files-only build with the real .distignore must pass"

good_src="$work/good-src"
mkdir -p "$good_src"
git -C "$repo_root" archive HEAD | tar -x -C "$good_src"
stub_runtime_assets "$good_src"

good_pkg_root="$work/good-package"
good_pkg="$good_pkg_root/formtura"
mkdir -p "$good_pkg"
rsync -a --exclude-from="$good_src/.distignore" "$good_src/" "$good_pkg/"

good_zip="$work/formtura-good.zip"
(cd "$good_pkg_root" && zip -qr "$good_zip" formtura)

"$repo_root/scripts/verify-release.sh" "$good_zip" \
	|| fail "a tracked-only package was rejected by verify-release.sh (it should pass)"

# Tracked dev tooling reported in the same incident (ESLint/Playwright
# config, a stray editor workspace file) - these are tracked, so only
# .distignore keeps them out; verify-release.sh doesn't check for these
# specific names, so check the assembled package directly.
for dev_file in .eslintrc.json playwright.config.js; do
	[ -e "$good_pkg/$dev_file" ] && fail "$dev_file shipped in the release package"
done
if find "$good_pkg" -name '*.code-workspace' -print -quit | grep -q .; then
	fail "an editor workspace file shipped in the release package"
fi

log "Case 2: untracked content the named .distignore patterns don't cover must be rejected"

leaky_src="$work/leaky-src"
mkdir -p "$leaky_src"
git -C "$repo_root" archive HEAD | tar -x -C "$leaky_src"
stub_runtime_assets "$leaky_src"

# A stand-in for a credential file (.npmrc holds registry auth tokens in
# real projects) plus a synthetic name .distignore can never coincidentally
# grow a pattern for. Neither should appear in .distignore, so only
# verify-release.sh's generic dotfile/dev-config backstop can catch them.
printf '//registry.npmjs.org/:_authToken=leaked-token\n' >"$leaky_src/.npmrc"
printf 'leaked\n' >"$leaky_src/.formtura-test-leak-marker"

leaky_pkg_root="$work/leaky-package"
leaky_pkg="$leaky_pkg_root/formtura"
mkdir -p "$leaky_pkg"
rsync -a --exclude-from="$leaky_src/.distignore" "$leaky_src/" "$leaky_pkg/"

[ -e "$leaky_pkg/.npmrc" ] || fail "test setup error: .distignore already excludes .npmrc, this case no longer isolates the backstop"
[ -e "$leaky_pkg/.formtura-test-leak-marker" ] || fail "test setup error: .distignore already excludes the synthetic marker file, this case no longer isolates the backstop"

leaky_zip="$work/formtura-leaky.zip"
(cd "$leaky_pkg_root" && zip -qr "$leaky_zip" formtura)

if "$repo_root/scripts/verify-release.sh" "$leaky_zip" >/dev/null 2>&1; then
	fail "verify-release.sh accepted a package containing .npmrc and a stray dotfile - the dotfile backstop is not working"
fi

log "OK: release packaging keeps untracked and dev-only content out"
