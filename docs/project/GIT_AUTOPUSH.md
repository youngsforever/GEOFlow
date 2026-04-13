# Git 自动推送说明

本文档说明当前仓库已经接入的两套 Git 自动推送机制：

- 主代码仓库自动提交并推送
- `docs/git` 独立文档快照仓库自动提交并推送

## 1. 主代码仓库自动推送

脚本位置：

- `bin/git/autopush-code.sh`
- `bin/git/status.sh`

默认行为：

1. `git fetch origin`
2. `git add -A -- .`
3. 对暂存区里的 `*.php` 执行 `php -l`
4. 自动生成 commit
5. 推送到当前分支对应的远端分支

默认配置：

- 远端：`origin`
- 目标分支：当前分支
- 推送方式：普通 `git push`

如果你希望在远端有分歧时仍然以本地为准，可以显式启用：

```bash
AUTO_PUSH_FORCE_WITH_LEASE=true sh bin/git/autopush-code.sh
```

常用命令：

```bash
sh bin/git/autopush-code.sh
sh bin/git/autopush-code.sh "auto sync(code): manual trigger"
sh bin/git/status.sh
```

常用环境变量：

- `AUTO_PUSH_REMOTE`
- `AUTO_PUSH_BRANCH`
- `AUTO_PUSH_FORCE_WITH_LEASE`

示例：

```bash
AUTO_PUSH_REMOTE=origin AUTO_PUSH_BRANCH=master sh bin/git/autopush-code.sh
```

## 2. 文档快照自动推送

脚本位置：

- `docs/git/turn_tick.sh`
- `docs/git/sync_docs.sh`
- `docs/git/setup_remote.sh`
- `docs/git/status.sh`

行为说明：

- `turn_tick.sh` 每调用一次计 1 轮
- 每满 10 轮自动触发 `sync_docs.sh`
- `sync_docs.sh` 会将 `docs/` 镜像到 `docs/git/repo/docs_snapshot/`
- 有变更时会自动 commit
- 如果 `docs/git/repo/` 配置了 `origin`，则会继续自动 push

配置远端：

```bash
sh docs/git/setup_remote.sh git@github.com:<you>/<docs-snapshot-repo>.git
```

手动同步：

```bash
sh docs/git/sync_docs.sh "manual docs sync"
```

查看状态：

```bash
sh docs/git/status.sh
```

## 3. 推荐接入方式

代码仓库：

```bash
*/10 * * * * cd /Users/laoyao/AI\ Coding/01-Projects/Active/GEO官网系统 && sh bin/git/autopush-code.sh >> logs/git_autopush.log 2>&1
```

文档快照：

- 如果外部系统能感知“轮次结束”，就在每轮结束时调用：

```bash
cd /Users/laoyao/AI\ Coding/01-Projects/Active/GEO官网系统 && sh docs/git/turn_tick.sh
```
