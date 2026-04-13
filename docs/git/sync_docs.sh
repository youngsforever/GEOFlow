#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
ROOT_DIR=$(CDPATH= cd -- "$SCRIPT_DIR/../.." && pwd)
DOCS_DIR="$ROOT_DIR/docs"
REPO_DIR="$SCRIPT_DIR/repo"
SNAPSHOT_DIR="$REPO_DIR/docs_snapshot"
STATE_DIR="$SCRIPT_DIR/state"
LAST_SYNC_FILE="$STATE_DIR/last_sync_at.txt"
LAST_PUSH_FILE="$STATE_DIR/last_push_at.txt"

MESSAGE="${1:-docs sync: manual $(date '+%Y-%m-%d %H:%M:%S')}"

if [ ! -d "$REPO_DIR/.git" ]; then
  echo "[docs-sync] repo is not initialized: $REPO_DIR" >&2
  exit 1
fi

mkdir -p "$STATE_DIR"
mkdir -p "$SNAPSHOT_DIR"

find "$SNAPSHOT_DIR" -mindepth 1 -maxdepth 1 -exec rm -rf {} +

mkdir -p "$SNAPSHOT_DIR/deployment"

find "$DOCS_DIR" \
  \( -path "$DOCS_DIR/git" -o -path "$DOCS_DIR/git/*" \
     -o -path "$DOCS_DIR/archived" -o -path "$DOCS_DIR/archived/*" \
     -o -path "$DOCS_DIR/backups" -o -path "$DOCS_DIR/backups/*" \
     -o -path "$DOCS_DIR/runtime" -o -path "$DOCS_DIR/runtime/*" \
     -o -path "$DOCS_DIR/scripts" -o -path "$DOCS_DIR/scripts/*" \
     -o -path "$DOCS_DIR/maintenance" -o -path "$DOCS_DIR/maintenance/*" \
     -o -path "$DOCS_DIR/diagnostics" -o -path "$DOCS_DIR/diagnostics/*" \) -prune \
  -o \( -name '*.md' -o -name '*.txt' \) -type f -print | while IFS= read -r file; do
    relative_path=${file#"$DOCS_DIR/"}
    target_path="$SNAPSHOT_DIR/$relative_path"
    mkdir -p "$(dirname "$target_path")"
    cp "$file" "$target_path"
  done

if [ -f "$DOCS_DIR/deployment/Caddyfile" ]; then
  cp "$DOCS_DIR/deployment/Caddyfile" "$SNAPSHOT_DIR/deployment/Caddyfile"
fi

git -C "$REPO_DIR" add -A

if git -C "$REPO_DIR" diff --cached --quiet; then
  echo "[docs-sync] no docs changes to commit"
  exit 0
fi

git -C "$REPO_DIR" commit -m "$MESSAGE" >/dev/null
date '+%Y-%m-%d %H:%M:%S' > "$LAST_SYNC_FILE"
echo "[docs-sync] committed: $MESSAGE"

if git -C "$REPO_DIR" remote get-url origin >/dev/null 2>&1; then
  CURRENT_BRANCH=$(git -C "$REPO_DIR" symbolic-ref --short HEAD)
  git -C "$REPO_DIR" push origin "$CURRENT_BRANCH"
  date '+%Y-%m-%d %H:%M:%S' > "$LAST_PUSH_FILE"
  echo "[docs-sync] pushed: origin/$CURRENT_BRANCH"
else
  echo "[docs-sync] remote origin is not configured, skip push"
fi
