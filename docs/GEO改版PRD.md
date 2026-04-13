# GEO 改版 PRD

更新时间：2026-03-27

## 1. 目标

将当前 GEO 内容系统从“可发布的博客系统”升级为“适合 AI 检索、引用、推荐的结构化知识发布系统”。

核心原则：

- 把文章写成数据库
- 把页面做成可提取的答案容器
- 把后台录入改成结构化字段，而不是只依赖长正文
- 把 AI 生成流程参数化，直接约束 GEO 质量

## 2. 设计依据

本方案基于用户提供的 21 条 GEO 技巧，归纳为四类系统能力：

- 内容增强
  - 统计数据
  - 权威引语
  - 参考来源
  - 技术术语
  - 高知识密度
  - 原子化事实
- 结构工程
  - 倒金字塔
  - 键值对
  - 表格化对比
  - 层级标题
  - FAQ
  - 步骤化列表
- 语义与逻辑
  - 显式逻辑连接
  - 实体消歧
  - 客观中立语调
  - 多意图覆盖
  - 易理解表达
- 对抗与防御
  - 战略性实体布局
  - 避免拒绝触发词
  - 查询重写优化
  - 上下文无关摘要

## 3. 当前系统问题

当前系统已具备：

- 文章详情页、首页、分类页、归档页
- 后台文章编辑、任务创建、网站设置
- 基础 SEO 和结构化数据

当前缺口：

- 文章详情页缺少结构化摘要、FAQ、来源、关键事实块
- 列表页仍偏博客流，不是问题入口页
- 后台没有 GEO 专用字段
- 任务创建页没有 GEO 生成策略
- 文章质量没有 GEO 评分机制

## 4. 改版目标页

前台：

- [index.php](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/index.php)
- [category.php](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/category.php)
- [archive.php](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/archive.php)
- [article.php](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/article.php)

后台：

- [admin/article-edit.php](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/geo_admin/article-edit.php)
- [admin/article-create.php](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/geo_admin/article-create.php)
- [admin/article-view.php](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/geo_admin/article-view.php)
- [admin/task-create.php](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/geo_admin/task-create.php)
- [admin/task-edit.php](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/geo_admin/task-edit.php)
- [admin/site-settings.php](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/geo_admin/site-settings.php)

生成链路：

- [includes/ai_engine.php](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/includes/ai_engine.php)
- [includes/functions.php](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/includes/functions.php)
- [includes/seo_functions.php](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/includes/seo_functions.php)

## 5. 前台改版方案

### 5.1 文章详情页

目标：

- 让详情页成为 AI 可直接提取的答案页

新增区块：

1. 上下文无关摘要
- 位置：标题和元信息下方，正文前
- 长度：80-120 字
- 要求：
  - 独立可读
  - 包含主题实体
  - 包含至少一个数字或结论

2. 关键事实块
- 格式：键值对或短句列表
- 示例：
  - 适用年龄：8-12 岁
  - 主流平台数量：5 个
  - 平均课程价格：3000-8000 元

3. FAQ 模块
- 格式：
  - Q: 少儿编程课程应该怎么选？
  - A: 优先看年龄适配、课程体系、老师质量和项目实践密度。

4. 参考来源
- 显示引用来源、数据来源、机构名称、链接

5. 教程型步骤列表
- 针对 how-to 内容使用 1. 2. 3.

6. 对比表
- 针对对比型内容使用 Markdown 表格

页面结构建议：

1. 标题
2. 发布时间 / 阅读量 / 阅读时长
3. 结构化摘要
4. 关键事实块
5. 正文
6. FAQ
7. 来源与参考
8. 相关文章

### 5.2 首页与分类页

目标：

- 从“信息流”改成“问题入口页”

改动点：

- 文章卡片摘要优先显示结论句，而不是正文截断
- 卡片增加“适合回答的问题”
- 分类页头部增加“本分类常见问题”
- 推荐内容区优先按问题组织，而不是只按发布时间组织

### 5.3 首页 Hero

目标：

- 首页第一屏直接传达站点可解决的问题

改动点：

- 增加“站点定位一句话”
- 增加“覆盖主题 / 数据规模”
- 增加“热门问题入口”
- 增加“高频查询词导航”

## 6. 后台改版方案

### 6.1 文章结构化字段

在文章模型中新增以下字段：

- `context_independent_summary`
- `key_facts_json`
- `faq_json`
- `source_references_json`
- `expert_quotes_json`
- `comparison_table_md`
- `howto_steps_json`
- `target_queries`
- `technical_terms`
- `geo_score`
- `geo_notes`

说明：

- 优先使用 JSON 保存列表型结构
- Markdown 表格可单独存文本

### 6.2 后台文章编辑页

目标：

- 把“写作建议”变成“结构化录入”

新增编辑区块：

