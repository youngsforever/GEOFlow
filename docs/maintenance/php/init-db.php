<?php
/**
 * 数据库初始化脚本
 */

$projectRoot = dirname(__DIR__, 3);
chdir($projectRoot);

define('FEISHU_TREASURE', true);

require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/includes/database.php';

echo "开始初始化数据库...\n";

try {
    // 获取数据库实例
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    echo "数据库连接成功！\n";
    
    // 检查表是否存在
    $tables = ['articles', 'categories', 'tags', 'admins', 'settings'];
    
    foreach ($tables as $table) {
        $stmt = $db->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
        $stmt->execute([$table]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            echo "表 {$table} 已存在\n";
        } else {
            echo "表 {$table} 不存在，需要创建\n";
        }
    }
    
    // 检查是否有文章数据
    $stmt = $db->query("SELECT COUNT(*) as count FROM articles");
    $result = $stmt->fetch();
    echo "文章数量: " . $result['count'] . "\n";
    
    // 检查是否有分类数据
    $stmt = $db->query("SELECT COUNT(*) as count FROM categories");
    $result = $stmt->fetch();
    echo "分类数量: " . $result['count'] . "\n";
    
    // 检查管理员账户
    $stmt = $db->query("SELECT COUNT(*) as count FROM admins");
    $result = $stmt->fetch();
    echo "管理员数量: " . $result['count'] . "\n";
    
    if ($result['count'] == 0) {
        echo "创建默认管理员账户...\n";
        $password_hash = password_hash('yaodashuai', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO admins (username, password, email, name, created_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)");
        $stmt->execute(['admin', $password_hash, 'admin@example.com', '系统管理员']);
        echo "管理员账户创建成功！\n";
    }
    
    echo "数据库初始化完成！\n";
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
?>
