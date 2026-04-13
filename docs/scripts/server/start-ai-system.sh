#!/bin/bash

# GEO+AI内容生成系统 - 启动脚本
# 作者: 姚金刚
# 版本: 1.0
# 日期: 2025-10-05

echo "🚀 启动 GEO+AI内容生成系统..."

# 获取脚本所在目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# 检查PHP是否可用
if ! command -v php &> /dev/null; then
    echo "❌ 错误: PHP未安装或不在PATH中"
    exit 1
fi

# 检查端口是否被占用
PORT=8080
if lsof -Pi :$PORT -sTCP:LISTEN -t >/dev/null ; then
    echo "⚠️  端口 $PORT 已被占用，尝试使用端口 8081..."
    PORT=8081
    if lsof -Pi :$PORT -sTCP:LISTEN -t >/dev/null ; then
        echo "❌ 端口 $PORT 也被占用，请手动指定端口"
        exit 1
    fi
fi

# 创建必要的目录
echo "📁 创建必要目录..."
mkdir -p logs
mkdir -p data/db
mkdir -p assets/images

# 设置权限
echo "🔐 设置目录权限..."
chmod 755 logs data assets/images
chmod 644 *.php

# 检查是否已安装
if [ ! -f "data/db/blog.db" ]; then
    echo "⚠️  系统尚未安装，请先访问安装页面："
    echo "   http://localhost:$PORT/install_ai_system.php"
    echo ""
fi

# 启动PHP内置服务器
echo "🌐 启动Web服务器..."
echo "   访问地址: http://localhost:$PORT"
echo "   管理后台: http://localhost:$PORT/admin/dashboard_new.php"
echo "   系统测试: http://localhost:$PORT/test_ai_system.php"
echo ""
echo "💡 提示:"
echo "   - 默认管理员账户: admin"
echo "   - 默认密码: yaodashuai@2025"
echo "   - 按 Ctrl+C 停止服务器"
echo ""

# 启动服务器
php -S localhost:$PORT router.php

echo "👋 GEO+AI内容生成系统已停止"
