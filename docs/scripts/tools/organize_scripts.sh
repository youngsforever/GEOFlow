#!/bin/bash

# 脚本文件整理 - 将不常用脚本移至 docs/scripts/
# 将备份文件夹统一移至 docs/
# 创建时间: 2026-02-03

set -e  # 遇到错误立即退出

echo "========================================="
echo "  GEO网站系统 - 脚本文件整理"
echo "========================================="
echo ""

# 获取脚本所在目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../../.." && pwd)"
cd "$PROJECT_ROOT"

echo "当前目录: $PROJECT_ROOT"
echo ""

# 创建 docs/scripts/ 目录结构
echo "📁 创建 docs/scripts/ 目录结构..."
mkdir -p docs/scripts/server
mkdir -p docs/scripts/maintenance
mkdir -p docs/scripts/fixes
mkdir -p docs/scripts/tools
mkdir -p docs/scripts/utils

echo "✅ 目录创建完成"
echo ""

# 移动服务器相关脚本
echo "🖥️  移动服务器相关脚本到 docs/scripts/server/ ..."

server_scripts=(
    "start_server.sh"
    "start-ai-system.sh"
    "monitor_server.sh"
)

moved_server=0
for script in "${server_scripts[@]}"; do
    if [ -f "$script" ]; then
        mv "$script" "docs/scripts/server/"
        echo "  ✓ $script"
        ((moved_server++))
    fi
done

echo "✅ 已移动 $moved_server 个服务器脚本"
echo ""

# 移动维护工具脚本
echo "🔧 移动维护工具脚本到 docs/scripts/maintenance/ ..."

maintenance_scripts=(
    "cleanup_backups.sh"
    "create_backup.sh"
    "restore_file.sh"
    "setup-cron.sh"
)

moved_maintenance=0
for script in "${maintenance_scripts[@]}"; do
    if [ -f "$script" ]; then
        mv "$script" "docs/scripts/maintenance/"
        echo "  ✓ $script"
        ((moved_maintenance++))
    fi
done

echo "✅ 已移动 $moved_maintenance 个维护脚本"
echo ""

# 移动历史修复脚本
echo "🔨 移动历史修复脚本到 docs/scripts/fixes/ ..."

fixes_scripts=(
    "apply_stage1_fixes.sh"
    "fix_session_locks.sh"
    "move_test_files.sh"
)

moved_fixes=0
for script in "${fixes_scripts[@]}"; do
    if [ -f "$script" ]; then
        mv "$script" "docs/scripts/fixes/"
        echo "  ✓ $script"
        ((moved_fixes++))
    fi
done

echo "✅ 已移动 $moved_fixes 个修复脚本"
echo ""

# 移动工具脚本
echo "🛠️  移动工具脚本到 docs/scripts/tools/ ..."

tools_scripts=(
    "organize_files.sh"
)

moved_tools=0
for script in "${tools_scripts[@]}"; do
    if [ -f "$script" ]; then
        mv "$script" "docs/scripts/tools/"
        echo "  ✓ $script"
        ((moved_tools++))
    fi
done

echo "✅ 已移动 $moved_tools 个工具脚本"
echo ""

# 移动实用工具脚本
echo "⚙️  移动实用工具脚本到 docs/scripts/utils/ ..."

utils_scripts=(
    "check-env.sh"
)

moved_utils=0
for script in "${utils_scripts[@]}"; do
    if [ -f "$script" ]; then
        mv "$script" "docs/scripts/utils/"
        echo "  ✓ $script"
        ((moved_utils++))
    fi
done

echo "✅ 已移动 $moved_utils 个实用工具脚本"
echo ""

# 移动备份文件夹到 docs/
echo "📦 移动备份文件夹到 docs/ ..."

if [ -d "_backups" ]; then
    mv "_backups" "docs/backups"
    echo "  ✓ _backups/ → docs/backups/"
fi

if [ -d "_archived" ]; then
    mv "_archived" "docs/archived"
    echo "  ✓ _archived/ → docs/archived/"
fi

echo "✅ 备份文件夹移动完成"
echo ""

# 创建 docs/scripts/README.md
echo "📝 创建 docs/scripts/README.md ..."

