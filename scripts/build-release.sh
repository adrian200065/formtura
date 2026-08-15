#!/usr/bin/env bash
#
# Build a deterministic, installable Formtura release ZIP.
#
# The build runs in an isolated temporary workspace copied from the working
# tree, with .git, node_modules, vendor and dist excluded. That isolation is
# the point: dependencies and assets are produced from committed manifests, so
# the artifact never inherits stale or hand-modified files that happen to be
# sitting in a developer's checkout.
#
# Usage: scripts/build-release.sh [output-directory]   (default: ./dist)

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
output_dir="${1:-$repo_root/dist}"

log() { printf '==> %s\n' "$1"; }
fail() { printf 'ERROR: %s\n' "$1" >&2; exit 1; }

for cmd in composer pnpm rsync zip unzip php; do
	command -v "$cmd" >/dev/null 2>&1 || fail "required command not found: $cmd"
done

[ -f "$repo_root/.distignore" ] || fail "missing .distignore"

# Single source of truth for the version: the plugin header constant.
version="$(php -r '
	$src = file_get_contents($argv[1]);
	if (!preg_match("/define\(\s*\x27FORMTURA_VERSION\x27\s*,\s*\x27([^\x27]+)\x27/", $src, $m)) {
		fwrite(STDERR, "could not parse FORMTURA_VERSION\n");
		exit(1);
	}
	echo $m[1];
' "$repo_root/formtura.php")" || fail "could not determine plugin version"

log "Building Formtura $version"

workspace="$(mktemp -d)"
cleanup() { rm -rf "$workspace"; }
trap cleanup EXIT

build_src="$workspace/src"
package_root="$workspace/package"
package_dir="$package_root/formtura"

mkdir -p "$build_src" "$package_dir"

log "Copying working tree into isolated workspace"
rsync -a \
	--exclude '.git/' \
	--exclude 'node_modules/' \
	--exclude 'vendor/' \
	--exclude 'dist/' \
	--exclude '.worktrees/' \
	"$repo_root/" "$build_src/"

log "Installing production Composer dependencies"
(
	cd "$build_src"
	composer install --no-dev --classmap-authoritative --no-interaction --quiet
) || fail "composer install failed"

[ -f "$build_src/vendor/autoload.php" ] || fail "composer install produced no vendor/autoload.php"

log "Installing JavaScript dependencies"
(
	cd "$build_src"
	pnpm install --frozen-lockfile --silent
) || fail "pnpm install failed"

log "Building production builder assets"
(
	cd "$build_src"
	pnpm run build
) || fail "asset build failed"

for asset in assets/js/builder.js assets/css/builder.css; do
	[ -f "$build_src/$asset" ] || fail "asset build produced no $asset"
done

log "Assembling runtime package"
rsync -a --exclude-from="$build_src/.distignore" "$build_src/" "$package_dir/"

log "Creating ZIP"
zip_name="formtura-${version}.zip"
(
	cd "$package_root"
	zip -qr "$workspace/$zip_name" formtura
) || fail "zip failed"

log "Verifying package"
"$repo_root/scripts/verify-release.sh" "$workspace/$zip_name" || fail "release verification failed"

mkdir -p "$output_dir"
mv "$workspace/$zip_name" "$output_dir/$zip_name"

log "Release ready"
printf '%s\n' "$output_dir/$zip_name"
