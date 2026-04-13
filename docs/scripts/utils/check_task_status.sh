#!/bin/bash
# 快速查询任务状态的脚本

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
cd "$PROJECT_ROOT"

TASK_ID=$1

if [ -z "$TASK_ID" ]; then
    echo "用法: ./docs/scripts/utils/check_task_status.sh <任务ID>"
    echo ""
    echo "显示最近5个任务的状态："
    sqlite3 data/db/blog.db <<EOF
.mode column
.headers on
SELECT 
    id, 
    name, 
    status,
    batch_status, 
    substr(batch_error_message, 1, 50) as message,
    batch_success_count as success,
    batch_error_count as errors
FROM tasks 
ORDER BY id DESC 
LIMIT 5;
EOF
else
    echo "=== 任务 #$TASK_ID 详细状态 ==="
    sqlite3 data/db/blog.db <<EOF
.mode line
SELECT 
    id, 
    name, 
    status,
    batch_status, 
    batch_started_at,
    batch_stopped_at,
    batch_completed_at,
    batch_success_count,
    batch_error_count,
    batch_error_message
FROM tasks 
WHERE id = $TASK_ID;
EOF
    
    echo ""
    echo "=== 最近的日志（最后20行）==="
    if [ -f "logs/batch_${TASK_ID}.log" ]; then
        tail -20 "logs/batch_${TASK_ID}.log"
    else
        echo "日志文件不存在"
    fi
fi
