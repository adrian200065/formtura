#!/usr/bin/env bash
#
# Verify a Formtura release ZIP is installable and free of development content.
#
# This is the executable definition of "a valid release package". It is run by
# build-release.sh before any ZIP is published, and by CI against the built
# artifact. A source archive (git archive, GitHub's "Download ZIP") must fail
# it, because such an archive has no vendor/ and no compiled builder assets.
#
# Usage: scripts/verify-release.sh <zip-path>

set -euo pipefail

if [ "$#" -ne 1 ]; then
	echo "Usage: $0 <zip-path>" >&2
	exit 2
fi

zip_path="$1"

if [ ! -f "$zip_path" ]; then
	echo "FAIL: no such ZIP: $zip_path" >&2
	exit 1
fi

for cmd in unzip; do
	if ! command -v "$cmd" >/dev/null 2>&1; then
		echo "FAIL: required command not found: $cmd" >&2
		exit 1
	fi
done

extract_dir="$(mktemp -d)"
cleanup() { rm -rf "$extract_dir"; }
trap cleanup EXIT

unzip -qq "$zip_path" -d "$extract_dir"

# Runtime files without which the plugin cannot boot or render its builder.
required=(
	formtura/formtura.php
	formtura/uninstall.php
	formtura/vendor/autoload.php
	formtura/assets/js/builder.js
	formtura/assets/css/builder.css
	formtura/src
	formtura/templates
)

# Development-only content that must never ship to a user's site.
forbidden=(
	formtura/node_modules
	formtura/tests
	formtura/builder
	formtura/.git
	formtura/.github
	formtura/docs
	formtura/scripts
	formtura/composer.lock
	formtura/pnpm-lock.yaml
	formtura/package.json
	formtura/vite.config.js
	formtura/phpunit.xml
	formtura/.eslintrc.json
	formtura/playwright.config.js
)

failures=0

for path in "${required[@]}"; do
	if [ ! -e "$extract_dir/$path" ]; then
		echo "FAIL: missing required path: $path" >&2
		failures=$((failures + 1))
	fi
done

for path in "${forbidden[@]}"; do
	if [ -e "$extract_dir/$path" ]; then
		echo "FAIL: forbidden development path present: $path" >&2
		failures=$((failures + 1))
	fi
done

# The builder bundle must be a real production build, not an empty placeholder.
builder_js="$extract_dir/formtura/assets/js/builder.js"
if [ -f "$builder_js" ]; then
	size=$(wc -c <"$builder_js")
	if [ "$size" -lt 1024 ]; then
		echo "FAIL: assets/js/builder.js is only ${size} bytes; expected a production bundle" >&2
		failures=$((failures + 1))
	fi
fi

# Dotfiles and dot-directories are never runtime content for this plugin -
# they're where env files, credentials, and editor/agent tooling state live
# (.env*, .git, .github, .claude, .vscode, ...). This is a backstop, not the
# primary defense: it catches anything a .distignore pattern doesn't yet
# name, rather than requiring every possible leak to be enumerated by hand.
# .gitkeep is the sole standard exception (an empty-directory placeholder).
while IFS= read -r -d '' bad_path; do
	echo "FAIL: forbidden dotfile/dev-config present: ${bad_path#"$extract_dir/"}" >&2
	failures=$((failures + 1))
done < <(find "$extract_dir/formtura" -mindepth 1 \( -name '.*' -a ! -name '.gitkeep' \) -print0)

# Editor workspace files (e.g. VS Code's *.code-workspace) can be tracked
# anywhere in the tree, not just the root, so .distignore's named patterns
# get the same generic backstop.
while IFS= read -r -d '' bad_path; do
	echo "FAIL: forbidden editor workspace file present: ${bad_path#"$extract_dir/"}" >&2
	failures=$((failures + 1))
done < <(find "$extract_dir/formtura" -name '*.code-workspace' -print0)

# Composer dev dependencies must not have been installed into the package.
if [ -d "$extract_dir/formtura/vendor/phpunit" ]; then
	echo "FAIL: vendor/phpunit present; package was built without --no-dev" >&2
	failures=$((failures + 1))
fi

# Every PHP file in the package must parse, so a syntax error can never ship.
if command -v php >/dev/null 2>&1; then
	while IFS= read -r -d '' php_file; do
		if ! php -l "$php_file" >/dev/null 2>&1; then
			echo "FAIL: PHP syntax error in ${php_file#"$extract_dir/"}" >&2
			failures=$((failures + 1))
		fi
	done < <(find "$extract_dir/formtura" -path "$extract_dir/formtura/vendor" -prune -o -name '*.php' -print0)
fi

if [ "$failures" -gt 0 ]; then
	echo "FAIL: $failures problem(s) found in $zip_path" >&2
	exit 1
fi

echo "OK: $zip_path is a complete, installable release package"
