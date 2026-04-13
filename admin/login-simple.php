<?php
/**
 * 简化版管理员登录页面
 */

define('FEISHU_TREASURE', true);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database_admin.php';

// 简单的CSRF函数
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 检查是否已登录
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    admin_redirect('dashboard.php');
}

$error = '';
$success = '';
$admin_site_name = function_exists('get_setting') ? get_setting('site_title', SITE_NAME) : SITE_NAME;

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // 验证CSRF令牌
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid request';
    } elseif (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        // 验证登录
        $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];

            // 更新最后登录时间
            try {
                $stmt = $db->prepare("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$admin['id']]);
            } catch (Exception $e) {
                // 忽略错误
            }

            $success = '登录成功，正在跳转...';
            header('refresh:1;url=' . admin_url('dashboard.php'));
        } else {
            $error = '用户名或密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - <?php echo htmlspecialchars($admin_site_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            /* 防止页面滑动和布局偏移 */
            overflow: hidden;
            position: relative;
        }
        
        /* 禁用所有过渡动画，防止滑动效果 */
        *, *::before, *::after {
            transition: none !important;
            animation: none !important;
        }
        
        /* 确保容器稳定 */
        .login-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            max-width: 28rem;
            padding: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- 登录卡片 -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <!-- Logo和标题 -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 mb-2">管理员登录</h1>
                <p class="text-gray-600"><?php echo htmlspecialchars($admin_site_name); ?> 后台管理系统</p>
            </div>

            <!-- 错误提示 -->
            <?php if (!empty($error)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="text-red-700"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 成功提示 -->
            <?php if (!empty($success)): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-green-700"><?php echo htmlspecialchars($success); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 登录表单 -->
            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                        用户名
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="请输入用户名"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                        密码
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="请输入密码"
                    >
                </div>

                <button 
                    type="submit" 
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-lg focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    登录
                </button>
            </form>

        </div>

        <!-- 返回首页链接 -->
        <div class="text-center mt-6">
            <a href="../" class="text-white hover:text-gray-200">
                ← 返回首页
            </a>
        </div>
    </div>

    <script>
        // 等待页面完全加载后再执行
        document.addEventListener('DOMContentLoaded', function() {
            // 延迟聚焦，避免页面加载时的滑动
            setTimeout(function() {
                const usernameInput = document.getElementById('username');
                if (usernameInput && !usernameInput.value) {
                    usernameInput.focus();
                }
            }, 200);
            
            // 表单提交时显示加载状态
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const button = this.querySelector('button[type="submit"]');
                    if (button) {
                        button.innerHTML = '登录中...';
                        button.disabled = true;
                    }
                });
            }
        });
    </script>
</body>
</html>
