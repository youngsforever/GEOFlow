# GitHub 开源上传规则

## 1. 后台 API 配置到底存在哪里

这个项目的 API 配置不是单独放在某个配置文件里，而是分成两层：

1. 文件级配置
   - `/includes/config.php`
   - `/includes/db_support.php`
   - 这两处负责读取环境变量，例如 `APP_SECRET_KEY`、`DB_HOST`、`DB_NAME`、`DB_USER`、`DB_PASSWORD`、`SITE_URL`

2. 数据库存储
   - AI 模型配置保存在 `ai_models` 表
   - 真正的模型密钥保存在 `ai_models.api_key`
   - 存储时会经过 `encrypt_ai_api_key()` 加密，读取时再解密
   - 加密依赖 `APP_SECRET_KEY`

结论：

- `includes/config.php` 不是 API key 明文配置文件
- 真正敏感的是：
  - `.env` 中的 `APP_SECRET_KEY` / 数据库连接信息
  - 数据库里的 `ai_models.api_key`
  - 数据库里的 `site_settings`、`admins` 等业务数据

## 2. 开源时绝对不能上传的内容

以下内容属于隐私、运行态数据或本机状态，禁止上传到 GitHub：

- `.env`
- 任何真实环境变量文件，如 `.env.local`、`.env.prod`、`.env.staging`
- `data/db/` 下的数据库文件
- `data/backups/` 下的数据库备份
- `uploads/` 下的用户上传文件
- `logs/` 和 `bin/logs/` 下的运行日志、PID、flag
- `docs/git/repo/` 下的本地文档快照仓库
- `bin/git/state/`、`docs/git/state/` 下的自动推送状态文件
- 任意导出的 SQL、CSV、JSON、Excel、Word、PDF 数据快照
- 含真实 AI key、统计代码、广告投放配置、联系人邮箱的数据库导出
- 含真实密钥的历史备份或归档文件，尤其是 `docs/archived/backup_old/` 这类历史快照

## 3. 可以上传的内容

以下内容可以作为开源仓库主体上传：

- PHP 源码
- `assets/css`、`assets/js`、主题静态资源
- Docker 配置、启动脚本、迁移脚本
- `.env.example`
- README 和经过审查的公开文档
- 不包含真实业务数据的测试/示例代码

## 4. 建议第一版开源直接剔除的内容

这些内容不一定都属于“隐私”，但会显著提高噪音、泄露历史实现细节，或者直接包含高风险历史快照。第一版公开仓库建议直接不带：

- `docs/archived/`
- `docs/backups/`
- `docs/archived/backup_old/`
- `admin/legacy/`
- `*-backup.php`
- `*.bak`
- `tmp-*`

结论：

- 这些目录和文件不建议继续出现在首版公开仓库历史里
- 如果已经被 Git 跟踪，应该在公开分支里移除

## 5. 需要人工复核后再决定是否上传的内容

这些内容不一定是隐私，但通常不建议作为第一版开源仓库直接公开：

- 含个人姓名、个人邮箱、历史内部说明的文档
- 部署说明中带有内网 IP、内网域名、业务供应商账号的章节
- 示例数据中带真实品牌、真实客户、真实作者信息的内容

这些文件更像历史沉淀、排障材料或内部迁移痕迹，会提高维护噪音。

## 6. 推荐的 GitHub 上传规则

### 硬规则

1. 仓库只上传源码、示例配置和公开文档
2. 所有真实密钥只允许存在于环境变量或部署平台 Secret 中
3. 所有后台配置数据只保留“结构”，不保留“真实内容”
4. 所有运行日志、数据库、上传文件必须留在本地或对象存储
5. 发布前必须跑一遍开源自检脚本

### 执行命令

```bash
cd /Users/laoyao/AI\ Coding/01-Projects/Active/GEO官网系统
sh bin/git/check-open-source-release.sh
```

如果脚本报出 blocking issue，先把这些文件从 Git 索引中移除，再提交：

```bash
git rm -r --cached uploads logs bin/logs data/db data/backups docs/git/repo
git rm --cached .env
```

如果历史归档里已经带有真实密钥，公开前还要一并移除：

```bash
git rm -r --cached docs/archived/backup_old admin/legacy
git rm --cached '*.bak' '*-backup.php'
```

## 7. 这套项目特别需要注意的点

- `APP_SECRET_KEY` 既影响 session / 安全逻辑，也影响 AI key 解密，绝不能公开
- `site_settings` 可能包含统计代码、联系邮箱、社交账号、底部广告等运营配置
- `ai_models` 虽然做了加密存储，但数据库整体一旦公开，仍然属于泄露
- `uploads/knowledge/*.docx` 属于业务知识库原始材料，默认应视为私有资产
- `docs/archived/backup_old/*.backup_*` 这类历史文件即使不参与运行，也可能直接包含旧版 API key，必须视为阻断项

## 8. 建议的开源发布方式

推荐采用“两仓策略”：

- 公开仓库：只放源码、文档、示例配置
- 私有运行仓库或服务器：保留 `.env`、数据库、上传素材、运行日志

这样可以避免把“代码”与“业务数据”混在一个 Git 历史里。

## 9. 发布前最小检查清单

发布前至少逐项确认：

1. `.env` 和其他真实环境变量文件未被 Git 跟踪
2. `data/db/`、`data/backups/`、`uploads/`、`logs/` 未被 Git 跟踪
3. `docs/archived/backup_old/` 不在公开分支中
4. `admin/legacy/` 不在首版公开分支中
5. 仓库内不存在真实 `sk-` 风格 API key
6. 运行 `sh bin/git/check-open-source-release.sh` 结果为通过

## 10. 与 GitHub 发布流程的关系

如果你准备正式对外开源，不建议直接把当前私有仓库切公开。

建议配套阅读：

- `docs/project/OPEN_SOURCE_RELEASE_POLICY.md`

这份文档定义了：

- 私有仓库与公开仓库的角色边界
- 本地应该基于哪个仓库继续迭代
- 每次向 GitHub 公开仓库推送时的固定流程
- 同步脚本 `bin/git/prepare-open-source-release.sh` 的使用方式
