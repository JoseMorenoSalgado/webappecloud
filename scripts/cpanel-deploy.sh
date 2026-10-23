#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

: "${APP_ROOT:?Set APP_ROOT to the application directory}"
: "${ARTIFACT:?Set ARTIFACT to the uploaded deployment archive}"
APP_ROOT="$(realpath -m "$APP_ROOT")"
[[ "$APP_ROOT" == "$HOME/"* ]] || { echo "APP_ROOT must be within HOME" >&2; exit 1; }
[[ -f "$ARTIFACT" ]] || { echo "Artifact missing" >&2; exit 1; }
mkdir -p "$APP_ROOT/releases" "$APP_ROOT/shared" "$APP_ROOT/tmp"
[[ -f "$APP_ROOT/shared/.env" ]] || { echo "Create $APP_ROOT/shared/.env before deployment" >&2; exit 1; }
release="$(date -u +%Y%m%d%H%M%S)-${GITHUB_SHA:-manual}"
release="${release:0:48}"
target="$APP_ROOT/releases/$release"
mkdir "$target"
tar -xzf "$ARTIFACT" -C "$target" --no-same-owner --no-same-permissions
ln -s "$APP_ROOT/shared/.env" "$target/.env"
if [[ -d "$APP_ROOT/shared/.runtime" ]]; then
  rm -rf "$target/.runtime"
  ln -s "$APP_ROOT/shared/.runtime" "$target/.runtime"
fi
# The app entrypoint is server.js (Next standalone); public and static assets accompany it.
[[ -f "$target/server.js" ]] || { echo "server.js missing" >&2; exit 1; }
ln -sfn "$target" "$APP_ROOT/current.new"
mv -Tf "$APP_ROOT/current.new" "$APP_ROOT/current"
# Restart mechanism differs between Passenger and custom Node process managers.
# Configure CPANEL_RESTART_COMMAND only after verifying hosting mode.
if [[ -n "${CPANEL_RESTART_COMMAND:-}" ]]; then
  bash -lc "$CPANEL_RESTART_COMMAND"
else
  echo "Release activated; restart app through cPanel Application Manager."
fi
echo "Activated $release"