cat > docs/scripts/README.md << 'EOF'
# 脚本文件目录

本目录存放系统的各类脚本文件，按功能分类管理。

## 📂 目录结构

```
scripts/
├── server/              # 服务器相关脚本
├── maintenance/         # 维护工具脚本
├── fixes/               # 历史修复脚本
├── tools/               # 工具脚本
└── utils/               # 实用工具脚本
```

## 🖥️ server/ - 服务器相关

| 脚本名称 | 作用 | 使用说明 |
|---------|------|---------|
| `start_server.sh` | 旧版服务器启动脚本 | 已被 `../../start-server.sh` 替代 |
| `start-ai-system.sh` | AI系统启动脚本 | 启动AI内容生成系统 |
| `monitor_server.sh` | 服务器监控脚本 | 监控服务器状态并自动重启 |

## 🔧 maintenance/ - 维护工具

| 脚本名称 | 作用 | 使用说明 |
|---------|------|---------|
| `cleanup_backups.sh` | 清理备份文件 | 清理session修复产生的备份 |
| `create_backup.sh` | 创建备份 | 创建修复前的备份 |
| `restore_file.sh` | 恢复文件 | 恢复被删除的文件 |
| `setup-cron.sh` | Cron任务设置 | 设置定时任务 |

## 🔨 fixes/ - 历史修复脚本

| 脚本名称 | 作用 | 状态 |
|---------|------|------|
| `apply_stage1_fixes.sh` | 阶段1修复脚本 | ✅ 已完成 |
| `fix_session_locks.sh` | Session锁修复 | ✅ 已完成 |
| `move_test_files.sh` | 移动测试文件 | ✅ 已完成 |

**说明**：这些脚本已经执行完成，保留作为历史记录。

## 🛠️ tools/ - 工具脚本

| 脚本名称 | 作用 | 使用说明 |
|---------|------|---------|
| `organize_files.sh` | 文件整理脚本 | 整理文档和备份文件 |

## ⚙️ utils/ - 实用工具

| 脚本名称 | 作用 | 使用说明 |
|---------|------|---------|
| `check-env.sh` | 环境检查脚本 | 检查系统环境配置 |

## 📌 当前根目录保留脚本

以下脚本继续保留在根目录，作为本地运行入口：

```bash
../../start-server.sh      # 启动服务器
../../stop-server.sh       # 停止服务器
../../start.sh             # 新版启动脚本
```

其余辅助脚本已迁移到 docs/scripts/ 对应分类目录。

## 🔍 使用示例

```bash
# 从根目录执行脚本
cd /path/to/GEO网站系统

# 执行服务器监控
./docs/scripts/server/monitor_server.sh

# 执行环境检查
./docs/scripts/utils/check-env.sh

# 创建备份
./docs/scripts/maintenance/create_backup.sh
```
EOF

echo "✅ docs/scripts/README.md 创建完成"
echo ""

# 统计信息
total_moved=$((moved_server + moved_maintenance + moved_fixes + moved_tools + moved_utils))

echo "========================================="
echo "  整理完成！"
echo "========================================="
echo ""
echo "📊 统计信息："
echo "  - 服务器脚本: $moved_server 个 → docs/scripts/server/"
echo "  - 维护脚本: $moved_maintenance 个 → docs/scripts/maintenance/"
echo "  - 修复脚本: $moved_fixes 个 → docs/scripts/fixes/"
echo "  - 工具脚本: $moved_tools 个 → docs/scripts/tools/"
echo "  - 实用工具: $moved_utils 个 → docs/scripts/utils/"
echo "  - 总计移动: $total_moved 个脚本"
echo ""
echo "📦 备份文件夹："
echo "  - _backups/ → docs/backups/"
echo "  - _archived/ → docs/archived/"
echo ""
echo "📁 根目录保留的常用脚本："
echo "  ✓ start-server.sh"
echo "  ✓ stop-server.sh"
echo "  ✓ start.sh"
echo "  ✓ 快速访问.sh"
echo "  ✓ open.sh"
echo "  ✓ check_task_status.sh"
echo ""
echo "✅ 脚本整理完成！现在所有文档、脚本、备份都统一在 docs/ 目录下了。"
echo ""
