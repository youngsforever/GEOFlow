#!/bin/bash

# 文件整理脚本 - 整理备份文件和说明文档
# 创建时间: 2026-02-03

set -e  # 遇到错误立即退出

echo "========================================="
echo "  GEO网站系统 - 文件整理脚本"
echo "========================================="
echo ""

# 获取脚本所在目录
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "当前目录: $SCRIPT_DIR"
echo ""

# 创建新的文件夹结构
echo "📁 创建文件夹结构..."
mkdir -p docs
mkdir -p _backups/admin
mkdir -p _archived/backup_old
mkdir -p _archived/tests

echo "✅ 文件夹创建完成"
echo ""

# 移动说明文档到 docs/
echo "📄 移动说明文档到 docs/ ..."

# 定义要移动的文档列表
docs_to_move=(
    "AI_PROJECT_GUIDE.md"
    "SENSITIVE_WORDS_FLOW_ANALYSIS.md"
    "SESSION_FIX_REPORT.md"
    "TASK_23_ANALYSIS_REPORT.md"
    "任务管理系统分析报告.md"
    "安全设置页面修复说明.md"
    "快速参考.md"
    "本地环境访问指南.md"
    "本地环境配置指南.md"
    "测试文件归档说明.md"
    "登录问题修复说明.md"
    "系统状态概览.md"
    "系统说明文档.md"
    "阶段1修复指南.md"
)

moved_docs=0
for doc in "${docs_to_move[@]}"; do
    if [ -f "$doc" ]; then
        mv "$doc" "docs/"
        echo "  ✓ $doc"
        ((moved_docs++))
    fi
done

echo "✅ 已移动 $moved_docs 个文档文件"
echo ""

# 移动 admin 目录下的 .bak 文件
echo "💾 移动备份文件到 _backups/admin/ ..."

moved_bak=0
if [ -d "admin" ]; then
    for bak_file in admin/*.bak; do
        if [ -f "$bak_file" ]; then
            mv "$bak_file" "_backups/admin/"
            filename=$(basename "$bak_file")
            echo "  ✓ $filename"
            ((moved_bak++))
        fi
    done
fi

echo "✅ 已移动 $moved_bak 个备份文件"
echo ""

# 移动旧的备份文件夹
echo "📦 整理旧的归档文件夹..."

if [ -d "备份" ]; then
    # 移动备份文件夹中的内容到 _archived/backup_old/
    if [ "$(ls -A 备份)" ]; then
        cp -r 备份/* _archived/backup_old/
        echo "  ✓ 备份/ → _archived/backup_old/"
    fi
    # 删除旧的备份文件夹
    rm -rf 备份
fi

if [ -d "_archived_tests" ]; then
    # 移动测试文件夹中的内容到 _archived/tests/
    if [ "$(ls -A _archived_tests)" ]; then
        cp -r _archived_tests/* _archived/tests/
        echo "  ✓ _archived_tests/ → _archived/tests/"
    fi
    # 删除旧的测试文件夹
    rm -rf _archived_tests
fi

echo "✅ 归档文件夹整理完成"
echo ""

# 创建 README.md 保留在根目录
echo "📝 创建根目录 README.md ..."

cat > README.md << 'EOF'
# GEO网站系统

一个基于PHP的AI驱动内容生成平台。

## 📚 文档

所有说明文档已移动到 `docs/` 目录：

- [系统说明文档](docs/系统说明文档.md) - 系统整体介绍
- [快速参考](docs/快速参考.md) - 常用命令和操作
- [本地环境配置指南](docs/本地环境配置指南.md) - 环境配置说明
- [本地环境访问指南](docs/本地环境访问指南.md) - 访问地址和登录信息
- [AI项目指南](docs/AI_PROJECT_GUIDE.md) - AI功能使用指南

## 🚀 快速启动

```bash
# 启动服务器
./start-server.sh

# 访问系统
open https://localhost:8080
```

## 📂 目录结构

```
├── admin/          # 后台管理
├── includes/       # 核心功能
├── assets/         # 静态资源
├── data/           # 数据库和数据文件
├── logs/           # 日志文件
├── docs/           # 📚 说明文档
├── _backups/       # 💾 备份文件
└── _archived/      # 📦 归档文件
```

## 🔧 技术栈

- **后端**: PHP 8.4.14
- **数据库**: SQLite (WAL模式)
- **Web服务器**: Caddy v2.10.2 + PHP-FPM
- **前端**: Tailwind CSS, Lucide Icons

## 📞 支持

查看 `docs/` 目录中的详细文档。
EOF

echo "✅ README.md 创建完成"
echo ""

# 创建 docs/README.md 索引
echo "📝 创建 docs/README.md 索引..."

cat > docs/README.md << 'EOF'
# GEO网站系统 - 文档中心

## 📖 系统文档

### 基础文档
- [系统说明文档](系统说明文档.md) - 系统整体介绍和功能说明
- [快速参考](快速参考.md) - 常用命令和操作速查
- [AI项目指南](AI_PROJECT_GUIDE.md) - AI功能详细使用指南

### 环境配置
- [本地环境配置指南](本地环境配置指南.md) - 开发环境配置步骤
- [本地环境访问指南](本地环境访问指南.md) - 访问地址和登录信息

### 问题修复记录
- [登录问题修复说明](登录问题修复说明.md) - 登录相关问题的修复记录
- [安全设置页面修复说明](安全设置页面修复说明.md) - 安全设置页面的修复记录
- [SESSION修复报告](SESSION_FIX_REPORT.md) - Session锁定问题的修复报告
- [阶段1修复指南](阶段1修复指南.md) - 系统初期修复指南

### 功能分析
- [任务管理系统分析报告](任务管理系统分析报告.md) - 任务管理功能的详细分析
- [TASK 23分析报告](TASK_23_ANALYSIS_REPORT.md) - 特定任务的问题分析
- [敏感词流程分析](SENSITIVE_WORDS_FLOW_ANALYSIS.md) - 敏感词检测机制分析

### 归档说明
- [测试文件归档说明](测试文件归档说明.md) - 测试文件的归档说明
- [系统状态概览](系统状态概览.md) - 系统当前状态概览

## 📂 其他资源

- **备份文件**: 查看 `../_backups/` 目录
- **归档文件**: 查看 `../_archived/` 目录
EOF

echo "✅ docs/README.md 创建完成"
echo ""

# 统计信息
echo "========================================="
echo "  整理完成！"
echo "========================================="
echo ""
echo "📊 统计信息："
echo "  - 文档文件: $moved_docs 个 → docs/"
echo "  - 备份文件: $moved_bak 个 → _backups/admin/"
echo "  - 归档文件夹: 2 个 → _archived/"
echo ""
echo "📁 新的文件夹结构："
echo "  ├── docs/           # 所有说明文档"
echo "  ├── _backups/       # 所有备份文件"
echo "  └── _archived/      # 已归档的旧文件"
echo ""
echo "✅ 文件整理完成！系统文件现在更加整洁了。"
echo ""

