#!/bin/bash

# GEO+AI内容生成系统 - Cron任务设置脚本
# 作者: 姚金刚
# 版本: 1.0
# 日期: 2025-10-05

echo "⏰ 设置 GEO+AI内容生成系统 定时任务..."

# 获取脚本所在目录的绝对路径
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
CRON_FILE="$PROJECT_ROOT/bin/cron.php"
LOG_FILE="$PROJECT_ROOT/logs/cron.log"

# 检查 bin/cron.php 文件是否存在
if [ ! -f "$CRON_FILE" ]; then
    echo "❌ 错误: bin/cron.php 文件不存在"
    exit 1
fi

# 检查PHP是否可用
if ! command -v php &> /dev/null; then
    echo "❌ 错误: PHP未安装或不在PATH中"
    exit 1
fi

echo "📍 项目路径: $PROJECT_ROOT"
echo "📄 任务调度器: $CRON_FILE"

# 创建cron任务条目
CRON_ENTRY="*/5 * * * * cd $PROJECT_ROOT && php bin/cron.php >> $LOG_FILE 2>&1"

echo ""
echo "🔧 建议的Cron任务配置："
echo "   $CRON_ENTRY"
echo ""

# 询问用户是否要自动添加cron任务
read -p "是否要自动添加此cron任务到当前用户的crontab？(y/n): " -n 1 -r
echo

if [[ $REPLY =~ ^[Yy]$ ]]; then
    # 备份当前crontab
    echo "💾 备份当前crontab..."
    crontab -l > "$SCRIPT_DIR/crontab.backup" 2>/dev/null || echo "# 空的crontab" > "$SCRIPT_DIR/crontab.backup"
    
    # 检查是否已存在相同的任务
    if crontab -l 2>/dev/null | grep -q "bin/cron.php"; then
        echo "⚠️  检测到已存在的cron任务，跳过添加"
    else
        # 添加新的cron任务
        (crontab -l 2>/dev/null; echo "$CRON_ENTRY") | crontab -
        echo "✅ Cron任务已添加成功"
    fi
    
    echo ""
    echo "📋 当前的cron任务列表："
    crontab -l
    
else
    echo "⏭️  跳过自动添加，请手动添加以下cron任务："
    echo "   1. 运行命令: crontab -e"
    echo "   2. 添加以下行:"
    echo "      $CRON_ENTRY"
    echo "   3. 保存并退出"
fi

echo ""
echo "📝 说明："
echo "   - 此任务每5分钟执行一次"
echo "   - 日志文件: $LOG_FILE"
echo "   - 如需修改频率，请编辑cron表达式"
echo "   - 如需停止任务，运行: crontab -e 并删除对应行"
echo ""

# 创建日志目录
mkdir -p "$PROJECT_ROOT/logs"

# 测试运行一次
echo "🧪 测试运行任务调度器..."
cd "$PROJECT_ROOT"
php bin/cron.php

echo ""
echo "✅ Cron任务设置完成！"
echo "💡 提示: 可以通过查看 $LOG_FILE 监控任务执行情况"
