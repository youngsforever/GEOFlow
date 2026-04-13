# docs/git 机制说明

## 目标

为 `docs/` 目录建立一个独立的文档快照仓库，并提供“每 10 轮同步一次”的轻量机制。

## 现实约束

主项目根目录已经是 Git 仓库，但这里仍然保留独立文档快照仓库，原因是：

- 在 `docs/git/repo/` 下初始化一个独立 Git 仓库
- 将当前项目的 `docs/` 内容镜像到该仓库
- 排除 `docs/git/` 本身，避免递归
- 文档快照可以单独配置一个 GitHub 远端，不干扰主代码仓库

## 目录说明

- `docs/git/turn_tick.sh`
  - 每调用一次，记 1 轮
  - 每满 10 轮自动触发同步
- `docs/git/sync_docs.sh`
  - 手动立即同步 `docs/` 快照
  - 有变更时自动 commit
  - 如果 `origin` 已配置，则会自动 push
- `docs/git/status.sh`
  - 查看当前轮次和最近同步状态
- `docs/git/setup_remote.sh`
  - 为独立文档快照仓库设置或更新 `origin`
- `docs/git/state/`
  - 记录轮次和最近一次同步信息
- `docs/git/repo/`
  - 独立 Git 快照仓库

## 使用方式

### 初始化后手动同步

```bash
sh docs/git/sync_docs.sh "manual docs sync"
```

### 配置文档快照仓库远端

```bash
sh docs/git/setup_remote.sh git@github.com:<you>/<docs-snapshot-repo>.git
```

### 每轮记一次

```bash
sh docs/git/turn_tick.sh
```

### 查看状态

```bash
sh docs/git/status.sh
```

## 自动机制接入建议

如果你希望真正做到“每 10 轮自动同步”，需要在你的外部工作流里，在每轮对话结束后调用一次：

```bash
sh docs/git/turn_tick.sh
```

因为仓库内脚本本身无法感知聊天轮数，必须由外部调用来提供“轮次事件”。

## 快照内容

同步时会将文档型内容镜像到：

- `docs/git/repo/docs_snapshot/`

并排除：

- `docs/git/`
- `docs/archived/`
- `docs/backups/`
- `docs/runtime/`
- `docs/scripts/`
- `docs/maintenance/`
- `docs/diagnostics/`

当前默认保留：

- `*.md`
- `*.txt`
- `deployment/Caddyfile`

## 提交规则

- 只有检测到文档实际变化时才会 commit
- 配置了 `origin` 后，commit 完成会继续自动 push
- 自动同步提交信息格式：
  - `docs sync: auto turn 10`
- 手动同步提交信息格式：
  - 使用调用参数
