# 敏感词检测与处理流程分析

## 📋 概述

本文档详细说明了GEO网站系统中敏感词的检测逻辑和文章生成/发布流程。

---

## 🗄️ 数据库结构

### sensitive_words 表

```sql
CREATE TABLE IF NOT EXISTS sensitive_words (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    word VARCHAR(200) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

**字段说明**：
- `id`: 主键
- `word`: 敏感词内容（唯一）
- `created_at`: 创建时间

**当前实现特点**：
- ✅ 简单的敏感词列表
- ❌ 没有严重级别（severity）
- ❌ 没有替换词（replacement）
- ❌ 没有命中次数统计（hit_count）
- ❌ 没有分类（category）

---

## 🔍 敏感词检测函数

### 1. ai_engine.php 中的实现

**位置**: `includes/ai_engine.php` 第589-604行

```php
public function checkSensitiveWords($content) {
    $stmt = $this->db->prepare("SELECT word FROM sensitive_words");
    $stmt->execute();
    $sensitive_words = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($sensitive_words as $word) {
        if (strpos($content, $word) !== false) {
            return [
                'has_sensitive' => true,
                'word' => $word
            ];
        }
    }
    
    return ['has_sensitive' => false];
}
```

**检测逻辑**：
- 从数据库读取所有敏感词
- 使用 `strpos()` 进行简单字符串匹配
- **只检测内容（content），不检测标题（title）**
- 发现第一个敏感词后立即返回
- 返回格式：`['has_sensitive' => bool, 'word' => string]`

**局限性**：
- ❌ 区分大小写（`strpos` 区分大小写）
- ❌ 无法检测变体（如：敏感词 → 敏 感 词）
- ❌ 无法检测拼音或谐音
- ❌ 只返回第一个匹配的敏感词

### 2. ai_service.php 中的实现

**位置**: `includes/ai_service.php` 第334-349行

```php
public function checkSensitiveWords($content) {
    $stmt = $this->db->prepare("SELECT word FROM sensitive_words");
    $stmt->execute();
    $sensitive_words = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($sensitive_words as $word) {
        if (strpos($content, $word) !== false) {
            return [
                'has_sensitive' => true,
                'word' => $word
            ];
        }
    }

    return ['has_sensitive' => false];
}
```

**说明**: 与 `ai_engine.php` 中的实现完全相同。

---

## 🔄 AI生成文章时的敏感词检测流程

### 流程图

```
开始生成文章
    ↓
1. 调用AI生成内容
    ↓
2. 插入图片（如果配置）
    ↓
3. 生成关键词和描述
    ↓
4. 【敏感词检测】checkSensitiveWords(content)
    ↓
    ├─ 发现敏感词 → 返回 error，文章不保存
    │                  记录日志：WARNING
    │                  返回：['success' => false, 'error' => '文章包含敏感词，已自动删除']
    │
    └─ 未发现敏感词 → 继续保存文章
           ↓
       5. 保存到数据库
           ↓
       6. 根据 need_review 设置状态
           ├─ need_review = true  → status='draft', review_status='pending'
           └─ need_review = false → status='published', review_status='auto_approved'
           ↓
       完成
