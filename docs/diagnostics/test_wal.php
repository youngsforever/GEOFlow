<?php
$projectRoot = dirname(__DIR__, 2);
chdir($projectRoot);

define('FEISHU_TREASURE', true);
require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/includes/database_admin.php';

echo "测试WAL模式启用...\n\n";

// 连接数据库（这会触发WAL模式设置）
$db = Database::getInstance()->getConnection();

// 检查journal_mode
$stmt = $db->query("PRAGMA journal_mode");
$result = $stmt->fetch();
$journal_mode = $result[0] ?? $result['journal_mode'] ?? 'unknown';
echo "Journal Mode: " . $journal_mode . "\n";

// 检查busy_timeout
$stmt = $db->query("PRAGMA busy_timeout");
$result = $stmt->fetch();
$busy_timeout = $result[0] ?? $result['timeout'] ?? 'unknown';
echo "Busy Timeout: " . $busy_timeout . " ms\n";

// 检查synchronous
$stmt = $db->query("PRAGMA synchronous");
$result = $stmt->fetch();
$synchronous = $result[0] ?? $result['synchronous'] ?? 'unknown';
echo "Synchronous: " . $synchronous . "\n";

echo "\n";

if ($journal_mode === 'wal') {
    echo "✅ WAL模式已成功启用！\n";
} else {
    echo "❌ WAL模式未启用 (当前: {$journal_mode})\n";
}
