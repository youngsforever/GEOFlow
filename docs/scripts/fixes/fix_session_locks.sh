#!/bin/bash
# 批量修复admin页面的session锁问题
# 在require_admin_login()后添加session_write_close()

cd "$(dirname "$0")/admin"

# 需要修复的文件列表
files=(
    "ai-config-backup.php"
    "ai-config-new.php"
    "ai-config-simple.php"
    "ai-configurator.php"
    "ai-models.php"
    "ai-prompts.php"
    "ai-special-prompts.php"
    "article-create.php"
    "article-edit.php"
    "article-view.php"
    "articles-new.php"
    "articles-review.php"
    "articles-trash.php"
    "authors-new.php"
    "categories.php"
    "dashboard-backup.php"
    "dashboard-simple.php"
    "dashboard.php"
    "execute_all_tasks.php"
    "execute_task.php"
    "get_task_status.php"
    "image-libraries.php"
    "image-library-detail.php"
    "index.php"
    "keyword-libraries.php"
    "keyword-library-detail.php"
    "knowledge-base-detail.php"
    "knowledge-bases.php"
    "materials-new.php"
    "security-settings.php"
    "simple-test.php"
    "site-settings.php"
    "system_diagnostics.php"
    "task-create.php"
    "task-execute.php"
    "task_health_check.php"
    "tasks-new.php"
    "tasks-safe.php"
    "test-admin.php"
    "test-dashboard.php"
    "test-fixes.php"
    "test-navigation.php"
    "title-libraries.php"
    "title-library-ai-generate.php"
    "title-library-detail.php"
    "title_generate_async.php"
)

# 特殊处理的文件（不需要修复或需要特殊处理）
skip_files=(
    "login-new.php"
    "login-simple.php"
    "logout.php"
)

echo "开始批量修复session锁问题..."
echo "总共需要检查 ${#files[@]} 个文件"
echo ""

fixed_count=0
skipped_count=0
error_count=0

for file in "${files[@]}"; do
    if [[ ! -f "$file" ]]; then
        echo "⚠️  跳过: $file (文件不存在)"
        ((skipped_count++))
        continue
    fi
    
    # 检查是否已经有session_write_close
    if grep -q "session_write_close()" "$file"; then
        echo "✓  跳过: $file (已经有session_write_close)"
        ((skipped_count++))
        continue
    fi
    
    # 检查是否有require_admin_login
    if ! grep -q "require_admin_login()" "$file"; then
        echo "⚠️  跳过: $file (没有require_admin_login)"
        ((skipped_count++))
        continue
    fi
    
    # 使用sed在require_admin_login()后添加session_write_close()
    # 查找包含require_admin_login()的行号
    line_num=$(grep -n "require_admin_login()" "$file" | head -1 | cut -d: -f1)
    
    if [[ -z "$line_num" ]]; then
        echo "❌ 错误: $file (无法找到require_admin_login行号)"
        ((error_count++))
        continue
    fi
    
    # 创建备份
    cp "$file" "$file.bak"
    
    # 在require_admin_login()后插入session_write_close()
    # 使用awk更精确地处理
    awk -v line="$line_num" '
        NR == line {
            print $0
            print ""
            print "// 立即释放session锁，允许其他页面并发访问"
            print "session_write_close();"
            next
        }
        { print }
    ' "$file.bak" > "$file"
    
    echo "✅ 修复: $file (在第 $line_num 行后添加session_write_close)"
    ((fixed_count++))
done

echo ""
echo "========================================="
echo "修复完成！"
echo "✅ 成功修复: $fixed_count 个文件"
echo "⚠️  跳过: $skipped_count 个文件"
echo "❌ 错误: $error_count 个文件"
echo "========================================="
echo ""
echo "备份文件已保存为 *.bak"
echo "如果需要回滚，请运行: rm admin/*.php && rename 's/\.bak$//' admin/*.bak"

