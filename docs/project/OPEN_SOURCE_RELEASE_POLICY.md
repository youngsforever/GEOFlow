# GEOFlow 开源发布与 GitHub 推送规则

## 1. 目标

这份规则只解决三件事：

1. 哪个仓库是日常开发主仓库
2. 哪个仓库是对外公开的开源仓库
3. 本地迭代后如何安全地把代码发布到 GitHub 开源仓库

结论先行：

- 私有仓库是**开发事实源**
- 开源仓库是**净化后的发布产物**
- 不允许把当前私有仓库直接切公开

## 2. 仓库角色定义

建议固定成两仓模式。

### 私有仓库

用途：

- 日常开发
- 真实部署配置
- 数据库结构演进
- 本地上传文件
- 历史排障资料
- 内部文档与临时文件

本地建议目录：

```text
/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统
```

规则：

- 这里是唯一开发主仓库
- 所有功能开发、修复、重构都先在这里完成
- 所有真实 `.env`、数据库、上传文件、日志都只留在这里

### 开源仓库

用途：

- 对外发布源码
- 对外维护 README、LICENSE、示例配置、公开文档
- 对外接受 issue / PR

本地建议目录：

```text
/Users/laoyao/AI Coding/01-Projects/OpenSource/GEOFlow
```

规则：

- 这里不是日常开发主仓库
- 这里只接收从私有仓库同步出来的“开源净化版本”
- 不把真实运行数据、历史备份和内部资料带进来
- 不把 `.claude/`、`.agents/` 这类本地工具目录带进来

## 3. 开发事实源规则

### 固定规则

1. 你本地所有功能开发都基于私有仓库进行
2. 开源仓库不作为主开发仓库
3. 公开仓库的内容必须由私有仓库“筛选后同步”生成
4. 不允许把私有仓库 remote 直接改成公开 GitHub 仓库后强推

### 为什么这样定

因为当前私有仓库已经天然混合了：

- 运行态目录
- 历史归档
- 旧备份文件
- 内部调试脚本
- 上传素材
- 真实配置和敏感数据风险

所以开源仓库必须是“产物仓”，而不是“当前仓库直接公开”。

## 4. 开源仓库允许包含的内容

### 可以进入开源仓库

- `admin/` 当前正式后台页面
- `includes/`
- `assets/`
- 前台入口页面
- `docker/`
- `docker-compose.yml`
- `docker-compose.prod.yml`
- `bin/` 中正式可公开的脚本
- `.env.example`
- `README.md`
- `LICENSE`
- 精简后的公开文档

### 禁止进入开源仓库

- `.env`
- 任何真实环境变量文件
- `uploads/**`
- `data/db/**`
- `data/backups/**`
- `logs/**`
- `bin/logs/**`
- `docs/git/repo/**`
- `.claude/**`
- `.agents/**`
- `bin/git/state/**`
- `docs/git/state/**`
- `data/login_attempts.json`
- `docs/archived/backup_old/**`
- `admin/legacy/**`
- `docs/backups/**`
- `*.bak`
- `*-backup.php`
- `tmp-*`
- `.DS_Store`

### 第一版建议直接剔除

即使不一定敏感，也建议不带入首版开源仓库：

- `docs/archived/**`
- `docs/backups/**`
- `admin/legacy/**`
- 旧原型、旧测试页、旧排障材料

## 5. GitHub 发布方式

推荐使用“新建独立公开仓库”的方式发布，不修改现有私有仓库可见性。

### 正式做法

1. 在 GitHub 新建一个全新的公开仓库
2. 本地准备一个独立的开源工作目录
3. 从私有仓库把“可公开内容”同步到这个目录
4. 在这个目录里初始化或连接公开 Git 仓库
5. 在公开仓库中单独提交和推送

### 不推荐的做法

- 把现在的私有仓库直接改成公开
- 直接在私有仓库上删除一批文件后强推到新公开 remote
- 在公开仓库里长期做主开发，再手动回拷到私有仓库

## 6. 本地目录与推送模型

建议固定这两个目录：