```

### 代码位置

**文件**: `includes/task_service.php` 第437-508行

**关键代码**:

```php
private function saveGeneratedArticle($task, $title_data, $content) {
    try {
        // 检查敏感词
        $sensitive_check = $this->ai_service->checkSensitiveWords($content);
        if ($sensitive_check['has_sensitive']) {
            write_log("文章包含敏感词: {$sensitive_check['word']}", 'WARNING');
            return ['success' => false, 'error' => '文章包含敏感词，已自动删除'];
        }
        
        // ... 继续保存文章
    }
}
```

**检测时机**: 
- ✅ 在保存文章到数据库**之前**
- ✅ 在生成内容**之后**
- ❌ **只检测内容（content），不检测标题（title）**

**检测结果**:
- **发现敏感词**: 文章不会保存到数据库，直接返回错误
- **未发现敏感词**: 继续保存文章

---

## 📝 文章发布时的敏感词检测流程

### 定时任务检测

**文件**: `bin/cron.php` 第194-230行

```php
function checkArticleSensitiveWords($article_id, $ai_engine) {
    global $db;
    
    // 获取文章内容
    $stmt = $db->prepare("SELECT title, content FROM articles WHERE id = ?");
    $stmt->execute([$article_id]);
    $article = $stmt->fetch();
    
    // 检查标题和内容中的敏感词
    $title_check = $ai_engine->checkSensitiveWords($article['title']);
    $content_check = $ai_engine->checkSensitiveWords($article['content']);
    
    if ($title_check['has_sensitive'] || $content_check['has_sensitive']) {
        // 发现敏感词，将文章移至垃圾箱
        $sensitive_word = $title_check['has_sensitive'] ? $title_check['word'] : $content_check['word'];
        
        $stmt = $db->prepare("
            UPDATE articles SET 
                deleted_at = CURRENT_TIMESTAMP,
                status = 'private'
            WHERE id = ?
        ");
        $stmt->execute([$article_id]);
        
        log_message("文章 {$article_id} 包含敏感词 '{$sensitive_word}'，已移至垃圾箱");
    }
}
```

**检测时机**: 
- ✅ 定时任务（cron）执行时
- ✅ **同时检测标题和内容**

**检测结果**:
- **发现敏感词**: 
  - 设置 `deleted_at = CURRENT_TIMESTAMP`（软删除）
  - 设置 `status = 'private'`
  - 记录系统日志
- **未发现敏感词**: 不做任何操作

---

## ⚠️ 当前实现的问题

### 1. 标题检测不一致

**问题**: 
- AI生成时：**只检测内容，不检测标题**
- 定时任务：**同时检测标题和内容**

**影响**: 
- 包含敏感词的标题可能在生成时通过检测
- 但在定时任务中被检测到并删除

**建议**: 统一在生成时同时检测标题和内容

### 2. 检测方法简单

**问题**:
- 使用简单的 `strpos()` 字符串匹配
- 区分大小写
- 无法检测变体、拼音、谐音

**建议**: 
- 使用 `stripos()` 实现不区分大小写
- 添加正则表达式支持
- 考虑使用专业的敏感词过滤库

### 3. 缺少敏感词管理功能

**问题**:
- 没有严重级别（high/medium/low）
- 没有替换词功能
- 没有命中统计
- 没有分类管理

**建议**: 扩展数据库表结构，添加更多字段

---

## 📊 完整流程总结

### AI生成文章流程

```
1. 任务启动
2. 选择标题
3. 调用AI生成内容
4. 插入图片
5. 生成关键词和描述
6. 【敏感词检测】checkSensitiveWords(content)  ← 只检测内容
   ├─ 有敏感词 → 不保存，返回错误
   └─ 无敏感词 → 继续
7. 保存文章到数据库
   ├─ need_review=true  → draft + pending
   └─ need_review=false → published + auto_approved
8. 完成
```

### 定时任务检测流程

```
1. Cron任务执行
2. 遍历已发布文章
3. 【敏感词检测】checkSensitiveWords(title + content)  ← 检测标题和内容
   ├─ 有敏感词 → 软删除（deleted_at + status=private）
   └─ 无敏感词 → 不处理
4. 记录日志
5. 完成
```

---

## 🎯 建议优化方案

### 短期优化

1. **统一检测逻辑**: 在生成时同时检测标题和内容
2. **不区分大小写**: 使用 `stripos()` 替代 `strpos()`
3. **返回所有敏感词**: 不只返回第一个，返回所有匹配的敏感词

### 中期优化

1. **添加敏感词级别**: high/medium/low
2. **添加替换功能**: 自动替换为 `***` 或指定词
3. **添加命中统计**: 记录每个敏感词的命中次数

### 长期优化

1. **使用专业库**: 集成成熟的敏感词过滤库
2. **AI辅助检测**: 使用AI模型进行语义级别的敏感内容检测
3. **实时检测**: 在AI生成过程中实时检测并重新生成

---

## ✅ 已完成的优化（2026-02-02）

### 1. 修改敏感词检测逻辑

**文件**: `includes/task_service.php` 第437-527行

**改进内容**:
- ✅ **同时检测标题和内容**（之前只检测内容）
- ✅ **发现敏感词时不再拒绝保存**，而是保存为草稿
- ✅ **设置 `review_status = 'sensitive_word'`** 标记为敏感词触发
- ✅ **在 `excerpt` 字段添加敏感词信息**：`"⚠️ 敏感词触发：{$sensitive_word}"`
- ✅ **强制设置 `status = 'draft'` 和 `published_at = NULL`**

**新流程**:
```
AI生成文章 → 检测敏感词（标题+内容）
    ├─ 发现敏感词 → 保存为草稿 + review_status='sensitive_word' + excerpt标注
    └─ 未发现敏感词 → 按任务配置决定（need_review）
```

### 2. 改进敏感词检测函数

**文件**: `includes/ai_service.php` 第331-365行

**改进内容**:
- ✅ **不区分大小写**: 使用 `mb_strtolower()` + `mb_strpos()`
- ✅ **支持中文**: 使用 `mb_*` 函数，指定 UTF-8 编码
- ✅ **按长度排序**: 优先匹配较长的敏感词（避免误匹配）
- ✅ **返回所有匹配的敏感词**:
  - `word`: 第一个匹配的敏感词（最长的）
  - `all_words`: 所有匹配的敏感词数组
  - `count`: 匹配的敏感词数量

**返回格式**:
```php
// 发现敏感词
[
    'has_sensitive' => true,
    'word' => '第一个敏感词',
    'all_words' => ['敏感词1', '敏感词2'],
    'count' => 2
]

// 未发现敏感词
[
    'has_sensitive' => false,
    'word' => '',
    'all_words' => [],
    'count' => 0
]
```

### 3. 新的文章状态标记

**数据库字段使用**:
- `status = 'draft'`: 草稿状态
- `review_status = 'sensitive_word'`: 敏感词触发标记
- `excerpt = "⚠️ 敏感词触发：{$sensitive_word}"`: 显示触发的敏感词
- `published_at = NULL`: 未发布

**日志记录**:
- WARNING级别: `"保存AI生成文章成功（包含敏感词 '{$sensitive_word}'）: {$title} (ID: $article_id)"`

---

## 📋 下一步建议

### 1. UI界面优化

在文章管理页面 `admin/articles-new.php` 中：
- 添加筛选选项：显示 `review_status = 'sensitive_word'` 的文章
- 在文章列表中用特殊样式标记敏感词触发的文章
- 显示触发的敏感词（从 `excerpt` 字段读取）

### 2. 审核流程

创建专门的敏感词审核页面：
- 列出所有 `review_status = 'sensitive_word'` 的文章
- 提供批量审核功能（通过/拒绝）
- 显示完整的敏感词列表（`all_words`）

### 3. 统计功能

添加敏感词命中统计：
- 记录每个敏感词被触发的次数
- 显示最常触发的敏感词
- 帮助优化敏感词库

---

**文档创建时间**: 2026-02-02
**最后更新时间**: 2026-02-02
**系统版本**: GEO网站系统 v1.0
