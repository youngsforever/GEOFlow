#!/bin/bash

# GEO+AI内容生成系统 - 停止本地开发服务器脚本
# 
# @author 姚金刚
# @version 1.0
# @date 2025-10-03

echo "🛑 正在停止 GEO+AI 内容系统本地开发服务器..."

# 查找并停止PHP开发服务器进程
PHP_PIDS=$(ps aux | grep "php -S localhost:8080" | grep -v grep | awk '{print $2}')

if [ -z "$PHP_PIDS" ]; then
    echo "ℹ️  没有找到运行中的PHP开发服务器"
else
    echo "🔍 找到PHP服务器进程：$PHP_PIDS"
    for PID in $PHP_PIDS; do
        kill $PID
        echo "✅ 已停止进程 $PID"
    done
    echo "🎉 服务器已成功停止"
fi

echo ""
echo "💡 要重新启动服务器，请运行："
echo "   ./start.sh"
