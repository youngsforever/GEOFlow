#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
STATE_DIR="$SCRIPT_DIR/state"
COUNTER_FILE="$STATE_DIR/turn_counter.txt"
LAST_SYNC_TURN_FILE="$STATE_DIR/last_sync_turn.txt"
LAST_SYNC_AT_FILE="$STATE_DIR/last_sync_at.txt"
LAST_PUSH_AT_FILE="$STATE_DIR/last_push_at.txt"
REPO_DIR="$SCRIPT_DIR/repo"

TURN_COUNT="0"
LAST_SYNC_TURN="never"
LAST_SYNC_AT="never"
LAST_PUSH_AT="never"

if [ -f "$COUNTER_FILE" ]; then
  TURN_COUNT=$(cat "$COUNTER_FILE")
fi

if [ -f "$LAST_SYNC_TURN_FILE" ]; then
  LAST_SYNC_TURN=$(cat "$LAST_SYNC_TURN_FILE")
fi

if [ -f "$LAST_SYNC_AT_FILE" ]; then
  LAST_SYNC_AT=$(cat "$LAST_SYNC_AT_FILE")
fi

if [ -f "$LAST_PUSH_AT_FILE" ]; then
  LAST_PUSH_AT=$(cat "$LAST_PUSH_AT_FILE")
fi

echo "turn_count=$TURN_COUNT"
echo "last_sync_turn=$LAST_SYNC_TURN"
echo "last_sync_at=$LAST_SYNC_AT"
echo "last_push_at=$LAST_PUSH_AT"

if [ -d "$REPO_DIR/.git" ]; then
  echo "repo_status=initialized"
  if git -C "$REPO_DIR" remote get-url origin >/dev/null 2>&1; then
    echo "remote_origin=$(git -C "$REPO_DIR" remote get-url origin)"
  else
    echo "remote_origin=missing"
  fi
  git -C "$REPO_DIR" log --oneline -n 3 2>/dev/null || true
else
  echo "repo_status=missing"
fi
