#!/bin/bash
# 创建修复前的备份
# 
# @date 2026-02-02

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  📦 创建任务启动/终止逻辑修复前的备份"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# 让脚本从 docs/scripts/maintenance/ 正确定位到项目根目录
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
cd "$PROJECT_ROOT"

# 备份目录
BACKUP_DIR="备份"

# 确保备份目录存在
if [ ! -d "$BACKUP_DIR" ]; then
    echo "❌ 错误：备份目录不存在"
    exit 1
fi

echo "📁 备份目录: $BACKUP_DIR"
echo ""

# 备份文件列表
FILES_TO_BACKUP=(
    "admin/tasks-new.php"
    "admin/start_task_batch.php"
    "bin/batch_execute_task.php"
    "admin/task_health_check.php"
    "admin/execute_task.php"
    "includes/functions.php"
)

# 执行备份
SUCCESS_COUNT=0
FAIL_COUNT=0

for file in "${FILES_TO_BACKUP[@]}"; do
    if [ -f "$file" ]; then
        # 获取文件名（不含路径）
        filename=$(basename "$file")
        
        # 复制文件
        cp "$file" "$BACKUP_DIR/$filename"
        
        if [ $? -eq 0 ]; then
            echo "✅ 已备份: $file → $BACKUP_DIR/$filename"
            ((SUCCESS_COUNT++))
        else
            echo "❌ 备份失败: $file"
            ((FAIL_COUNT++))
        fi
    else
        echo "⚠️  文件不存在: $file"
        ((FAIL_COUNT++))
    fi
done

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  📊 备份统计"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "✅ 成功: $SUCCESS_COUNT 个文件"
echo "❌ 失败: $FAIL_COUNT 个文件"
echo ""

if [ $FAIL_COUNT -eq 0 ]; then
    echo "🎉 备份完成！可以开始修复"
    echo ""
    echo "恢复方法："
    echo "  cp 备份/*.php admin/"
    echo "  cp 备份/batch_execute_task.php ./bin/"
    echo "  cp 备份/functions.php includes/"
    echo ""
    exit 0
else
    echo "⚠️  备份过程中有错误，请检查"
    exit 1
fi
