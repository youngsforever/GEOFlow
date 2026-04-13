#!/bin/bash

# GEO+AI系统 - 快速访问脚本
# 
# @author 姚金刚
# @date 2026-01-31

echo "🎯 GEO+AI 智能内容生成系统 - 快速访问"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"

# 检查服务器是否运行
if lsof -Pi :8080 -sTCP:LISTEN -t >/dev/null 2>&1; then
    echo "✅ 服务器状态: 运行中"
    echo ""
    echo "📍 访问地址："
    echo "   🌐 前台首页: http://localhost:8080"
    echo "   🔐 后台管理: http://localhost:8080/admin/"
    echo ""
    echo "🔑 管理员账户："
    echo "   用户名: admin"
    echo "   密码: yaodashuai"
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "请选择操作："
    echo "  1) 打开前台首页"
    echo "  2) 打开后台管理"
    echo "  3) 查看服务器日志"
    echo "  4) 停止服务器"
    echo "  0) 退出"
    echo ""
    read -p "请输入选项 [0-4]: " choice
    
    case $choice in
        1)
            echo "🌐 正在打开前台首页..."
            open http://localhost:8080
            ;;
        2)
            echo "🔐 正在打开后台管理..."
            open http://localhost:8080/admin/
            ;;
        3)
            echo "📋 服务器日志（按 Ctrl+C 退出）："
            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
            tail -f "$PROJECT_ROOT/logs/$(date +%Y-%m-%d).log" 2>/dev/null || echo "暂无日志文件"
            ;;
        4)
            echo "🛑 正在停止服务器..."
            PID=$(lsof -ti:8080)
            if [ ! -z "$PID" ]; then
                kill $PID
                echo "✅ 服务器已停止"
            else
                echo "⚠️  未找到运行中的服务器进程"
            fi
            ;;
        0)
            echo "👋 再见！"
            exit 0
            ;;
        *)
            echo "❌ 无效的选项"
            ;;
    esac
else
    echo "❌ 服务器状态: 未运行"
    echo ""
    echo "请选择操作："
    echo "  1) 启动服务器"
    echo "  0) 退出"
    echo ""
    read -p "请输入选项 [0-1]: " choice
    
    case $choice in
        1)
            echo "🚀 正在启动服务器..."
            "$PROJECT_ROOT/start.sh"
            ;;
        0)
            echo "👋 再见！"
            exit 0
            ;;
        *)
            echo "❌ 无效的选项"
            ;;
    esac
fi
