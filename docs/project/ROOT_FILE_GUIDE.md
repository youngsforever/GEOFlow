# 根目录文件梳理

本文档基于当前项目代码结构，对当前项目根目录保留内容做快速说明，并标记哪些文件已迁移到 `bin/` 或 `docs/`。

## 当前系统理解

这是一个以原生 PHP 为主的 GEO + AI 内容系统：

- 前台页面入口保留在根目录，如 `index.php`、`article.php`、`category.php`。
- `router.php` 是当前本地开发环境下的主路由器。
- `includes/` 存放配置、数据库封装、公共函数和任务管理逻辑。
- `admin/` 是后台管理系统。
- `data/`、`logs/`、`uploads/` 是运行期数据目录。
- `bin/` 存放任务调度、批量执行和健康检查这类 CLI 运行脚本。

## 根目录文件逐项说明

### 一、运行入口和页面文件

| 文件/目录 | 说明 | 处理 |
|---|---|---|
| `admin/` | 后台管理系统目录 | 保留根目录 |
| `archive.php` | 归档页入口 | 保留根目录 |
| `article.php` | 当前正式文章详情页，兼容旧的 `?id=` 链接 | 保留根目录 |
| `assets/` | 静态资源目录 | 保留根目录 |
| `bin/` | CLI 运行脚本目录 | 保留根目录 |
| `category.php` | 当前正式分类页 | 保留根目录 |
| `data/` | SQLite 数据与运行数据目录 | 保留根目录 |
| `detail/` | 详情页相关目录 | 保留根目录 |
| `detail.php` | 历史详情页兼容入口 | 保留根目录 |
| `includes/` | 配置、数据库、函数与核心逻辑 | 保留根目录 |
| `index.php` | 当前正式首页入口 | 保留根目录 |
| `logs/` | 日志目录 | 保留根目录 |
| `router.php` | PHP 内置服务器路由器 | 保留根目录 |
| `uploads/` | 上传目录 | 保留根目录 |

### 二、bin/ 运行脚本

| 文件 | 说明 | 处理 |
|---|---|---|
| `bin/cron.php` | 当前正式定时任务入口 | 保留 |
| `bin/batch_execute_task.php` | 批量任务后台执行主脚本 | 保留 |
| `bin/health_check_cron.php` | 任务健康检查定时脚本 | 保留 |

### 三、根目录保留的本地运行脚本

| 文件 | 说明 | 处理 |
|---|---|---|
| `start.sh` | 当前主启动脚本 | 保留根目录 |

说明：当前根目录只保留 `start.sh` 作为本地运行入口，旧的启动/停止脚本已归档到 `docs/scripts/server/`。

### 四、已归档到 docs/ 的文件

| 原根目录文件 | 新位置 | 分类原因 |
|---|---|---|
| `DEPLOYMENT.md` | `docs/deployment/DEPLOYMENT.md` | 部署说明文档 |
| `Caddyfile` | `docs/deployment/Caddyfile` | 部署配置样例 |
| `organize_scripts.sh` | `docs/scripts/tools/organize_scripts.sh` | 一次性整理工具 |
| `check_task_status.sh` | `docs/scripts/utils/check_task_status.sh` | 辅助查询脚本 |
| `open.sh` | `docs/scripts/tools/open.sh` | 快捷访问脚本 |
| `快速访问.sh` | `docs/scripts/tools/快速访问.sh` | 快捷访问脚本 |
| `check_status.php` | `docs/maintenance/php/check_status.php` | 运维诊断脚本 |
| `init-db.php` | `docs/maintenance/php/init-db.php` | 初始化维护脚本 |
| `update-password.php` | `docs/maintenance/php/update-password.php` | 管理员维护脚本 |
| `security_check.php` | `docs/maintenance/php/security_check.php` | 安全检查脚本 |
| `test_wal.php` | `docs/diagnostics/test_wal.php` | WAL 测试脚本 |
| `server.pid` | `docs/runtime/server.pid` | 运行时临时文件 |
| `about.php` | 已删除 | 不再需要的前端页面 |
| `tag.php` | 已删除 | 不再需要的前端页面 |
| `cron-new.php` | 已更名为 `cron.php` | 原主调度器文件名 |
| `cron-legacy.php` | `docs/archived/scripts/cron-legacy.php` | 旧版调度器 |
| `index-new.php` | 已并入 `index.php` | 原新版首页 |
| `article-new.php` | 已并入 `article.php` | 原新版文章详情页 |
| `category-new.php` | 已更名为 `category.php` | 原新版分类页 |
| `index-simple.php` | `docs/archived/pages/index-simple.php` | 调试页/极简页 |
| `minimal-index.php` | `docs/archived/pages/minimal-index.php` | 演示页 |
| `index-smartbi.php` | `docs/archived/pages/index-smartbi.php` | SmartBI 原型页 |
| `detail-smartbi.php` | `docs/archived/pages/detail-smartbi.php` | SmartBI 原型详情页 |
| `submit.php` | `docs/archived/pages/submit.php` | 历史提交页 |
| `simple_router.php` | `docs/archived/routes/simple_router.php` | 备用简化路由器 |
| `start-server.sh` | `docs/scripts/server/start-server.sh` | 旧版启动脚本 |
| `stop-server.sh` | `docs/scripts/server/stop-server.sh` | 旧版停止脚本 |

### 四、目录说明

| 目录 | 说明 | 处理 |
|---|---|---|
| `data/backups/` | 运行期数据库备份目录 | 保留 |
| `docs/` | 文档、脚本归档、诊断与维护文件集中目录 | 已扩展分类 |

## 本次整理原则

- 不移动前台/后台实际入口文件。
- 不移动 `includes/`、`admin/`、`data/`、`logs/`、`uploads/` 这类运行期目录。
- 将 CLI 运行脚本统一收口到 `bin/`。
- 将旧版页面、原型页、备用路由器、旧脚本、诊断脚本、部署文档和临时状态文件迁入 `docs/`。
- 已迁移脚本补齐了项目根目录定位逻辑，避免因为相对路径变化而失效。

## 当前根目录结论

当前根目录已经收敛为：

- 前台入口：`index.php`、`article.php`、`category.php`、`archive.php`
- 本地启动：`start.sh`
- 本地路由：`router.php`
- 兼容入口：`detail.php`
- 核心目录：`admin/`、`includes/`、`assets/`、`data/`、`logs/`、`uploads/`
- CLI 脚本目录：`bin/`
