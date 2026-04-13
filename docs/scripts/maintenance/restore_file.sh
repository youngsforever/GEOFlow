#!/bin/bash
# 恢复被删除的 task_health_check.php

cd "$(dirname "$0")"

echo "恢复 task_health_check.php..."
cp 备份/task_health_check.php admin/task_health_check.php

if [ -f "admin/task_health_check.php" ]; then
    echo "✅ 文件已恢复"
    ls -lh admin/task_health_check.php
else
    echo "❌ 恢复失败"
    exit 1
fi

