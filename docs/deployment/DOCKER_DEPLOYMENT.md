# Docker 部署文档

更新时间：2026-03-27

## 1. 适用范围

本文档用于当前项目的 Docker 部署方案，适用于：

- 单机部署
- Docker Compose 部署
- PHP 单体应用 + PostgreSQL + 本地调度器场景

当前结构：

- `docker-compose.yml`
  - 开发环境默认配置
- `docker-compose.prod.yml`
  - 生产环境独立配置

## 2. 运行逻辑

服务说明：

- `web`
  - 使用 PHP 8.3 CLI 内置服务器
  - 对外监听 `8080`
  - 对应前台与后台站点
- `scheduler`
  - 循环执行 `bin/cron.php`
  - 用于自动任务调度
  - 生产环境默认启动

生产环境和开发环境的核心差异：

- 开发环境挂载整个项目源码，方便热更新
- 生产环境不挂载源码，只持久化数据目录

## 3. 必要文件

确保存在以下文件：

- [docker-compose.yml](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/docker-compose.yml)
- [docker-compose.prod.yml](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/docker-compose.prod.yml)
- [docker/Dockerfile](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/docker/Dockerfile)
- [docker/entrypoint.sh](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/docker/entrypoint.sh)
- [docker/scheduler.sh](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/docker/scheduler.sh)

## 4. 生产部署步骤

### 4.1 准备环境变量

复制模板：

```bash
cp .env.example .env.prod
```

至少修改以下变量：

```env
SITE_URL=https://your-domain.com
APP_SECRET_KEY=请替换成高强度随机字符串
CRON_INTERVAL=60
TZ=Asia/Shanghai
REQUIRE_STRONG_APP_SECRET=true
```

说明：

- `APP_SECRET_KEY` 必须长期稳定保存
- 如果你更换了它，数据库里现有加密的 AI Key 会失效，除非同步做重加密迁移

### 4.2 启动服务

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build
```

### 4.3 查看状态

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod ps
```

### 4.4 查看日志

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod logs -f web
docker compose -f docker-compose.prod.yml --env-file .env.prod logs -f scheduler
```

## 5. 数据持久化

生产环境使用命名卷：

- `geo_data`
- `geo_uploads`
- `geo_logs`

对应容器内目录：

- `/var/www/html/data`
- `/var/www/html/uploads`
- `/var/www/html/logs`

其中关键数据：

- PostgreSQL 数据卷：Docker named volume `pgdata`
- 图片上传：`/var/www/html/uploads/images`
- 知识库文件：`/var/www/html/uploads/knowledge`
- 运行日志：`/var/www/html/logs`

## 6. 更新部署

拉取新代码后执行：

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build
```

如果只是重启：

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod restart
```

## 7. 停止与卸载

停止容器：

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod down
```

如果连数据卷一起删除：

```bash
docker compose -f docker-compose.prod.yml --env-file .env.prod down -v
```

注意：

- `down -v` 会删除数据库、上传文件和日志

## 8. 当前约束

- 当前使用的是 PHP 内置服务器，不是 Nginx + PHP-FPM
- 当前数据库是 PostgreSQL，适合多进程/多容器并发运行
- 生产环境中 `scheduler` 默认随主站一起启动
- AI 模型密钥已加密存储，但依赖 `APP_SECRET_KEY` 正确注入

## 9. 建议

- 生产环境将 `.env.prod` 放在受控目录，不要提交到仓库
- 首次部署后备份 `pgdata` 卷与 `uploads/`、`logs/` 数据
- 如需导入旧 SQLite 数据，使用 `php bin/migrate_sqlite_to_pg.php`
