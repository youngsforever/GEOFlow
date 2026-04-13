<?php
define('FEISHU_TREASURE', true);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database_admin.php';
require_once __DIR__ . '/../includes/functions.php';

require_admin_login();
session_write_close();

$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;

$where = ['1 = 1'];
$params = [];

if ($status !== '') {
    $where[] = 'j.status = ?';
    $params[] = $status;
}

if ($search !== '') {
    $where[] = '(j.url LIKE ? OR j.source_domain LIKE ? OR j.page_title LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$whereSql = implode(' AND ', $where);
$countStmt = $db->prepare("SELECT COUNT(*) AS total FROM url_import_jobs j WHERE {$whereSql}");
$countStmt->execute($params);
$total = (int) ($countStmt->fetch()['total'] ?? 0);
$totalPages = max(1, (int) ceil($total / $per_page));
$offset = ($page - 1) * $per_page;

$listSql = "
    SELECT j.*,
           (
               SELECT message
               FROM url_import_job_logs l
               WHERE l.job_id = j.id
               ORDER BY l.id DESC
               LIMIT 1
           ) AS latest_log
    FROM url_import_jobs j
    WHERE {$whereSql}
    ORDER BY j.created_at DESC, j.id DESC
    LIMIT {$per_page} OFFSET {$offset}
";
$listStmt = $db->prepare($listSql);
$listStmt->execute($params);
$jobs = $listStmt->fetchAll();

$stats = [
    'total' => (int) ($db->query("SELECT COUNT(*) AS count FROM url_import_jobs")->fetch()['count'] ?? 0),
    'completed' => (int) ($db->query("SELECT COUNT(*) AS count FROM url_import_jobs WHERE status = 'completed'")->fetch()['count'] ?? 0),
    'running' => (int) ($db->query("SELECT COUNT(*) AS count FROM url_import_jobs WHERE status = 'running'")->fetch()['count'] ?? 0),
    'failed' => (int) ($db->query("SELECT COUNT(*) AS count FROM url_import_jobs WHERE status = 'failed'")->fetch()['count'] ?? 0),
];

function url_import_status_meta(string $status): array {
    return match ($status) {
        'completed' => ['label' => '已完成', 'class' => 'bg-emerald-100 text-emerald-800'],
        'running' => ['label' => '处理中', 'class' => 'bg-blue-100 text-blue-800'],
        'failed' => ['label' => '失败', 'class' => 'bg-red-100 text-red-800'],
        default => ['label' => '排队中', 'class' => 'bg-yellow-100 text-yellow-800'],
    };
}

function url_import_import_meta(array $job): array {
    $result = json_decode($job['result_json'] ?? '{}', true);
    $importResult = is_array($result) ? ($result['import_result'] ?? null) : null;
    if (is_array($importResult) && !empty($importResult['imported_at'])) {
        return [
            'label' => '已入库',
            'class' => 'bg-emerald-100 text-emerald-800',
            'summary' => sprintf(
                '知识库%s / 关键词%s / 标题%s / 图片%s',
                !empty($importResult['knowledge_base_id']) ? '#' . $importResult['knowledge_base_id'] : '-',
                (int) ($importResult['inserted_keywords'] ?? 0),
                (int) ($importResult['inserted_titles'] ?? 0),
                (int) ($importResult['inserted_images'] ?? 0)
            ),
        ];
    }

    return [
        'label' => '未入库',
        'class' => 'bg-gray-100 text-gray-700',
        'summary' => '仅完成预览，尚未写入素材库',
    ];
}

$page_title = 'URL采集历史';
$page_header = '
<div class="flex items-center justify-between">
    <div class="flex items-center space-x-4">
        <a href="materials.php" class="text-gray-400 hover:text-gray-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">URL采集历史</h1>
            <p class="mt-1 text-sm text-gray-600">查看 URL 智能采集任务、处理阶段与最近日志。</p>
        </div>
    </div>
    <a href="url-import.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-cyan-600 hover:bg-cyan-700">
        <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
        新建采集任务
    </a>
</div>
';

require_once __DIR__ . '/includes/header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white shadow rounded-lg p-5"><div class="text-sm text-gray-500">总任务</div><div class="mt-2 text-2xl font-semibold text-gray-900"><?php echo $stats['total']; ?></div></div>
    <div class="bg-white shadow rounded-lg p-5"><div class="text-sm text-gray-500">已完成</div><div class="mt-2 text-2xl font-semibold text-emerald-700"><?php echo $stats['completed']; ?></div></div>
    <div class="bg-white shadow rounded-lg p-5"><div class="text-sm text-gray-500">处理中</div><div class="mt-2 text-2xl font-semibold text-blue-700"><?php echo $stats['running']; ?></div></div>
    <div class="bg-white shadow rounded-lg p-5"><div class="text-sm text-gray-500">失败</div><div class="mt-2 text-2xl font-semibold text-red-700"><?php echo $stats['failed']; ?></div></div>
</div>

<div class="bg-white shadow rounded-lg mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">筛选</h3>
    </div>
    <div class="px-6 py-4">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">状态</label>
                <select name="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500 sm:text-sm">
                    <option value="">全部状态</option>
                    <option value="queued" <?php echo $status === 'queued' ? 'selected' : ''; ?>>排队中</option>
                    <option value="running" <?php echo $status === 'running' ? 'selected' : ''; ?>>处理中</option>
                    <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>已完成</option>
                    <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>失败</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">搜索</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="搜索 URL、域名或页面标题" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-cyan-500 focus:border-cyan-500 sm:text-sm">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-cyan-600 hover:bg-cyan-700">筛选</button>
                <a href="url-import-history.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">清空</a>
            </div>
        </form>
    </div>
</div>

<div class="bg-white shadow rounded-lg overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-medium text-gray-900">采集记录</h3>
    </div>
    <?php if (empty($jobs)): ?>
        <div class="px-6 py-10 text-center text-gray-500">暂无采集记录</div>
    <?php else: ?>
        <div class="divide-y divide-gray-200">
            <?php foreach ($jobs as $job): ?>
                <?php $meta = url_import_status_meta((string) $job['status']); ?>
                <?php $importMeta = url_import_import_meta($job); ?>
                <div class="px-6 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $meta['class']; ?>"><?php echo $meta['label']; ?></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?php echo $importMeta['class']; ?>"><?php echo $importMeta['label']; ?></span>
                                <div class="text-sm text-gray-500">#<?php echo (int) $job['id']; ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($job['source_domain'] ?: '未知来源'); ?></div>
                            </div>
                            <div class="mt-3 text-sm font-medium text-gray-900 break-all"><?php echo htmlspecialchars($job['url']); ?></div>
                            <?php if (!empty($job['page_title'])): ?>
                                <div class="mt-1 text-sm text-gray-600"><?php echo htmlspecialchars($job['page_title']); ?></div>
                            <?php endif; ?>
                            <div class="mt-3 grid grid-cols-1 md:grid-cols-4 gap-3 text-sm text-gray-500">
                                <div>当前阶段：<?php echo htmlspecialchars($job['current_step']); ?></div>
                                <div>进度：<?php echo (int) $job['progress_percent']; ?>%</div>
                                <div>创建时间：<?php echo htmlspecialchars($job['created_at']); ?></div>
                                <div>完成时间：<?php echo htmlspecialchars($job['finished_at'] ?: '-'); ?></div>
                            </div>
                            <div class="mt-3 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">入库状态：<?php echo htmlspecialchars($importMeta['summary']); ?></div>
                            <?php if (!empty($job['latest_log'])): ?>
                                <div class="mt-3 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-600">最近日志：<?php echo htmlspecialchars($job['latest_log']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($job['error_message'])): ?>
                                <div class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">错误：<?php echo htmlspecialchars($job['error_message']); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