```text
私有开发仓库
/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统

公开发布仓库
/Users/laoyao/AI Coding/01-Projects/OpenSource/GEOFlow
```

### 推送规则

#### 私有仓库

- 只推送到私有 GitHub / 私有 remote
- 保留完整开发历史
- 保留内部文档和真实运行态文件

#### 开源仓库

- 只推送筛选后的源码
- 只推送到公开 GitHub 仓库
- 每次推送前必须执行开源自检

## 7. 日常迭代规则

### 日常开发

固定在私有仓库做：

```bash
cd "/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统"
```

做完后先在私有仓库完成：

- 开发
- 本地验证
- Docker 验证
- 后台页面验证
- 开源风险检查

### 准备同步到开源仓库

在私有仓库运行：

```bash
sh bin/git/check-open-source-release.sh
```

如果通过，再执行发布同步脚本：

```bash
sh bin/git/prepare-open-source-release.sh "/Users/laoyao/AI Coding/01-Projects/OpenSource/GEOFlow"
```

### 公开仓库提交与推送

进入公开仓库目录后再执行：

```bash
cd "/Users/laoyao/AI Coding/01-Projects/OpenSource/GEOFlow"
git status
git add -A
git commit -m "sync(open-source): 2026-04-13 release sync"
git push origin main
```

## 8. 迭代与同步的硬规则

### 必须遵守

1. 功能代码先进入私有仓库
2. 开源仓库只接收筛选后的同步结果
3. 所有公开发布前都先跑 `check-open-source-release.sh`
4. 私有仓库和公开仓库不混用 remote

### 明确禁止

1. 直接在公开仓库里做主线开发
2. 直接把私有仓库整体 push 到公开 GitHub
3. 把 `.env`、上传文件、数据库、日志带到公开仓库
4. 把历史备份和归档目录一起带到公开仓库

### 例外规则

如果公开仓库里必须修一个只影响开源包装层的问题，例如：

- README
- LICENSE
- GitHub Actions
- issue template
- 开源文档

可以直接在公开仓库改。

但如果是业务代码变更：

- 先回到私有仓库改
- 再重新同步到公开仓库

## 9. GitHub 提交信息建议

### 私有仓库提交

正常按开发语义提交，例如：

```text
fix: repair task list layout
feat: add article markdown table rendering
refactor: split article service from admin pages
```

### 公开仓库提交

建议固定成同步语义，避免误导外部协作者以为公开仓库是主开发源：

```text
sync(open-source): 2026-04-13 initial public release
sync(open-source): 2026-04-14 task page fixes
sync(open-source): 2026-04-15 API and CLI docs refresh
```

## 10. 发布前检查清单

每次向公开仓库推送前，至少确认：

1. 私有仓库代码已完成自测
2. `sh bin/git/check-open-source-release.sh` 已通过
3. 没有真实 API key、数据库、上传文件、日志进入同步目录
4. `docs/archived/backup_old/`、`admin/legacy/` 等历史目录未进入公开仓库
5. 公开仓库内 README、LICENSE、`.env.example` 齐全

## 11. 推荐发布流程

### 第一次正式开源发布

1. 在 GitHub 新建公开仓库
2. 本地建立公开工作目录
3. 跑开源自检
4. 运行发布同步脚本
5. 检查公开目录内容
6. 在公开目录内 `git init`
7. 提交并推送

首发目录内容与清理命令参考：

- `docs/project/OPEN_SOURCE_FIRST_RELEASE_MANIFEST.md`

### 后续每次发布

1. 在私有仓库开发和验证
2. 跑开源自检
3. 同步到公开目录
4. 在公开目录中 commit / push

## 12. 最终结论

后面你的本地迭代基准应该固定为：

```text
/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统
```

后面你的 GitHub 公开推送目标应该固定为：

```text
/Users/laoyao/AI Coding/01-Projects/OpenSource/GEOFlow
```

也就是说：

- **开发在私有仓库**
- **发布在公开仓库**
- **同步靠规则和脚本**

这是当前这个项目最稳的开源方式。
