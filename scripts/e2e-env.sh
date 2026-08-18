#!/usr/bin/env bash
#
# Provision (or tear down) a disposable WordPress instance for E2E tests.
#
# The instance lives entirely outside the repo, in a temp directory, with
# the plugin symlinked in from the working tree - so `up` always exercises
# whatever is on disk (uncommitted changes included), and `down` leaves
# nothing behind but a dropped database. The same script runs locally and
# in CI; only the DB credentials differ, via env vars.
#
# Usage:
#   scripts/e2e-env.sh up
#   scripts/e2e-env.sh down
#
# Env vars (all optional, defaults suit a GitHub Actions mysql: service):
#   E2E_DB_HOST   default: 127.0.0.1
#   E2E_DB_USER   default: root
#   E2E_DB_PASS   default: root
#   E2E_DB_NAME   default: formtura_e2e
#   E2E_PORT      default: 8890
#
# A .env.e2e file in the repo root, if present, is sourced first - use it
# for machine-local DB credentials that shouldn't be committed.

set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
state_file="/tmp/formtura-e2e-env.state"

log() { printf '==> %s\n' "$1"; }
fail() { printf 'ERROR: %s\n' "$1" >&2; exit 1; }

if [ -f "$repo_root/.env.e2e" ]; then
	source "$repo_root/.env.e2e"
fi

E2E_DB_HOST="${E2E_DB_HOST:-127.0.0.1}"
E2E_DB_USER="${E2E_DB_USER:-root}"
E2E_DB_PASS="${E2E_DB_PASS:-root}"
E2E_DB_NAME="${E2E_DB_NAME:-formtura_e2e}"
E2E_PORT="${E2E_PORT:-8890}"

mysql_cmd() { mysql --host="$E2E_DB_HOST" --user="$E2E_DB_USER" --password="$E2E_DB_PASS" "$@"; }

cmd_up() {
	for tool in wp php mysql; do
		command -v "$tool" >/dev/null 2>&1 || fail "required command not found: $tool"
	done

	[ -f "$repo_root/vendor/autoload.php" ] || fail "vendor/autoload.php missing - run 'composer install' first"
	[ -f "$repo_root/assets/js/builder.js" ] || fail "assets/js/builder.js missing - run 'pnpm run build' first"

	if [ -f "$state_file" ]; then
		log "Existing E2E environment found, tearing it down first"
		cmd_down
	fi

	# A previous run that was killed rather than torn down (Ctrl-C, a CI
	# timeout) can leave its PHP server bound to the port with no state file
	# to find it by - free the port unconditionally before claiming it.
	local stale_pid
	stale_pid="$(ss -ltnp 2>/dev/null | awk -v p=":$E2E_PORT" '$4 ~ p {print $0}' | grep -oP 'pid=\K[0-9]+' | head -1)" || true
	if [ -n "$stale_pid" ]; then
		log "Port $E2E_PORT is already in use (pid $stale_pid) - killing it"
		kill "$stale_pid" 2>/dev/null || true
		sleep 1
	fi

	local workspace
	workspace="$(mktemp -d)"

	log "Dropping and recreating database $E2E_DB_NAME"
	mysql_cmd -e "DROP DATABASE IF EXISTS \`$E2E_DB_NAME\`; CREATE DATABASE \`$E2E_DB_NAME\`;"

	log "Downloading WordPress core into $workspace"
	wp core download --path="$workspace" --quiet

	log "Configuring wp-config.php"
	wp config create \
		--path="$workspace" \
		--dbname="$E2E_DB_NAME" \
		--dbuser="$E2E_DB_USER" \
		--dbpass="$E2E_DB_PASS" \
		--dbhost="$E2E_DB_HOST" \
		--quiet

	log "Installing WordPress"
	wp core install \
		--path="$workspace" \
		--url="http://127.0.0.1:$E2E_PORT" \
		--title="Formtura E2E" \
		--admin_user=admin \
		--admin_password=admin \
		--admin_email=admin@example.test \
		--skip-email \
		--quiet

	log "Symlinking the plugin from the working tree"
	rm -rf "$workspace/wp-content/plugins/formtura"
	ln -s "$repo_root" "$workspace/wp-content/plugins/formtura"

	log "Deactivating default plugins, activating only Formtura"
	wp plugin deactivate --all --path="$workspace" --quiet || true
	wp plugin activate formtura --path="$workspace" --quiet

	log "Installing the mail-capture mu-plugin (no real email is ever sent)"
	mkdir -p "$workspace/wp-content/mu-plugins"
	ln -s "$repo_root/tests/e2e/fixtures/mu-plugins/e2e-mail-log.php" \
		"$workspace/wp-content/mu-plugins/e2e-mail-log.php"
	rm -f "$workspace/e2e-mail-log.json"

	log "Starting the PHP built-in server on 127.0.0.1:$E2E_PORT"
	(
		cd "$workspace"
		nohup php -S "127.0.0.1:$E2E_PORT" >"$workspace/php-server.log" 2>&1 &
		echo $! >"$workspace/php-server.pid"
	)

	for _ in $(seq 1 30); do
		if curl -sf "http://127.0.0.1:$E2E_PORT/wp-login.php" >/dev/null 2>&1; then
			break
		fi
		sleep 0.5
	done
	curl -sf "http://127.0.0.1:$E2E_PORT/wp-login.php" >/dev/null 2>&1 \
		|| fail "PHP built-in server did not come up - see $workspace/php-server.log"

	# $! above is unreliable here (this PHP build re-execs itself once it
	# parses -S, so the backgrounded PID and the PID actually holding the
	# port differ) - record the PID really bound to the port instead, or
	# `down` silently fails to stop the server.
	local listening_pid
	listening_pid="$(ss -ltnp 2>/dev/null | awk -v p=":$E2E_PORT" '$4 ~ p {print $0}' | grep -oP 'pid=\K[0-9]+' | head -1)" || true
	if [ -n "$listening_pid" ]; then
		echo "$listening_pid" >"$workspace/php-server.pid"
	fi

	cat >"$state_file" <<-EOF
	WORKSPACE=$workspace
	E2E_DB_HOST=$E2E_DB_HOST
	E2E_DB_USER=$E2E_DB_USER
	E2E_DB_PASS=$E2E_DB_PASS
	E2E_DB_NAME=$E2E_DB_NAME
	EOF

	log "E2E environment ready at http://127.0.0.1:$E2E_PORT (mail log: $workspace/e2e-mail-log.json)"
}

cmd_down() {
	[ -f "$state_file" ] || { log "No E2E environment state found, nothing to tear down"; return 0; }

	# shellcheck source=/dev/null
	source "$state_file"

	if [ -n "${WORKSPACE:-}" ] && [ -f "$WORKSPACE/php-server.pid" ]; then
		log "Stopping the PHP built-in server"
		kill "$(cat "$WORKSPACE/php-server.pid")" 2>/dev/null || true
	fi

	if [ -n "${E2E_DB_NAME:-}" ]; then
		log "Dropping database $E2E_DB_NAME"
		mysql --host="$E2E_DB_HOST" --user="$E2E_DB_USER" --password="$E2E_DB_PASS" \
			-e "DROP DATABASE IF EXISTS \`$E2E_DB_NAME\`;" 2>/dev/null || true
	fi

	[ -n "${WORKSPACE:-}" ] && rm -rf "$WORKSPACE"
	rm -f "$state_file"
	log "E2E environment torn down"
}

case "${1:-}" in
	up) cmd_up ;;
	down) cmd_down ;;
	*) fail "usage: $0 {up|down}" ;;
esac
