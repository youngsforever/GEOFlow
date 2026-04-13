#!/bin/bash
# GEO+AI 系统 - 快速打开浏览器脚本
# 
# @author AI Assistant
# @version 1.0
# @date 2026-02-02

# 颜色定义
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

clear
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  🌐 GEO+AI 系统快速访问"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "请选择要打开的页面："
echo ""
echo "  1) 前台首页"
echo "  2) 管理后台"
echo "  3) 系统诊断"
echo "  4) 文章管理"
echo "  5) 任务管理"
echo "  6) AI配置中心"
echo "  7) 素材管理"
echo "  0) 全部打开"
echo ""
echo -n "请输入选项 [1-7, 0]: "
read choice

case $choice in
    1)
        echo -e "${GREEN}✅ 正在打开前台首页...${NC}"
        open "http://localhost:8080"
        ;;
    2)
        echo -e "${GREEN}✅ 正在打开管理后台...${NC}"
        open "http://localhost:8080/admin/"
        ;;
    3)
        echo -e "${GREEN}✅ 正在打开系统诊断...${NC}"
        open "http://localhost:8080/admin/system_diagnostics.php"
        ;;
    4)
        echo -e "${GREEN}✅ 正在打开文章管理...${NC}"
        open "http://localhost:8080/admin/articles-new.php"
        ;;
    5)
        echo -e "${GREEN}✅ 正在打开任务管理...${NC}"
        open "http://localhost:8080/admin/tasks-new.php"
        ;;
    6)
        echo -e "${GREEN}✅ 正在打开AI配置中心...${NC}"
        open "http://localhost:8080/admin/ai-configurator.php"
        ;;
    7)
        echo -e "${GREEN}✅ 正在打开素材管理...${NC}"
        open "http://localhost:8080/admin/materials-new.php"
        ;;
    0)
        echo -e "${GREEN}✅ 正在打开所有页面...${NC}"
        open "http://localhost:8080"
        sleep 1
        open "http://localhost:8080/admin/"
        sleep 1
        open "http://localhost:8080/admin/system_diagnostics.php"
        ;;
    *)
        echo "无效的选项"
        exit 1
        ;;
esac

echo ""
echo -e "${BLUE}💡 提示：${NC}如果浏览器未自动打开，请手动访问上述地址"
echo ""

