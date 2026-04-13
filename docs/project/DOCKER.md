# Docker 运行说明

## 系统梳理

当前项目不是前后端分离架构，而是一套 PHP 单体系统：

- 前台站点入口：`index.php`、`article.php`、`category.php`、`archive.php`、`detail.php`
- 路由入口：`router.php`
- 后台管理：`admin/`
- 核心逻辑与数据访问：`includes/`
- 运行脚本：`bin/`
- 持久化数据：`data/`、`logs/`、`uploads/`

前台页面和后台页面都由 PHP 直接渲染。任务调度与批量执行也在同一项目内完成，因此 Docker 化时不需要单独拆前端服务。

## Docker 方案

本项目提供两类容器：

- `web`：对外提供 HTTP 服务，容器内监听 `8080`
- `scheduler`：循环执行 `bin/cron.php`，用于任务调度

当前采用双文件结构：

- `docker-compose.yml`
  - 默认用于本地开发
  - 挂载整个项目目录到容器内
  - 改代码可实时生效
- `docker-compose.prod.yml`
  - 用于生产部署
  - 独立运行，不与开发文件叠加
  - 不挂载源码
  - 只持久化 `data/`、`uploads/`、`logs/`
  - 强制要求 `APP_SECRET_KEY`

默认本机映射端口：

- `18080 -> 8080`

默认 Compose 项目名：

- `geo-system-docker`

## 开发环境

```bash
docker compose up -d --build
```

访问地址：

- 前台首页：`http://localhost:18080`
- 管理后台：`http://localhost:18080/geo_admin/`

停止：

```bash
docker compose down
```

如需启用任务调度器：

```bash
docker compose --profile scheduler up -d scheduler
```

查看日志：

```bash
docker compose logs -f web
docker compose logs -f scheduler
```

手动执行调度器：

```bash
docker compose exec web php bin/cron.php
```

## 生产环境

1. 准备环境变量文件：

```bash
cp .env.example .env.prod
```

至少修改：

- `SITE_URL`
- `APP_SECRET_KEY`
- `REQUIRE_STRONG_APP_SECRET=true`

2. 启动生产容器：

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build
```

3. 停止生产容器：

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod down
```

## 持久化

开发环境：

- 通过宿主机源码目录挂载直接持久化 `data/`、`logs/`、`uploads/`

生产环境：

- `geo_data` -> `/var/www/html/data`
- `geo_uploads` -> `/var/www/html/uploads`
- `geo_logs` -> `/var/www/html/logs`

数据库备份目录位于：

- `data/backups/`

## 环境变量

当前 Compose 已预设：

- `SITE_URL=http://localhost:18080`
- `APP_PORT=8080`
- `TZ=Asia/Shanghai`
- `APP_SECRET_KEY` 由 `.env` 或 `--env-file` 提供
- `REQUIRE_STRONG_APP_SECRET`
  - 开发环境默认 `false`
  - 生产环境强制为 `true`
- 生产环境下 `scheduler` 默认启动

如需改外部端口，可在启动前覆盖：

```bash
HOST_PORT=18081 docker compose up -d --build
```