1. GEO 摘要
2. 关键事实
3. FAQ
4. 数据来源 / 参考文献
5. 权威引语
6. 对比表
7. 步骤列表
8. 目标查询词
9. 技术术语

### 6.3 后台文章查看页

目标：

- 增加 GEO 质量诊断

建议评分项：

- 是否包含数字
- 是否包含 FAQ
- 是否包含来源
- 是否包含步骤列表
- 是否包含对比表
- 是否存在营销语气过强
- 是否存在代词过多
- 是否存在超长句
- 是否具备上下文无关摘要

输出：

- 总分 0-100
- 问题提示列表
- 修复建议列表

## 7. 任务系统改版方案

### 7.1 任务创建页新增 GEO 策略

新增字段：

- `geo_generate_summary`
- `geo_generate_key_facts`
- `geo_generate_faq`
- `geo_generate_sources`
- `geo_generate_quotes`
- `geo_generate_table`
- `geo_generate_steps`
- `geo_target_intent`
- `geo_tone`
- `geo_term_density`
- `geo_require_statistics`
- `geo_sentence_max_length`

### 7.2 任务意图分类

`geo_target_intent` 枚举值：

- `informational`
- `commercial`
- `how_to`
- `comparison`
- `mixed`

### 7.3 生成流程改造

在 [includes/ai_engine.php](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/includes/ai_engine.php) 中增加：

- 正文生成后，二次生成 GEO 结构字段
- 根据任务策略决定输出哪些区块
- 对摘要、FAQ、事实块做后处理校验

## 8. 网站设置改版方案

在 [admin/site-settings.php](/Users/laoyao/AI Coding/01-Projects/Active/GEO官网系统/geo_admin/site-settings.php) 新增全局模板设置：

- 默认 GEO 摘要模板
- 默认 FAQ 模板
- 默认关键事实模板
- 默认来源区块模板
- 默认步骤模板
- 默认对比表模板
- 默认查询意图模板

用途：

- 给任务生成提供默认模版
- 给手工写作提供后台提示

## 9. 数据结构建议

### 9.1 文章表新增字段

建议新增列：

- `context_independent_summary` TEXT
- `key_facts_json` TEXT
- `faq_json` TEXT
- `source_references_json` TEXT
- `expert_quotes_json` TEXT
- `comparison_table_md` TEXT
- `howto_steps_json` TEXT
- `target_queries` TEXT
- `technical_terms` TEXT
- `geo_score` INTEGER DEFAULT 0
- `geo_notes` TEXT

### 9.2 任务表新增字段

建议新增列：

- `geo_generate_summary` INTEGER DEFAULT 1
- `geo_generate_key_facts` INTEGER DEFAULT 1
- `geo_generate_faq` INTEGER DEFAULT 1
- `geo_generate_sources` INTEGER DEFAULT 1
- `geo_generate_quotes` INTEGER DEFAULT 0
- `geo_generate_table` INTEGER DEFAULT 0
- `geo_generate_steps` INTEGER DEFAULT 0
- `geo_target_intent` VARCHAR(30) DEFAULT 'mixed'
- `geo_tone` VARCHAR(30) DEFAULT 'objective'
- `geo_term_density` VARCHAR(20) DEFAULT 'medium'
- `geo_require_statistics` INTEGER DEFAULT 1
- `geo_sentence_max_length` INTEGER DEFAULT 48

## 10. 实施优先级

### 第一阶段

目标：

- 最快提升 GEO 表现

内容：

- 文章详情页新增摘要、关键事实、FAQ、来源
- 后台文章编辑页新增对应字段
- 后台文章查看页新增 GEO 评分

### 第二阶段

目标：

- 让 AI 生成链路直接产出 GEO 内容

内容：

- 任务创建页增加 GEO 策略
- AI 生成流程增加 GEO 结构字段输出
- 列表页改为问题入口卡片

### 第三阶段

目标：

- 全站统一 GEO 方法论

内容：

- 网站设置新增 GEO 模板
- 首页 Hero 和分类页常见问题导航
- GEO 审核与发布流程

## 11. 成功指标

前台指标：

- 文章页平均停留时间
- 列表页点击率
- FAQ 模块展开率

内容指标：

- 含摘要文章占比
- 含 FAQ 文章占比
- 含来源文章占比
- 含数据统计文章占比

系统指标：

- GEO 平均分
- 低于阈值文章占比
- 任务生成后人工修改率

## 12. 推荐实施顺序

如果只做第一批，我建议先做：

1. 文章表扩展 GEO 字段
2. 后台文章编辑页加结构化字段
3. 文章详情页渲染摘要 / FAQ / 来源 / 关键事实
4. 后台文章查看页 GEO 评分
5. 任务创建页 GEO 策略开关

这五步做完，当前系统就会从“普通内容管理系统”明显转向“面向 GEO 的结构化内容系统”。
