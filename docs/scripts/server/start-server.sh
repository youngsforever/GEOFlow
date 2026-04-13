#!/bin/bash

# GEO+AI内容生成系统 - 旧版本地开发服务器启动脚本
# 
# @author 姚金刚
# @version 1.0
# @date 2025-10-03

echo "🚀 启动 GEO+AI 内容系统旧版本地服务器..."
echo ""

# 检查PHP是否安装
if ! command -v php &> /dev/null; then
    echo "❌ 错误：未找到PHP，请先安装PHP"
    exit 1
fi

# 显示PHP版本
PHP_VERSION=$(php --version | head -n 1)
echo "✅ PHP版本：$PHP_VERSION"

# 检查SQLite扩展
if ! php -m | grep -q "pdo_sqlite"; then
    echo "❌ 错误：未找到SQLite扩展，请安装pdo_sqlite扩展"
    exit 1
fi

echo "✅ SQLite扩展：已安装"
echo ""

# 获取项目根目录
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"

# 切换到项目目录
cd "$PROJECT_ROOT"

# 检查是否已经安装
if [ ! -f "data/db/blog.db" ]; then
    echo "⚠️  检测到数据库未初始化"
    echo "🔧 然后访问 http://localhost:8080/install.php 进行安装"
    echo ""
fi

# 启动PHP开发服务器
echo "🌐 启动服务器：http://localhost:8080"
echo "🔧 安装页面：http://localhost:8080/install.php"
echo "🏠 前台首页：http://localhost:8080/"
echo "⚙️  管理后台：http://localhost:8080/admin/"
echo ""
echo "💡 默认管理员账户：admin / yaodashuai"
echo ""
echo "按 Ctrl+C 停止服务器"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# 启动服务器（使用路由器支持URL重写）
php -S localhost:8080 router.php
