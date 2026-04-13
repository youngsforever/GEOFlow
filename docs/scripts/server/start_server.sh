#!/bin/bash

# 智能GEO内容系统 - 服务器启动脚本
# 作者：姚金刚
# 版本：1.0

echo "=== 智能GEO内容系统服务器启动脚本 ==="
echo "时间：$(date)"
echo ""

# 检查是否已有服务器在运行
if lsof -i :8081 >/dev/null 2>&1; then
    echo "⚠️  检测到端口8081已被占用"
    echo "正在查找占用进程..."
    
    # 显示占用进程
    lsof -i :8081
    
    echo ""
    read -p "是否要终止现有进程并重新启动？(y/N): " -n 1 -r
    echo ""
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "正在终止现有进程..."
        # 终止占用8081端口的进程
        lsof -ti :8081 | xargs kill -9 2>/dev/null
        sleep 2
        echo "✅ 现有进程已终止"
    else
        echo "❌ 取消启动"
        exit 1
    fi
fi

# 检查PHP是否可用
if ! command -v php &> /dev/null; then
    echo "❌ 错误：未找到PHP，请确保PHP已安装并在PATH中"
    exit 1
fi

echo "✅ PHP版本：$(php -v | head -n 1)"

# 检查必要文件
if [ ! -f "router.php" ]; then
    echo "❌ 错误：未找到router.php文件"
    exit 1
fi

if [ ! -f "data/db/blog.db" ]; then
    echo "❌ 错误：未找到数据库文件 data/db/blog.db"
    exit 1
fi

echo "✅ 必要文件检查通过"

# 启动服务器
echo ""
echo "🚀 正在启动PHP开发服务器..."
echo "📍 地址：http://localhost:8081"
echo "📁 根目录：$(pwd)"
echo ""
echo "按 Ctrl+C 停止服务器"
echo "=========================="

# 启动PHP内置服务器
php -S localhost:8081 router.php
