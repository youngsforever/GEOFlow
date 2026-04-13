#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
REPO_DIR="$SCRIPT_DIR/repo"
REMOTE_URL=${1:-}

if [ -z "$REMOTE_URL" ]; then
  echo "usage: sh docs/git/setup_remote.sh <github-repo-url>" >&2
  exit 1
fi

if [ ! -d "$REPO_DIR/.git" ]; then
  echo "[docs-sync] repo is not initialized: $REPO_DIR" >&2
  exit 1
fi

if git -C "$REPO_DIR" remote get-url origin >/dev/null 2>&1; then
  git -C "$REPO_DIR" remote set-url origin "$REMOTE_URL"
  echo "[docs-sync] updated origin -> $REMOTE_URL"
else
  git -C "$REPO_DIR" remote add origin "$REMOTE_URL"
  echo "[docs-sync] added origin -> $REMOTE_URL"
fi
