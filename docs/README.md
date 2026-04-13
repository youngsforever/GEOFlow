# GEO网站系统 - 文档中心

## 📖 系统文档

### 基础文档
- [项目最新说明（2026-03-31）](项目最新说明-2026-03-31.md) - 当前 PostgreSQL 架构下的最新总说明，优先阅读
- [项目结构说明](project/STRUCTURE.md) - 当前正式目录规范与职责划分
- [根目录文件梳理](project/ROOT_FILE_GUIDE.md) - 根目录文件的逐项分类说明
- [Docker 运行说明](project/DOCKER.md) - 容器化运行、端口映射和部署方式
- [系统说明文档](系统说明文档.md) - 系统整体介绍和功能说明
- [快速参考](快速参考.md) - 常用命令和操作速查
- [AI项目指南](AI_PROJECT_GUIDE.md) - AI功能详细使用指南

### 环境配置
- [本地环境配置指南](本地环境配置指南.md) - 开发环境配置步骤
- [本地环境访问指南](本地环境访问指南.md) - 访问地址和登录信息
- [部署文档](deployment/DEPLOYMENT.md) - 服务器部署步骤和上线配置
- [Docker 部署文档](deployment/DOCKER_DEPLOYMENT.md) - 当前 Docker 双配置部署方式

### 问题修复记录
- [登录问题修复说明](登录问题修复说明.md) - 登录相关问题的修复记录
- [安全设置页面修复说明](安全设置页面修复说明.md) - 安全设置页面的修复记录
- [SESSION修复报告](SESSION_FIX_REPORT.md) - Session锁定问题的修复报告
- [阶段1修复指南](阶段1修复指南.md) - 系统初期修复指南

### 功能分析
- [任务管理系统分析报告](任务管理系统分析报告.md) - 任务管理功能的详细分析
- [TASK 23分析报告](TASK_23_ANALYSIS_REPORT.md) - 特定任务的问题分析
- [敏感词流程分析](SENSITIVE_WORDS_FLOW_ANALYSIS.md) - 敏感词检测机制分析

### 整理说明
- [文件整理说明](文件整理说明.md) - 文档和备份文件的整理说明
- [脚本文件分析报告](脚本文件分析报告.md) - 脚本文件的分类和整理说明
- [测试文件归档说明](测试文件归档说明.md) - 测试文件的归档说明
- [系统状态概览](系统状态概览.md) - 系统当前状态概览

## 📂 其他资源

### 部署与运维
- **部署目录**: 查看 [deployment/](deployment/) 目录
- **诊断脚本**: 查看 [diagnostics/](diagnostics/) 目录
- **维护脚本**: 查看 [maintenance/](maintenance/) 目录

### 脚本文件
- **脚本目录**: 查看 [scripts/](scripts/) 目录
- **脚本说明**: 查看 [scripts/README.md](scripts/README.md)

### 备份文件
- **运行备份目录**: 查看项目内 `data/backups/` 目录
- **归档目录**: 查看 [archived/](archived/) 目录

## 📁 docs/ 目录结构

```
docs/
├── README.md                    # 本文档
├── project/                     # 项目结构梳理
│   ├── STRUCTURE.md             # 项目结构说明
│   └── ROOT_FILE_GUIDE.md       # 根目录文件梳理
│
├── deployment/                  # 部署文档与配置
│   ├── DEPLOYMENT.md
│   └── Caddyfile
│
├── diagnostics/                 # 测试/诊断脚本
│   └── test_wal.php
│
├── maintenance/                 # 维护脚本
│   └── php/
│       ├── check_status.php
│       ├── init-db.php
│       ├── security_check.php
│       └── update-password.php
│
├── runtime/                     # 运行时临时文件归档
│   └── server.pid
│
├── 文件整理说明.md              # 整理说明
├── 脚本文件分析报告.md          # 脚本分析
│
├── scripts/                     # 脚本文件
│   ├── README.md               # 脚本说明
│   ├── server/                 # 服务器脚本
│   ├── maintenance/            # 维护工具
│   ├── fixes/                  # 历史修复脚本
│   ├── tools/                  # 工具脚本
│   └── utils/                  # 实用工具
│
├── archived/                    # 归档文件
│   ├── backup_old/             # 旧的备份文件夹
│   ├── pages/                  # 历史页面/原型页面
│   ├── routes/                 # 备用路由器
│   ├── scripts/                # 旧版脚本
│   └── tests/                  # 旧的测试文件
│
└── *.md                         # 各类说明文档
```
