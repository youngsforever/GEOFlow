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
| `start_server.sh` | docs 内遗留的旧版服务器启动脚本 | 已被 `../../start.sh` 替代 |
| `start-server.sh` | 旧版本地启动脚本归档版 | 不再保留根目录 |
| `stop-server.sh` | 停止本地服务脚本归档版 | 不再保留根目录 |
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
| `organize_scripts.sh` | 根目录脚本整理脚本 | 将辅助脚本迁移到 docs/scripts/ |
| `open.sh` | 快速打开浏览器页面 | 打开前台和后台常用页面 |

## ⚙️ utils/ - 实用工具

| 脚本名称 | 作用 | 使用说明 |
|---------|------|---------|
| `check-env.sh` | 环境检查脚本 | 检查系统环境配置 |
| `check_task_status.sh` | 查询任务状态 | 查看任务表与批量执行日志 |

## 📌 根目录保留脚本

以下脚本继续保留在根目录，作为项目本地运行入口：

```bash
../../start.sh             # 新版启动脚本
```

以下辅助脚本已归档到 `docs/scripts/`：

```bash
./docs/scripts/server/start-server.sh
./docs/scripts/server/stop-server.sh
./docs/scripts/tools/open.sh
./docs/scripts/tools/快速访问.sh
./docs/scripts/utils/check_task_status.sh
```

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
