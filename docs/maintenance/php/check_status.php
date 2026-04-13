<?php
/**
 * 智能GEO内容系统 - 状态检查脚本
 * 
 * @author 姚金刚
 * @version 1.0
 */

$projectRoot = dirname(__DIR__, 3);
chdir($projectRoot);

echo "=== 智能GEO内容系统状态检查 ===\n";
echo "时间：" . date('Y-m-d H:i:s') . "\n\n";

// 检查PHP版本
echo "✅ PHP版本：" . PHP_VERSION . "\n";

// 检查必要文件
$required_files = [
    'router.php',
    'includes/config.php',
    'includes/database_admin.php',
    'includes/functions.php',
    'data/db/blog.db'
];

echo "\n📁 文件检查：\n";
foreach ($required_files as $file) {
    if (file_exists($file)) {
        echo "  ✅ {$file}\n";
    } else {
        echo "  ❌ {$file} (缺失)\n";
    }
}

// 检查数据库连接
echo "\n🗄️  数据库检查：\n";
try {
    define('FEISHU_TREASURE', true);
    require_once $projectRoot . '/includes/config.php';
    require_once $projectRoot . '/includes/database_admin.php';
    
    // 测试基本查询
    $stmt = $db->query("SELECT COUNT(*) as count FROM articles");
    $article_count = $stmt->fetch()['count'];
    echo "  ✅ 数据库连接正常\n";
    echo "  📊 文章总数：{$article_count}\n";
    
    // 检查任务状态
    $stmt = $db->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'active'");
    $active_tasks = $stmt->fetch()['count'];
    echo "  📋 活跃任务：{$active_tasks}\n";
    
    // 检查AI模型
    $stmt = $db->query("SELECT COUNT(*) as count FROM ai_models WHERE status = 'active'");
    $active_models = $stmt->fetch()['count'];
    echo "  🤖 可用AI模型：{$active_models}\n";
    
} catch (Exception $e) {
    echo "  ❌ 数据库连接失败：" . $e->getMessage() . "\n";
}

// 检查服务器状态
echo "\n🌐 服务器检查：\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_NOBODY, true);

$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code === 200) {
    echo "  ✅ Web服务器运行正常 (http://localhost:8080)\n";
} else {
    echo "  ❌ Web服务器无法访问 (HTTP {$http_code})\n";
    echo "  💡 请运行：./start.sh 启动服务器\n";
}

// 检查进程
echo "\n🔄 进程检查：\n";
$output = shell_exec('ps aux | grep "php -S localhost:8080" | grep -v grep');
if ($output) {
    echo "  ✅ PHP服务器进程运行中\n";
    echo "  📝 进程信息：" . trim($output) . "\n";
} else {
    echo "  ❌ 未找到PHP服务器进程\n";
}

// 检查批量执行任务
$batch_processes = shell_exec('ps aux | grep "batch_execute_task.php" | grep -v grep');
if ($batch_processes) {
    echo "  🔄 批量执行任务运行中：\n";
    echo "     " . trim($batch_processes) . "\n";
} else {
    echo "  ℹ️  当前无批量执行任务\n";
}

echo "\n=== 检查完成 ===\n";

// 如果是通过Web访问，输出HTML格式
if (isset($_SERVER['HTTP_HOST'])) {
    echo "\n<br><a href='/admin/dashboard.php'>返回管理后台</a>";
}
?>
