#!/bin/bash
# 移动测试文件到归档文件夹
# 作者: AI Assistant
# 日期: 2026-02-02

echo "=== 开始移动测试文件到归档文件夹 ==="

# 创建归档文件夹
ARCHIVE_DIR="_archived_tests"
if [ ! -d "$ARCHIVE_DIR" ]; then
    mkdir -p "$ARCHIVE_DIR"
    echo "✅ 创建归档文件夹: $ARCHIVE_DIR"
else
    echo "ℹ️  归档文件夹已存在: $ARCHIVE_DIR"
fi

# 要移动的文件列表
files=(
    "basic-test.php"
    "simple-test.php"
    "db-test.php"
    "simple-db-test.php"
    "func-test.php"
    "step-test.php"
    "step-test2.php"
    "quick-test.php"
    "test.php"
    "debug.php"
    "debug_check.php"
    "debug_image_upload.php"
    "debug_prompts.php"
    "deep-debug.php"
    "precise-debug.php"
    "env-check.php"
    "test-content-format.php"
    "test-fix.php"
    "test-frontend.php"
    "test-login.php"
    "test-security-settings.php"
    "test_ai_system.php"
    "emergency-fix.php"
    "emergency-switch.php"
    "temp-fix.php"
    "test_upload.php"
    "create_test_image.php"
    "simple-password-update.php"
    "test-image.jpg"
    "cookies.txt"
    "server.log"
    "server_monitor.log"
    "quick-test.sh"
)

# 统计
moved_count=0
not_found_count=0
error_count=0

# 移动文件
for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        if mv "$file" "$ARCHIVE_DIR/"; then
            echo "✅ 已移动: $file"
            ((moved_count++))
        else
            echo "❌ 移动失败: $file"
            ((error_count++))
        fi
    else
        echo "⚠️  文件不存在: $file"
        ((not_found_count++))
    fi
done

# 创建归档说明文件
cat > "$ARCHIVE_DIR/README.md" << 'EOF'
# 测试文件归档

本文件夹包含了GEO网站系统开发过程中使用的测试和调试文件。

## 归档时间
2026-02-02

## 文件分类

### 基础测试文件
- basic-test.php - 最基本的PHP功能测试
- simple-test.php - 简单的phpinfo测试
- db-test.php - 数据库连接测试
- simple-db-test.php - 简单数据库测试
- func-test.php - functions.php加载测试
- step-test.php - 分步加载测试
- step-test2.php - 分步加载测试2
- quick-test.php - 快速测试
- test.php - 系统综合测试

### 调试文件
- debug.php - 系统调试信息
- debug_check.php - 调试检查
- debug_image_upload.php - 图片上传调试
- debug_prompts.php - 提示词调试
- deep-debug.php - 深度调试
- precise-debug.php - 精确调试
- env-check.php - 环境诊断

### 功能测试文件
- test-content-format.php - 内容格式化测试
- test-fix.php - 修复测试
- test-frontend.php - 前台页面测试
- test-login.php - 登录测试
- test-security-settings.php - 安全设置测试
- test_ai_system.php - AI系统测试

### 临时修复文件
- emergency-fix.php - 紧急修复脚本
- emergency-switch.php - 紧急切换脚本
- temp-fix.php - 临时修复脚本

### 工具文件
- test_upload.php - 上传测试
- create_test_image.php - 创建测试图片
- simple-password-update.php - 简单密码更新
- test-image.jpg - 测试图片文件

### 临时/日志文件
- cookies.txt - Cookie临时文件
- server.log - 服务器日志
- server_monitor.log - 服务器监控日志
- quick-test.sh - 快速测试脚本

## 说明
这些文件都是开发和调试过程中使用的临时文件，系统已经稳定运行后可以安全归档。
如果需要重新使用这些文件，可以从本文件夹中复制回根目录。

## 注意
不建议删除这些文件，保留作为参考和备份。
EOF

echo ""
echo "=== 移动完成 ==="
echo "✅ 成功移动: $moved_count 个文件"
echo "⚠️  未找到: $not_found_count 个文件"
echo "❌ 移动失败: $error_count 个文件"
echo ""
echo "📝 已创建归档说明文件: $ARCHIVE_DIR/README.md"
echo ""
echo "完成！所有测试文件已移动到 $ARCHIVE_DIR 文件夹"

