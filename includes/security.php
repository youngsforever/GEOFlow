<?php
/**
 * 安全增强模块
 * 
 * @author 姚金刚
 * @version 2.0
 * @date 2025-10-05
 */

// 防止直接访问
if (!defined('FEISHU_TREASURE')) {
    die('Access denied');
}

/**
 * 安全头设置
 */
function set_security_headers() {
    // 防止XSS攻击
    header('X-XSS-Protection: 1; mode=block');
    
    // 防止MIME类型嗅探
    header('X-Content-Type-Options: nosniff');
    
    // 防止点击劫持
    header('X-Frame-Options: SAMEORIGIN');
    
    // 内容安全策略 - 允许必要的外部资源
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https: data:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; font-src 'self' https:;");
    
    // 严格传输安全（仅HTTPS）
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // 推荐策略
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // 权限策略
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

/**
 * 输入验证和清理
 */
function validate_input($data, $type = 'string', $max_length = null) {
    if (empty($data)) {
        return false;
    }
    
    switch ($type) {
        case 'email':
            return filter_var($data, FILTER_VALIDATE_EMAIL);
            
        case 'url':
            return filter_var($data, FILTER_VALIDATE_URL);
            
        case 'int':
            return filter_var($data, FILTER_VALIDATE_INT);
            
        case 'slug':
            return preg_match('/^[a-zA-Z0-9\-_]+$/', $data);
            
        case 'string':
        default:
            $data = trim($data);
            if ($max_length && strlen($data) > $max_length) {
                return false;
            }
            return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

/**
 * SQL注入防护
 */
function safe_query($db, $sql, $params = []) {
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        write_log("SQL Error: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * 文件上传安全检查
 */
function validate_upload($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    // 检查文件大小
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => '文件大小超过限制'];
    }
    
    // 检查MIME类型
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => '不支持的文件类型'];
    }
    
    // 检查文件扩展名
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_extensions)) {
        return ['success' => false, 'message' => '不支持的文件扩展名'];
    }
    
    // 检查文件内容（防止伪造）
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        return ['success' => false, 'message' => '文件内容与扩展名不匹配'];
    }
    
    return ['success' => true];
}

/**
 * 登录尝试限制
 */
function check_login_attempts($ip) {
    $attempts_file = __DIR__ . '/../data/login_attempts.json';
    $max_attempts = 5;
    $lockout_time = 900; // 15分钟
    
    if (!file_exists($attempts_file)) {
        return true;
    }
    
    $attempts = json_decode(file_get_contents($attempts_file), true);
    
    if (isset($attempts[$ip])) {
        $attempt_data = $attempts[$ip];
        
        // 检查是否在锁定期内
        if ($attempt_data['count'] >= $max_attempts) {
            if (time() - $attempt_data['last_attempt'] < $lockout_time) {
                return false;
            } else {
                // 锁定期已过，重置计数
                unset($attempts[$ip]);
                file_put_contents($attempts_file, json_encode($attempts));
            }
        }
    }
    
    return true;
}

/**
 * 记录登录尝试
 */
function record_login_attempt($ip, $success = false) {
    $attempts_file = __DIR__ . '/../data/login_attempts.json';
    
    $attempts = [];
    if (file_exists($attempts_file)) {
        $attempts = json_decode(file_get_contents($attempts_file), true);
    }
    
    if ($success) {
        // 登录成功，清除记录
        unset($attempts[$ip]);
    } else {
        // 登录失败，增加计数
        if (!isset($attempts[$ip])) {
            $attempts[$ip] = ['count' => 0, 'last_attempt' => 0];
        }
        
        $attempts[$ip]['count']++;
        $attempts[$ip]['last_attempt'] = time();
    }
    
    file_put_contents($attempts_file, json_encode($attempts));
}

/**
 * 生成安全的随机字符串
 */
function generate_secure_token($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * 密码强度检查
 */
function check_password_strength($password) {
    $min_length = 8;
    $has_uppercase = preg_match('/[A-Z]/', $password);
    $has_lowercase = preg_match('/[a-z]/', $password);
    $has_number = preg_match('/\d/', $password);
    $has_special = preg_match('/[^A-Za-z0-9]/', $password);
    
    $score = 0;
    $feedback = [];
    
    if (strlen($password) >= $min_length) {
        $score++;
    } else {
        $feedback[] = "密码长度至少{$min_length}位";
    }
    
    if ($has_uppercase) $score++;
    else $feedback[] = "需要包含大写字母";
    
    if ($has_lowercase) $score++;
    else $feedback[] = "需要包含小写字母";
    
    if ($has_number) $score++;
    else $feedback[] = "需要包含数字";
    
    if ($has_special) $score++;
    else $feedback[] = "需要包含特殊字符";
    
    return [
        'score' => $score,
        'strength' => $score >= 4 ? 'strong' : ($score >= 3 ? 'medium' : 'weak'),
        'feedback' => $feedback
    ];
}

/**
 * 数据库备份
 */
function backup_database() {
    $backup_dir = __DIR__ . '/../data/backups/';
    
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    $project_root = dirname(__DIR__);
    $command = 'php ' . escapeshellarg($project_root . '/bin/db_maintenance.php') . ' backup 2>&1';
    exec($command, $output, $exit_code);

    if ($exit_code === 0) {
        $backup_file = '';
        foreach ($output as $line) {
            if (str_starts_with($line, 'backup_created: ')) {
                $backup_file = trim(substr($line, strlen('backup_created: ')));
                break;
            }
        }

        if ($backup_file !== '') {
            write_log("Database backup created: $backup_file", 'INFO');
            cleanup_old_backups($backup_dir, 30);
            return $backup_file;
        }
    }

    write_log('Database backup failed: ' . implode("\n", $output), 'ERROR');
    
    return false;
}

/**
 * 清理旧备份文件
 */
function cleanup_old_backups($backup_dir, $retention_days = 30) {
    $files = array_merge(
        glob($backup_dir . 'backup_*.db') ?: [],
        glob($backup_dir . 'blog_backup_*.db') ?: [],
        glob($backup_dir . 'pg_backup_*.dump') ?: []
    );
    $cutoff_time = time() - ($retention_days * 24 * 60 * 60);
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff_time) {
            unlink($file);
            write_log("Old backup deleted: $file", 'INFO');
        }
    }
}

// 自动设置安全头
if (!headers_sent()) {
    set_security_headers();
}
