#!/bin/bash
# GEO+AI 系统 - 环境检查脚本
# 
# @author AI Assistant
# @version 1.0
# @date 2026-02-02

clear
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  🔍 GEO+AI 系统环境检查"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# 颜色定义
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# 获取脚本所在目录
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
cd "$SCRIPT_DIR"

# 统计变量
PASS_COUNT=0
WARN_COUNT=0
FAIL_COUNT=0

# 检查函数
check_pass() {
    echo -e "${GREEN}✅ $1${NC}"
    ((PASS_COUNT++))
}

check_warn() {
    echo -e "${YELLOW}⚠️  $1${NC}"
    ((WARN_COUNT++))
}

check_fail() {
    echo -e "${RED}❌ $1${NC}"
    ((FAIL_COUNT++))
}

echo "1️⃣  PHP环境检查"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# PHP版本检查
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    PHP_MAJOR=$(php -r "echo PHP_MAJOR_VERSION;")
    PHP_MINOR=$(php -r "echo PHP_MINOR_VERSION;")
    
    if [ "$PHP_MAJOR" -ge 7 ] && [ "$PHP_MINOR" -ge 4 ]; then
        check_pass "PHP版本: $PHP_VERSION (满足要求 >= 7.4)"
    else
        check_fail "PHP版本: $PHP_VERSION (需要 >= 7.4)"
    fi
else
    check_fail "PHP未安装"
fi

# PHP扩展检查
echo ""
echo "2️⃣  PHP扩展检查"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

REQUIRED_EXTS=("pdo" "pdo_sqlite" "json" "mbstring" "session" "curl")
for ext in "${REQUIRED_EXTS[@]}"; do
    if php -m | grep -qi "^$ext$"; then
        check_pass "$ext 扩展已安装"
    else
        if [ "$ext" = "pdo" ] || [ "$ext" = "pdo_sqlite" ]; then
            check_fail "$ext 扩展未安装（必需）"
        else
            check_warn "$ext 扩展未安装（推荐）"
        fi
    fi
done

# 文件系统检查
echo ""
echo "3️⃣  文件系统检查"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# 关键文件检查
REQUIRED_FILES=(
    "index.php"
    "article.php"
    "router.php"
    "includes/config.php"
    "includes/database.php"
    "includes/functions.php"
    "includes/ai_engine.php"
    "admin/dashboard.php"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$file" ]; then
        check_pass "$file 存在"
    else
        check_fail "$file 缺失"
    fi
done

# 目录检查
echo ""
echo "4️⃣  目录权限检查"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

REQUIRED_DIRS=("data/db" "logs" "uploads/images" "uploads/knowledge")
for dir in "${REQUIRED_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        if [ -w "$dir" ]; then
            check_pass "$dir 目录可写"
        else
            check_warn "$dir 目录不可写"
            echo "   修复: chmod -R 755 $dir"
        fi
    else
        check_warn "$dir 目录不存在"
        echo "   修复: mkdir -p $dir"
    fi
done

# 数据库检查
echo ""
echo "5️⃣  数据库检查"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ -f "data/db/blog.db" ]; then
    DB_SIZE=$(du -h "data/db/blog.db" | cut -f1)
    check_pass "数据库文件存在 (大小: $DB_SIZE)"
    
    # 检查数据库是否可读写
    if [ -r "data/db/blog.db" ] && [ -w "data/db/blog.db" ]; then
        check_pass "数据库文件可读写"
    else
        check_fail "数据库文件权限不足"
    fi
else
    check_warn "数据库未初始化"
    echo "   请访问: http://localhost:8080/install.php"
fi

# 配置检查
echo ""
echo "6️⃣  配置检查"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# 检查PHP配置
MEMORY_LIMIT=$(php -r "echo ini_get('memory_limit');")
MAX_EXECUTION_TIME=$(php -r "echo ini_get('max_execution_time');")
UPLOAD_MAX_FILESIZE=$(php -r "echo ini_get('upload_max_filesize');")

echo "   内存限制: $MEMORY_LIMIT"
echo "   最大执行时间: ${MAX_EXECUTION_TIME}秒"
echo "   最大上传文件: $UPLOAD_MAX_FILESIZE"

# 总结
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  📊 检查结果汇总"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo -e "${GREEN}✅ 通过: $PASS_COUNT${NC}"
echo -e "${YELLOW}⚠️  警告: $WARN_COUNT${NC}"
echo -e "${RED}❌ 失败: $FAIL_COUNT${NC}"
echo ""

if [ $FAIL_COUNT -eq 0 ]; then
    echo -e "${GREEN}🎉 环境检查通过！可以启动系统${NC}"
    echo ""
    echo "启动命令: ./start.sh"
else
    echo -e "${RED}⚠️  存在严重问题，请先修复后再启动${NC}"
fi

echo ""

