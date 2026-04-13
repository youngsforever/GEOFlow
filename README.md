# GEO+AI 智能内容生成系统

> 一个基于 PHP 的 **AI 驱动自动化内容生成与发布平台**，专为 SEO/GEO 内容营销场景设计。支持批量调用 AI 模型生成文章、完整后台管理、任务调度与进程监控，开箱即用，无需复杂依赖。

[![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://www.php.net/)
[![PostgreSQL](https://img.shields.io/badge/Database-PostgreSQL-336791)](https://www.postgresql.org/)
[![Docker](https://img.shields.io/badge/Docker-Compose-blue)](https://docs.docker.com/compose/)
[![License](https://img.shields.io/badge/License-MIT-yellow)](LICENSE)

---

## ✨ 核心特性

| 特性 | 说明 |
|------|------|
| 🤖 多模型 AI 生成 | 兼容 OpenAI 接口协议，支持接入多种 AI 服务商 |
| 📦 批量内容生产 | 后台工作进程持续生成文章，支持草稿限制与循环模式 |
| 🗂 完整素材管理 | 标题库、关键词库、图片库、知识库四位一体 |
| 🔍 内置 SEO 优化 | 自动生成 Meta 标签、Open Graph、Schema.org 结构化数据 |
| 📋 内容审核流程 | 草稿 → 审核 → 发布三段式工作流，支持自动发布 |
| ⏰ 任务调度 | 基于 Cron 的定时调度，可配置执行间隔 |
| 🛡 安全防护 | CSRF Token、SQL 预处理、XSS 过滤、bcrypt 密码加密 |
| 🐳 Docker 支持 | 提供开发与生产双配置，一键启动 |
| 🗄 服务端数据库 | 基于 PostgreSQL，支持更稳定的并发读写与容器化部署 |

---

## 🛠 技术栈

```
后端:    PHP 7.4+（无框架，原生开发）
数据库:  PostgreSQL（默认服务名 `postgres`，数据库名 `geo_system`）
前端:    TailwindCSS + 原生 JavaScript + Lucide Icons
编辑器:  EasyMDE（Markdown）+ Tagify（标签）
AI 集成: OpenAI 兼容接口（默认: 兔子 API）
部署:    PHP 内置服务器 / Docker Compose
```

---

## 🚀 快速开始

### 方式一：Docker（推荐）

```bash
# 1. 克隆仓库
git clone https://github.com/yishan-ai/geo-official-website.git
cd geo-official-website

# 2. 复制环境变量文件
cp .env.example .env

# 3. 编辑 .env，设置必要参数（见下方配置说明）
vi .env

# 4. 启动 Web、PostgreSQL、调度器与 Worker
docker compose --profile scheduler up -d --build

# 访问前台
open http://localhost:18080

# 访问后台
open http://localhost:18080/geo_admin/
```

### 方式二：本地 PHP 服务器

**前置要求:** PHP 7.4+，开启 `pdo_pgsql`、`curl` 扩展，并准备本地 PostgreSQL

```bash
# 1. 克隆仓库
git clone https://github.com/yishan-ai/geo-official-website.git
cd geo-official-website

# 2. 配置数据库环境变量
export DB_DRIVER=pgsql
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_NAME=geo_system
export DB_USER=geo_user
export DB_PASSWORD=geo_password

# 3. 启动开发服务器
php -S localhost:8080 router.php

# 访问后台
open http://localhost:8080/geo_admin/
```

### 从旧 SQLite 导入

如果你手里还有旧的 `blog.db`，先启动 PostgreSQL，再执行：

```bash
DB_DRIVER=pgsql php bin/migrate_sqlite_to_pg.php /path/to/blog.db
```

## 📦 开源发布说明

如果你准备把项目作为公开仓库发布，不建议直接把当前开发仓库切公开。

建议先阅读：

- `docs/project/GITHUB_OPEN_SOURCE_RULES.md`
- `docs/project/OPEN_SOURCE_RELEASE_POLICY.md`

其中包含：

- 哪些文件绝对不能上传
- 私有开发仓库与公开仓库的边界
- 每次向 GitHub 公开仓库推送的固定流程

---

## ⚙️ 环境变量配置

复制 `.env.example` 为 `.env` 并按需修改：

```dotenv
# Web 服务对外暴露端口（默认 18080）
HOST_PORT=18080

# 站点访问地址（需与 HOST_PORT 对应）
SITE_URL=http://localhost:18080

# 应用安全密钥（建议使用 32 位以上随机字符串）
APP_SECRET_KEY=replace-with-a-long-random-secret

# Cron 调度间隔（秒，默认 60）
CRON_INTERVAL=60

# 时区
TZ=Asia/Shanghai
```

---

## 📖 使用指南

### 第一步：登录后台

访问 `/geo_admin/`，使用初始管理员账户登录。

> ⚠️ **安全提示**：首次登录后请立即在「系统设置 → 账户安全」修改默认密码。

---

### 第二步：配置 AI 模型

```
后台 → AI 配置中心 → AI 模型管理 → 添加模型
```

填写：
- **模型名称**：自定义别名
- **API 端点**：如 `https://api.openai.com/v1/chat/completions`
- **API 密钥**：对应服务商的 Bearer Token
- **模型 ID**：如 `gpt-4o`、`claude-3-5-sonnet` 等

---

### 第三步：创建素材库

```
后台 → 素材管理
```

| 素材类型 | 用途 |
|----------|------|
| 标题库 | 预设文章标题，AI 依次取用 |
| 关键词库 | SEO 目标关键词 |
| 图片库 | 自动插入文章的配图 |
| 知识库 | 为 AI 提供垂直领域背景知识（支持文件上传） |

---

### 第四步：配置提示词

```
后台 → AI 配置中心 → 提示词管理 → 新建提示词
```

编写指导 AI 输出格式、风格、字数的系统提示词，支持多套提示词切换。

---

### 第五步：创建并启动生成任务

```
后台 → 任务管理 → 新建任务
```

| 配置项 | 说明 |
|--------|------|
| 标题库 | 选择文章标题来源 |
| AI 模型 | 选择使用的 AI 服务 |
| 提示词模板 | 选择生成风格 |
| 图片库 | 可选，自动插图 |
| 草稿限制 | 未发布草稿上限，防止积压 |
| 发布间隔 | 两次生成之间的等待时间（秒） |
| 循环模式 | 标题用尽后是否重新轮询 |
| 需要审核 | 开启后文章先进审核队列 |

配置完成后点击 **「启动批量执行」** 即可开始自动生成。

---

### 第六步：审核与发布

```
后台 → 文章管理 → 文章审核
```

- 支持批量通过 / 拒绝
- 可手动编辑内容后再发布
- 关闭「需要审核」可实现全自动发布

---

## 🏗 项目结构

```
geo-official-website/
├── index.php                  # 前台首页
├── article.php                # 文章详情页
├── archive.php                # 归档页
├── category.php               # 分类页
├── router.php                 # 开发环境 URL 路由
│
├── geo_admin/                 # 后台管理系统
│   ├── dashboard.php          # 仪表盘
│   ├── tasks-new.php          # 任务管理
│   ├── articles-new.php       # 文章管理
│   ├── articles-review.php    # 文章审核
│   ├── ai-configurator.php    # AI 配置中心
│   ├── ai-models.php          # AI 模型管理
│   ├── ai-prompts.php         # 提示词管理
│   ├── materials-new.php      # 素材库入口
│   ├── start_task_batch.php   # 批量执行启动器
│   └── includes/              # 后台公共模板
│
├── includes/                  # 核心系统类库
│   ├── config.php             # 全局配置与常量
│   ├── database.php           # 数据库类（单例）
│   ├── ai_engine.php          # AI 内容生成引擎 ⭐
│   ├── ai_service.php         # AI API 封装
│   ├── task_service.php       # 任务管理服务
│   ├── task_status_manager.php# 进程生命周期管理
│   ├── security.php           # 安全函数
│   └── seo_functions.php      # SEO 优化函数
│
├── bin/
│   ├── batch_execute_task.php # 批量执行工作进程
│   ├── worker.php             # 常驻 Worker
│   └── cron.php               # 定时任务调度器
│
├── assets/                    # 前端静态资源（CSS/JS/图片）
├── data/                      # 运行数据与迁移来源目录
├── logs/                      # 应用日志 & PID 文件
├── uploads/                   # 用户上传文件
│   ├── images/                # 文章图片
│   └── knowledge/             # 知识库文件
│
├── docker/                    # Docker 构建文件
├── docker-compose.yml         # 开发环境 Compose
├── docker-compose.prod.yml    # 生产环境 Compose
├── .env.example               # 环境变量模板
└── docs/                      # 项目文档
```

---

## 🗄 数据库概览

系统运行时使用 **PostgreSQL**，`data/db/blog.db` 仅保留给迁移/应急维护场景。

**核心表分组：**

```
内容表:      articles, categories, tags, article_tags, authors, comments
AI 生成表:   tasks, titles, title_libraries, keywords, keyword_libraries
              images, image_libraries, knowledge_bases, prompts, ai_models
调度表:      task_schedules, article_queue, task_materials
系统表:      settings, admins, sensitive_words, task_status_manager
```

---

## 🔄 内容生成流程

```
管理员配置任务
      ↓
调度器触发 (cron.php / Worker)
      ↓
从标题库取标题 → 调用 AI API → 生成文章内容
      ↓
自动插图（图片库）+ SEO 元数据生成
      ↓
文章保存为草稿（articles 表，status='draft'）
      ↓
[需要审核] → 管理员审核 → 手动发布
[自动发布] → 直接发布到前台
      ↓
前台展示（index.php / article.php）
```

---

## 🐳 Docker 服务说明

| 服务 | 说明 | 默认启动 |
|------|------|----------|
| `web` | PHP 内置服务器，提供 HTTP 访问 | ✅ |
| `scheduler` | Cron 定时调度器 | 需 `--profile scheduler` |
| `worker` | 常驻后台 Worker 进程 | 需 `--profile scheduler` |

```bash
# 仅启动 Web（不含调度）
docker compose up -d

# 启动完整服务（含调度器和 Worker）
docker compose --profile scheduler up -d

# 查看日志
docker compose logs -f web

# 停止所有服务
docker compose down
```

---

## 📊 监控与日志

```
logs/
├── YYYY-MM-DD.log        # 每日应用日志
├── batch_<task_id>.log   # 批量任务执行日志
├── batch_<task_id>.pid   # 进程 PID 文件
└── task_manager_*.log    # 任务状态管理器日志
```

后台仪表盘提供实时统计：文章数、任务状态、AI 模型用量、浏览量趋势等。

---

## 🛡 安全说明

- 所有数据库操作使用 **PDO 预处理语句**，防止 SQL 注入
- 表单提交均验证 **CSRF Token**
- 输出内容经过 **HTMLSpecialChars** 转义，防止 XSS
- 管理员密码使用 **bcrypt** 加密存储
- 支持配置安全响应头（X-Frame-Options、X-Content-Type-Options 等）

> ⚠️ 生产部署前请务必修改 `.env` 中的 `APP_SECRET_KEY`，并更新默认管理员密码。

---

## 📚 文档

详细文档见 [`docs/`](docs/) 目录：

- [系统说明文档](docs/系统说明文档.md) - 完整功能说明
- [AI 开发指南](docs/AI_PROJECT_GUIDE.md) - 核心类与架构说明
- [本地环境配置](docs/本地环境配置指南.md) - 开发环境搭建
- [部署文档](docs/deployment/DEPLOYMENT.md) - 服务器部署步骤

---

## 📌 推送规则

本仓库为个人独立项目，遵循以下规则：

- **分支策略**：直接推送至 `master` 分支，无需 Code Review 流程
- **提交规范**：遵循 [Conventional Commits](https://www.conventionalcommits.org/) 格式，如 `feat:`、`fix:`、`docs:` 等前缀
- **敏感文件**：严禁提交 `.env`、`data/db/*.db`、`logs/`、`uploads/` 等运行时或敏感文件（已在 `.gitignore` 中忽略）
- **Issue**：欢迎通过 [Issues](https://github.com/yishan-ai/geo-official-website/issues) 提交问题反馈或建议，但不保证处理时限

---

## 📄 License

[MIT License](LICENSE)

---

> Built with ❤️ for automated content marketing.
