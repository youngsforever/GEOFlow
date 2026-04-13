<?php
define('FEISHU_TREASURE', true);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database_admin.php';
require_once __DIR__ . '/../includes/functions.php';
require_once 'includes/url-import-helpers.php';

if (!is_admin_logged_in() || !get_current_admin(true)) {
    json_response(['success' => false, 'message' => '未登录或登录已过期'], 401);
}

session_write_close();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => '无效的请求方法'], 405);
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    json_response(['success' => false, 'message' => 'CSRF验证失败'], 400);
}

log_admin_request_if_needed([
    'action' => 'url_import_start',
    'page' => 'url-import-start.php',
    'target_type' => 'url_import_job',
    'details' => sanitize_admin_activity_payload($_POST)
]);

$url = trim($_POST['url'] ?? '');
$validation_error = validate_import_url($url);
if ($validation_error !== null) {
    json_response(['success' => false, 'message' => $validation_error], 422);
}

$normalized_url = normalize_import_url($url);
$source_domain = strtolower(parse_url($normalized_url, PHP_URL_HOST) ?? '');

$options = [
    'project_name' => trim($_POST['project_name'] ?? ''),
    'source_label' => trim($_POST['source_label'] ?? ''),
    'content_language' => trim($_POST['content_language'] ?? 'zh-CN'),
    'notes' => trim($_POST['notes'] ?? ''),
    'target_knowledge_base_id' => (int) ($_POST['target_knowledge_base_id'] ?? 0),
    'target_keyword_library_id' => (int) ($_POST['target_keyword_library_id'] ?? 0),
    'target_title_library_id' => (int) ($_POST['target_title_library_id'] ?? 0),
    'target_image_library_id' => (int) ($_POST['target_image_library_id'] ?? 0),
    'target_author_id' => (int) ($_POST['target_author_id'] ?? 0),
    'import_knowledge' => !empty($_POST['import_knowledge']),
    'import_keywords' => !empty($_POST['import_keywords']),
    'import_titles' => !empty($_POST['import_titles']),
    'import_images' => !empty($_POST['import_images']),
    'enable_ai_cleaning' => !empty($_POST['enable_ai_cleaning']),
    'enable_semantic_analysis' => !empty($_POST['enable_semantic_analysis']),
    'capture_body_images' => !empty($_POST['capture_body_images']),
    'allow_duplicate_import' => !empty($_POST['allow_duplicate_import']),
];

$stmt = $db->prepare("
    INSERT INTO url_import_jobs (
        url, normalized_url, source_domain, page_title, status, current_step, progress_percent,
        options_json, result_json, error_message, created_by, started_at, created_at, updated_at
    ) VALUES (?, ?, ?, ?, 'running', 'queued', 2, ?, '', '', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
");

$page_title = preg_replace('#^www\.#', '', $source_domain);
$page_title = $page_title !== '' ? $page_title . ' 页面内容' : 'URL 智能采集任务';

$stmt->execute([
    $url,
    $normalized_url,
    $source_domain,
    $page_title,
    json_encode($options, JSON_UNESCAPED_UNICODE),
    $_SESSION['admin_username'] ?? 'admin'
]);

$job_id = db_last_insert_id($db, 'url_import_jobs');

add_url_import_log($db, $job_id, '已创建采集任务，等待智能处理启动', 'info');
add_url_import_log($db, $job_id, '目标地址：' . $normalized_url, 'info');
run_url_import_pipeline($db, $job_id);

json_response([
    'success' => true,
    'message' => 'URL 智能采集任务已启动',
    'job_id' => $job_id
]);
