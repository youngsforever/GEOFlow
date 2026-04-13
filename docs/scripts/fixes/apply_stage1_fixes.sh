#!/bin/bash
# 阶段1修复脚本：修复静态问题
# 
# 修复内容：
# 1. task_health_check.php - 添加 session_start()
# 2. tasks-new.php - 修正路径错误（第53、57行）
# 3. start_task_batch.php - 使用 proc_open 转义路径
#
# @date 2026-02-02

set -e  # 遇到错误立即退出

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  🔧 阶段1：修复静态问题"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# 获取脚本所在目录
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
cd "$SCRIPT_DIR"

# 确保备份存在
if [ ! -d "备份" ]; then
    echo "❌ 错误：备份目录不存在，请先执行备份"
    exit 1
fi

echo "📋 修复清单："
echo "  1. task_health_check.php - 添加 session_start()"
echo "  2. tasks-new.php - 修正路径错误"
echo "  3. start_task_batch.php - 转义路径参数"
echo ""

read -p "确认开始修复？(y/n) " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ 已取消"
    exit 1
fi

echo ""
echo "🔄 开始修复..."
echo ""

# 修复 1: task_health_check.php - 添加 session_start()
echo "📝 修复 1/3: task_health_check.php"
cp 备份/task_health_check.php admin/task_health_check.php
sed -i.bak '7a\
session_start();
' admin/task_health_check.php
rm admin/task_health_check.php.bak
echo "   ✅ 已在第8行添加 session_start()"

# 修复 2: tasks-new.php - 修正路径
echo "📝 修复 2/3: tasks-new.php"
cp 备份/tasks-new.php admin/tasks-new.php
# 修复第53行
sed -i.bak 's|$stop_file = "logs/stop_{\$task_id}.flag";|$stop_file = dirname(__DIR__) . "/logs/stop_{$task_id}.flag";|' admin/tasks-new.php
# 修复第57行  
sed -i.bak 's|$pid_file = "logs/batch_{\$task_id}.pid";|$pid_file = dirname(__DIR__) . "/logs/batch_{$task_id}.pid";|' admin/tasks-new.php
rm admin/tasks-new.php.bak
echo "   ✅ 已修正第53、57行的路径"

# 修复 3: start_task_batch.php - 使用 proc_open
echo "📝 修复 3/3: start_task_batch.php"
echo "   ⚠️  此修复较复杂，需要手动应用"
echo "   📄 请查看 stage1_fix3_manual.txt 了解详情"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  ✅ 阶段1修复完成（2/3）"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "已完成："
echo "  ✅ task_health_check.php - session_start() 已添加"
echo "  ✅ tasks-new.php - 路径已修正"
echo ""
echo "待手动修复："
echo "  ⏳ start_task_batch.php - 需要手动修改第110行"
echo ""
echo "下一步："
echo "  1. 测试健康检查API是否正常"
echo "  2. 测试任务暂停功能"
echo "  3. 手动修复 start_task_batch.php"
echo ""

