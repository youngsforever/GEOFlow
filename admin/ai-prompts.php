<?php
/**
 * 智能GEO内容系统 - 提示词配置
 *
 * @author 姚金刚
 * @version 1.0
 * @date 2025-10-14
 */

define('FEISHU_TREASURE', true);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database_admin.php';
require_once __DIR__ . '/../includes/functions.php';

// 检查管理员登录
require_admin_login();

// 立即释放session锁，允许其他页面并发访问
session_write_close();

$message = '';
$error = '';

// 处理POST请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF验证失败';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_prompt':
                $name = trim($_POST['name'] ?? '');
                $type = 'content';
                $content = trim($_POST['content'] ?? '');
                
                if (empty($name) || empty($content)) {
                    $error = '提示词名称和内容不能为空';
                } else {
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO prompts (name, type, content, created_at, updated_at)
                            VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                        ");
                        
                        if ($stmt->execute([$name, $type, $content])) {
                            $message = '提示词创建成功';
                        } else {
                            $error = '提示词创建失败';
                        }
                    } catch (Exception $e) {
                        $error = '创建失败: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'update_prompt':
                $id = intval($_POST['id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $type = 'content';
                $content = trim($_POST['content'] ?? '');
                
                if ($id <= 0 || empty($name) || empty($content)) {
                    $error = '参数错误或必填字段为空';
                } else {
                    try {
                        $stmt = $db->prepare("
                            UPDATE prompts 
                            SET name = ?, type = ?, content = ?, updated_at = CURRENT_TIMESTAMP
                            WHERE id = ? AND type = 'content'
                        ");
                        
                        if ($stmt->execute([$name, $type, $content, $id])) {
                            $message = '提示词更新成功';
                        } else {
                            $error = '提示词更新失败';
                        }
                    } catch (Exception $e) {
                        $error = '更新失败: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'delete_prompt':
                $id = intval($_POST['id'] ?? 0);
                
                if ($id <= 0) {
                    $error = '无效的提示词ID';
                } else {
                    try {
                        // 检查是否有任务在使用此提示词
                        $stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE prompt_id = ?");
                        $stmt->execute([$id]);
                        $usage_count = $stmt->fetchColumn();
                        
                        if ($usage_count > 0) {
                            $error = "无法删除：有 {$usage_count} 个任务正在使用此提示词";
                        } else {
                            $stmt = $db->prepare("DELETE FROM prompts WHERE id = ? AND type = 'content'");
                            if ($stmt->execute([$id])) {
                                $message = '提示词删除成功';
                            } else {
                                $error = '提示词删除失败';
                            }
                        }
                    } catch (Exception $e) {
                        $error = '删除失败: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// 获取提示词列表
try {
    $prompts = $db->query("
        SELECT p.*, 
               COALESCE(t.task_count, 0) as task_count
        FROM prompts p
        LEFT JOIN (
            SELECT prompt_id, COUNT(*) as task_count 
            FROM tasks 
            WHERE prompt_id IS NOT NULL
            GROUP BY prompt_id
        ) t ON p.id = t.prompt_id
        WHERE p.type = 'content'
        ORDER BY p.created_at DESC
    ")->fetchAll();
} catch (Exception $e) {
    $prompts = [];
    $error = '获取提示词列表失败: ' . $e->getMessage();
}

// 设置页面信息
$page_title = '正文提示词配置';
$page_header = '
<div class="flex items-center justify-between">
    <div class="flex items-center space-x-4">
        <a href="ai-configurator.php" class="text-gray-400 hover:text-gray-600">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">正文提示词配置</h1>
            <p class="mt-1 text-sm text-gray-600">管理任务中心实际使用的正文生成提示词模板</p>
        </div>
    </div>
    <button onclick="showCreatePromptModal()" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
        <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
        添加正文提示词
    </button>
</div>';

require_once __DIR__ . '/includes/header.php';
?>

        <!-- 消息显示 -->
        <?php if (!empty($message)): ?>
            <div class="mb-6 bg-green-50 border border-green-200 rounded-md p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i data-lucide="check-circle" class="h-5 w-5 text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700"><?php echo htmlspecialchars($message); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-md p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i data-lucide="alert-circle" class="h-5 w-5 text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="mb-6 rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
            此页面只管理任务正文使用的 <code>content</code> 类型提示词。
            关键词和描述提示词请到 <a href="ai-special-prompts.php" class="font-medium underline">特殊提示词配置</a> 页面维护。
        </div>

        <!-- 提示词列表 -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">正文提示词列表</h3>
                <p class="mt-1 text-sm text-gray-600">这些提示词会被任务中心的“内容提示词”字段直接引用</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">提示词信息</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">类型</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">使用统计</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创建时间</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($prompts)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    <i data-lucide="message-square" class="w-8 h-8 mx-auto mb-2 text-gray-400"></i>
                                    <p>暂无正文提示词配置</p>
                                    <button onclick="showCreatePromptModal()" class="mt-2 text-green-600 hover:text-green-800">添加第一个正文提示词</button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($prompts as $prompt): ?>
                                <tr>
                                    <td class="px-6 py-4">
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($prompt['name']); ?></div>
                                            <div class="text-sm text-gray-500 max-w-xs truncate"><?php echo htmlspecialchars(substr($prompt['content'], 0, 100)) . (strlen($prompt['content']) > 100 ? '...' : ''); ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            正文提示词
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <div>任务: <?php echo $prompt['task_count']; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('Y-m-d H:i', strtotime($prompt['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                        <button onclick="editPrompt(<?php echo htmlspecialchars(json_encode($prompt)); ?>)" class="text-green-600 hover:text-green-900">编辑</button>
                                        <button onclick="deletePrompt(<?php echo $prompt['id']; ?>, '<?php echo htmlspecialchars($prompt['name']); ?>')" class="text-red-600 hover:text-red-900">删除</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 创建/编辑提示词模态框 -->
        <div id="promptModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
            <div class="relative top-10 mx-auto p-5 border w-11/12 md:w-4/5 lg:w-3/4 shadow-lg rounded-md bg-white">
                <div class="mt-3">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-gray-900" id="promptModalTitle">添加正文提示词</h3>
                        <button onclick="closePromptModal()" class="text-gray-400 hover:text-gray-600">
                            <i data-lucide="x" class="w-6 h-6"></i>
                        </button>
                    </div>

                    <form id="promptForm" method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" id="promptFormAction" value="create_prompt">
                        <input type="hidden" name="id" id="promptId" value="">
                        <input type="hidden" name="type" value="content">

                        <div>
                            <label for="prompt_name" class="block text-sm font-medium text-gray-700">提示词名称 *</label>
                            <input type="text" name="name" id="prompt_name" required
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm"
                                   placeholder="例如：科技文章正文生成">
                        </div>

                        <div>
                            <label for="prompt_content" class="block text-sm font-medium text-gray-700">提示词详情 *</label>
                            <textarea name="content" id="prompt_content" required rows="12"
                                      class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm"
                                      placeholder="请输入提示词内容，可以使用变量..."></textarea>

                            <!-- 变量说明 -->
                            <div class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
                                <h4 class="text-sm font-medium text-blue-800 mb-2">正文提示词可用变量：</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-2 text-xs text-blue-700">
                                    <div><code>{{title}}</code> - 当前文章标题</div>
                                    <div><code>{{keyword}}</code> - 标题绑定关键词</div>
                                    <div><code>{{Knowledge}}</code> - 任务检索出的知识片段</div>
                                </div>
                                <p class="mt-2 text-xs text-blue-600">支持条件块：<code>{{#if Knowledge}}...{{/if}}</code>。系统会按标题和关键词先召回相关知识片段，再渲染 <code>{{Knowledge}}</code>；正文生成阶段没有 <code>{{content}}</code> 可供替换。</p>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4">
                            <button type="button" onclick="closePromptModal()"
                                    class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                取消
                            </button>
                            <button type="submit"
                                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                                保存
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <script>
        // 显示创建提示词模态框
        function showCreatePromptModal() {
            document.getElementById('promptModalTitle').textContent = '添加正文提示词';
            document.getElementById('promptFormAction').value = 'create_prompt';
            document.getElementById('promptId').value = '';
            document.getElementById('promptForm').reset();
            document.getElementById('promptModal').classList.remove('hidden');
        }

        // 编辑提示词
        function editPrompt(prompt) {
            document.getElementById('promptModalTitle').textContent = '编辑正文提示词';
            document.getElementById('promptFormAction').value = 'update_prompt';
            document.getElementById('promptId').value = prompt.id;
            document.getElementById('prompt_name').value = prompt.name;
            document.getElementById('prompt_content').value = prompt.content;
            document.getElementById('promptModal').classList.remove('hidden');
        }

        // 关闭模态框
        function closePromptModal() {
            document.getElementById('promptModal').classList.add('hidden');
        }

        // 删除提示词
        function deletePrompt(id, name) {
            if (confirm(`确定要删除提示词"${name}"吗？此操作不可恢复。`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="delete_prompt">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // 初始化
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
