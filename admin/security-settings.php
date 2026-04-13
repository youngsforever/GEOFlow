<?php
/**
 * 智能GEO内容系统 - 安全管理
 */

define('FEISHU_TREASURE', true);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database_admin.php';

// 检查管理员登录
require_admin_login();

// 立即释放session锁，允许其他页面并发访问
session_write_close();

// 设置页面标题
$page_title = '安全管理';

$message = '';
$error = '';
$force_relogin = false;

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF验证失败';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'add_sensitive_words':
                $words = trim($_POST['words'] ?? '');

                if (empty($words)) {
                    $error = '敏感词不能为空';
                } else {
                    try {
                        // 按行分割敏感词
                        $word_list = array_filter(array_map('trim', explode("\n", $words)));
                        $added_count = 0;

                        foreach ($word_list as $word) {
                            if (!empty($word)) {
                                // 检查是否已存在
                                $check_stmt = $db->prepare("SELECT id FROM sensitive_words WHERE word = ?");
                                $check_stmt->execute([$word]);

                                if (!$check_stmt->fetch()) {
                                    $stmt = $db->prepare("
                                        INSERT INTO sensitive_words (word, created_at)
                                        VALUES (?, CURRENT_TIMESTAMP)
                                    ");
                                    if ($stmt->execute([$word])) {
                                        $added_count++;
                                    }
                                }
                            }
                        }

                        $message = "成功添加 {$added_count} 个敏感词";
                    } catch (Exception $e) {
                        $error = '添加失败: ' . $e->getMessage();
                    }
                }
                break;

            case 'delete_sensitive_word':
                $word_id = intval($_POST['word_id'] ?? 0);

                if ($word_id > 0) {
                    try {
                        $stmt = $db->prepare("DELETE FROM sensitive_words WHERE id = ?");
                        if ($stmt->execute([$word_id])) {
                            $message = '敏感词删除成功';
                        } else {
                            $error = '删除失败';
                        }
                    } catch (Exception $e) {
                        $error = '删除失败: ' . $e->getMessage();
                    }
                } else {
                    $error = '无效的敏感词ID';
                }
                break;

            case 'update_admin_password':
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error = '所有密码字段都不能为空';
                } elseif ($new_password !== $confirm_password) {
                    $error = '新密码和确认密码不匹配';
                } elseif (strlen($new_password) < 6) {
                    $error = '新密码长度至少6位';
                } else {
                    try {
                        // 验证当前密码
                        $stmt = $db->prepare("SELECT password FROM admins WHERE id = ?");
                        $stmt->execute([$_SESSION['admin_id']]);
                        $admin = $stmt->fetch();

                        if ($admin && password_verify($current_password, $admin['password'])) {
                            // 更新密码
                            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $db->prepare("UPDATE admins SET password = ? WHERE id = ?");

                            if ($stmt->execute([$new_password_hash, $_SESSION['admin_id']])) {
                                // 记录日志
                                write_log("管理员密码已修改: {$_SESSION['admin_username']}", 'INFO');
                                $force_relogin = true;
                            } else {
                                $error = '密码修改失败';
                            }
                        } else {
                            $error = '当前密码错误';
                        }
                    } catch (Exception $e) {
                        $error = '密码修改失败: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

if ($force_relogin) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    clear_admin_session();
    redirect(admin_url('index.php') . '?password_updated=1');
}

// 获取敏感词列表
try {
    $sensitive_words = $db->query("
        SELECT * FROM sensitive_words
        ORDER BY word ASC
    ")->fetchAll();
} catch (Exception $e) {
    $sensitive_words = [];
}

// 获取管理员信息
try {
    $stmt = $db->prepare("SELECT username, email, role, created_at FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin_info = $stmt->fetch();
    if (!$admin_info) {
        $admin_info = ['username' => 'admin', 'email' => '', 'role' => 'admin', 'created_at' => ''];
    }
} catch (Exception $e) {
    $admin_info = ['username' => 'admin', 'email' => '', 'role' => 'admin', 'created_at' => ''];
}

// 包含统一头部
require_once __DIR__ . '/includes/header.php';
?>

            <!-- 页面标题 -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900">安全管理</h1>
                <p class="mt-1 text-sm text-gray-600">管理敏感词库和系统安全设置</p>
            </div>

            <!-- 消息提示 -->
            <?php if (!empty($message)): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
                    <div class="flex items-center">
                        <i data-lucide="check-circle" class="w-5 h-5 text-green-500 mr-2"></i>
                        <span class="text-green-700"><?php echo htmlspecialchars($message); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <div class="flex items-center">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-red-500 mr-2"></i>
                        <span class="text-red-700"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 安全统计 -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i data-lucide="shield-alert" class="h-8 w-8 text-red-600"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">敏感词总数</dt>
                                    <dd class="text-2xl font-bold text-gray-900"><?php echo count($sensitive_words); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i data-lucide="user-check" class="h-8 w-8 text-green-600"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">当前管理员</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars($admin_info['username']); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i data-lucide="clock" class="h-8 w-8 text-blue-600"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">账户创建</dt>
                                    <dd class="text-sm font-medium text-gray-900">
                                        <?php echo $admin_info['created_at'] ? date('Y-m-d', strtotime($admin_info['created_at'])) : '未知'; ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i data-lucide="shield-check" class="h-8 w-8 text-amber-600"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">当前角色</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo htmlspecialchars(($admin_info['role'] ?? 'admin') === 'super_admin' ? '超级管理员' : '管理员'); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (is_super_admin()): ?>
                <div class="bg-white shadow rounded-lg mb-8">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900">超级管理员入口</h3>
                    </div>
                    <div class="px-6 py-4 flex flex-wrap gap-3">
                        <a href="<?php echo htmlspecialchars(admin_url('admin-users.php')); ?>" class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                            <i data-lucide="users" class="w-4 h-4 mr-2"></i>
                            管理员管理
                        </a>
                        <a href="<?php echo htmlspecialchars(admin_url('admin-activity-logs.php')); ?>" class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium text-gray-700 border border-gray-300 bg-white hover:bg-gray-50">
                            <i data-lucide="clipboard-list" class="w-4 h-4 mr-2"></i>
                            查看管理员操作日志
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 主要内容区域 -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- 敏感词管理 -->
                <div class="space-y-6">
                    <!-- 添加敏感词 -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">添加敏感词</h3>
                        </div>
                        <div class="px-6 py-6">
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="add_sensitive_words">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">敏感词列表</label>
                                    <textarea name="words" rows="8" required
                                              class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                              placeholder="每行一个敏感词，支持批量添加&#10;例如：&#10;违禁词1&#10;违禁词2&#10;违禁词3"></textarea>
                                    <p class="mt-1 text-xs text-gray-500">每行输入一个敏感词，系统会自动去重</p>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700">
                                        <i data-lucide="shield-plus" class="w-4 h-4 mr-2"></i>
                                        添加敏感词
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>



                    <!-- 敏感词列表 -->
                    <?php if (!empty($sensitive_words)): ?>
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">敏感词列表</h3>
                        </div>
                        <div class="px-6 py-6">
                            <div class="max-h-96 overflow-y-auto">
                                <div class="space-y-2">
                                    <?php foreach ($sensitive_words as $word): ?>
                                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded hover:bg-gray-100">
                                            <div class="flex items-center space-x-3">
                                                <span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($word['word']); ?></span>
                                                <span class="text-xs text-gray-500">
                                                    添加于 <?php echo date('Y-m-d', strtotime($word['created_at'])); ?>
                                                </span>
                                            </div>
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="action" value="delete_sensitive_word">
                                                <input type="hidden" name="word_id" value="<?php echo $word['id']; ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <button type="submit" onclick="return confirm('确定要删除这个敏感词吗？')"
                                                        class="text-red-600 hover:text-red-800 transition-colors">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 管理员设置 -->
                <div class="space-y-6">
                    <!-- 修改密码 -->
                    <div class="bg-white shadow rounded-lg">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900">修改管理员密码</h3>
                        </div>
                        <div class="px-6 py-6">
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="action" value="update_admin_password">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">当前密码</label>
                                    <input type="password" name="current_password" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="输入当前密码">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">新密码</label>
                                    <input type="password" name="new_password" required minlength="6"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="输入新密码（至少6位）">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">确认新密码</label>
                                    <input type="password" name="confirm_password" required minlength="6"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="再次输入新密码">
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                        <i data-lucide="key" class="w-4 h-4 mr-2"></i>
                                        修改密码
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- 安全提示 -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i data-lucide="alert-triangle" class="h-5 w-5 text-yellow-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">安全提示</h3>
                                <div class="mt-2 text-sm text-yellow-700">
                                    <ul class="list-disc list-inside space-y-1">
                                        <li>敏感词检测会在文章发布前自动执行</li>
                                        <li>包含敏感词的文章会被自动删除并移入垃圾箱</li>
                                        <li>建议定期更新敏感词库以提高过滤效果</li>
                                        <li>管理员密码建议使用强密码并定期更换</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

<?php
// 包含统一底部
require_once __DIR__ . '/includes/footer.php';
?>
