#!/bin/sh
set -eu

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
STATE_DIR="$SCRIPT_DIR/state"
COUNTER_FILE="$STATE_DIR/turn_counter.txt"
LAST_SYNC_FILE="$STATE_DIR/last_sync_turn.txt"

mkdir -p "$STATE_DIR"

if [ ! -f "$COUNTER_FILE" ]; then
  echo 0 > "$COUNTER_FILE"
fi

CURRENT_TURN=$(cat "$COUNTER_FILE")
NEXT_TURN=$((CURRENT_TURN + 1))
echo "$NEXT_TURN" > "$COUNTER_FILE"

echo "[docs-sync] turn=$NEXT_TURN"

if [ $((NEXT_TURN % 10)) -ne 0 ]; then
  exit 0
fi

sh "$SCRIPT_DIR/sync_docs.sh" "docs sync: auto turn $NEXT_TURN"
echo "$NEXT_TURN" > "$LAST_SYNC_FILE"
echo "[docs-sync] auto sync completed at turn=$NEXT_TURN"
