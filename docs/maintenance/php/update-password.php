<?php
/**
 * 更新管理员密码脚本
 */

$projectRoot = dirname(__DIR__, 3);
chdir($projectRoot);

define('FEISHU_TREASURE', true);

require_once $projectRoot . '/includes/config.php';
require_once $projectRoot . '/includes/database.php';

echo "开始更新管理员密码...\n";

try {
    // 获取数据库实例
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    // 生成新密码的哈希
    $new_password = 'admin';
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    // 更新管理员密码
    $stmt = $db->prepare("UPDATE admins SET password = ? WHERE username = 'admin'");
    $result = $stmt->execute([$password_hash]);
    
    if ($result) {
        echo "管理员密码已成功更新为: {$new_password}\n";
        
        // 验证更新
        $stmt = $db->prepare("SELECT username, password FROM admins WHERE username = 'admin'");
        $stmt->execute();
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($new_password, $admin['password'])) {
            echo "密码验证成功！\n";
        } else {
            echo "密码验证失败！\n";
        }
    } else {
        echo "密码更新失败！\n";
    }
    
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
?>
