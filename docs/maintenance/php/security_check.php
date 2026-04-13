<?php
/**
 * 安全检查脚本
 * 
 * @author 姚金刚
 * @version 1.0
 * @date 2025-10-05
 */

$projectRoot = dirname(__DIR__, 3);
chdir($projectRoot);

define('FEISHU_TREASURE', true);
require_once $projectRoot . '/includes/config.php';

echo "=== GEO系统安全检查 ===\n\n";

// 1. 检查数据库文件位置
echo "1. 数据库安全检查:\n";
if (file_exists(DB_PATH)) {
    echo "   ✓ 数据库文件存在: " . DB_PATH . "\n";
    
    // 检查数据库文件权限
    $perms = fileperms(DB_PATH);
    $octal_perms = substr(sprintf('%o', $perms), -4);
    echo "   ✓ 数据库文件权限: " . $octal_perms . "\n";
    
    if ($octal_perms > '0644') {
        echo "   ⚠ 建议将数据库文件权限设置为644\n";
    }
} else {
    echo "   ✗ 数据库文件不存在: " . DB_PATH . "\n";
}

// 2. 检查敏感目录保护
echo "\n2. 目录保护检查:\n";
$protected_dirs = ['data', 'data/backups', 'includes', 'logs'];
foreach ($protected_dirs as $dir) {
    $htaccess_file = $dir . '/.htaccess';
    if (file_exists($htaccess_file)) {
        echo "   ✓ {$dir} 目录已保护\n";
    } else {
        echo "   ⚠ {$dir} 目录缺少.htaccess保护\n";
    }
}

// 3. 检查配置文件安全
echo "\n3. 配置安全检查:\n";
if (defined('ADMIN_PASSWORD') && strlen(ADMIN_PASSWORD) > 32) {
    echo "   ✓ 管理员密码使用安全哈希\n";
} else {
    echo "   ⚠ 管理员密码可能不够安全\n";
}

if (defined('SECRET_KEY') && SECRET_KEY !== 'your-secret-key-change-this-in-production') {
    echo "   ✓ 密钥已自定义\n";
} else {
    echo "   ⚠ 请修改默认密钥\n";
}

// 4. 检查文件权限
echo "\n4. 文件权限检查:\n";
$sensitive_files = [
    'includes/config.php',
    'includes/database.php',
    'includes/security.php'
];

foreach ($sensitive_files as $file) {
    if (file_exists($file)) {
        $perms = fileperms($file);
        $octal_perms = substr(sprintf('%o', $perms), -4);
        echo "   ✓ {$file}: {$octal_perms}\n";
        
        if ($octal_perms > '0644') {
            echo "     ⚠ 建议权限设置为644\n";
        }
    }
}

// 5. 检查PHP配置
echo "\n5. PHP安全配置检查:\n";
$php_settings = [
    'display_errors' => 'Off',
    'expose_php' => 'Off',
    'allow_url_fopen' => 'Off',
    'allow_url_include' => 'Off'
];

foreach ($php_settings as $setting => $recommended) {
    $current = ini_get($setting);
    $status = ($current == $recommended || 
              ($recommended == 'Off' && !$current)) ? '✓' : '⚠';
    echo "   {$status} {$setting}: {$current} (推荐: {$recommended})\n";
}

// 6. 检查目录结构
echo "\n6. 目录结构检查:\n";
$required_dirs = ['data/db', 'data/backups', 'logs', 'assets/images'];
foreach ($required_dirs as $dir) {
    if (is_dir($dir)) {
        echo "   ✓ {$dir} 目录存在\n";
    } else {
        echo "   ⚠ {$dir} 目录不存在\n";
    }
}

// 7. 生成安全建议
echo "\n=== 安全建议 ===\n";
echo "1. 定期备份数据库文件\n";
echo "2. 使用HTTPS协议\n";
echo "3. 定期更新密码\n";
echo "4. 监控访问日志\n";
echo "5. 启用防火墙\n";
echo "6. 定期检查文件完整性\n";
echo "7. 使用强密码策略\n";
echo "8. 限制管理后台访问IP\n";

echo "\n检查完成！\n";
?>
