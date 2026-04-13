# GEOFlow 公开仓库首发目录清单与清理命令

## 1. 首发目标

这份清单只面向**公开仓库首发**。

目标是生成一个可以推送到 GitHub 公开仓库的干净目录，而不是继续在当前私有仓库里直接做公开发布。

推荐公开仓库本地目录：

```text
/Users/laoyao/AI Coding/01-Projects/OpenSource/GEOFlow
```

私有开发仓库固定为：

```text
/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统
```

## 2. 公开仓库首发建议保留的顶层目录

首发建议保留这些顶层内容：

- `admin/`
- `api/`
- `assets/`
- `bin/`
- `docker/`
- `docs/`
- `includes/`
- `index.php`
- `article.php`
- `archive.php`
- `category.php`
- `router.php`
- `docker-compose.yml`
- `docker-compose.prod.yml`
- `.env.example`
- `.gitignore`
- `README.md`
- `LICENSE`

说明：

- `docs/` 只保留公开文档，不带历史归档、备份和私有快照
- `bin/` 只保留正式脚本，不带运行日志和本地状态目录

## 3. 首发必须排除的目录和文件

以下内容不得进入公开仓库：

- `.env`
- `.claude/**`
- `.agents/**`
- `uploads/**`
- `data/db/**`
- `data/backups/**`
- `logs/**`
- `bin/logs/**`
- `docs/git/repo/**`
- `bin/git/state/**`
- `docs/git/state/**`
- `data/login_attempts.json`
- `docs/archived/**`
- `docs/backups/**`
- `admin/legacy/**`
- `*.bak`
- `*-backup.php`
- `tmp-*`
- `.DS_Store`

## 4. 为什么这些内容要排除

### 运行态数据

- `uploads/**`
- `data/db/**`
- `data/backups/**`
- `logs/**`
- `bin/logs/**`

这些是运行时文件，不是源码的一部分。

### 本地状态

- `docs/git/repo/**`
- `bin/git/state/**`
- `docs/git/state/**`
- `data/login_attempts.json`

这些是本地同步、登录、自动推送产生的状态数据。

### 历史包袱

- `docs/archived/**`
- `docs/backups/**`
- `admin/legacy/**`
- `*.bak`
- `*-backup.php`

这些不属于首发开源仓库需要承载的内容，而且当前历史归档中还存在真实密钥风险。

### 垃圾和临时文件

- `tmp-*`
- `.DS_Store`

## 5. 首发清理命令

如果你是清理一个已有的公开工作目录，可以先在**公开目录**里执行：

```bash
cd "/Users/laoyao/AI Coding/01-Projects/OpenSource/GEOFlow"

rm -rf uploads data/db data/backups logs bin/logs docs/git/repo \
       bin/git/state docs/git/state docs/archived docs/backups admin/legacy
find . -name '.DS_Store' -delete
find . -name '*.bak' -delete
find . -name '*-backup.php' -delete
find . -maxdepth 1 -name 'tmp-*' -delete
```

## 6. 推荐的首发生成命令

不要在公开目录手工复制文件，直接从私有仓库生成：

```bash
cd "/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统"

sh bin/git/check-open-source-release.sh
sh bin/git/prepare-open-source-release.sh "/Users/laoyao/AI Coding/01-Projects/OpenSource/GEOFlow"
```

## 7. 公开仓库首发前检查

进入公开目录后检查：

```bash
cd "/Users/laoyao/AI Coding/01-Projects/OpenSource/GEOFlow"

find . -maxdepth 2 -type d | sort
find . -name '.DS_Store'
find . -name '*.bak'
find . -name '*-backup.php'
find . -maxdepth 1 -name 'tmp-*'
```

应当满足：

1. 看不到 `uploads/`
2. 看不到 `data/db/`
3. 看不到 `logs/`
4. 看不到 `admin/legacy/`
5. 看不到 `docs/archived/`

## 8. 首发推送命令

```bash
cd "/Users/laoyao/AI Coding/01-Projects/OpenSource/GEOFlow"

git init
git remote add origin <your-public-github-repo>
git checkout -b main
git add -A
git commit -m "sync(open-source): initial public release"
git push -u origin main
```

## 9. 后续每次发布命令

```bash
cd "/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统"

sh bin/git/check-open-source-release.sh
sh bin/git/prepare-open-source-release.sh "/Users/laoyao/AI Coding/01-Projects/OpenSource/GEOFlow"

cd "/Users/laoyao/AI Coding/01-Projects/OpenSource/GEOFlow"
git status
git add -A
git commit -m "sync(open-source): YYYY-MM-DD release sync"
git push origin main
```

## 10. 最终规则

固定记住三句话：

1. 开发只在私有仓库做
2. 公开仓库只放同步后的净化产物
3. 每次推送公开仓库之前都先跑自检
