<?php
/**
 * API Token 管理
 */

define('FEISHU_TREASURE', true);
session_start();

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database_admin.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/api_token_service.php';

require_super_admin();
session_write_close();

$message = '';
$error = '';
$newToken = '';
$tokenService = new ApiTokenService($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'CSRF验证失败';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            if ($action === 'create_token') {
                $name = trim((string) ($_POST['name'] ?? ''));
                $scopes = $_POST['scopes'] ?? [];
                $expiresAt = trim((string) ($_POST['expires_at'] ?? ''));
                $created = $tokenService->createToken(
                    $name,
                    is_array($scopes) ? $scopes : [],
                    isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : null,
                    $expiresAt !== '' ? $expiresAt : null
                );
                $newToken = $created['token'];
                $message = 'Token 创建成功，请立即复制保存。';
            } elseif ($action === 'revoke_token') {
                $tokenId = (int) ($_POST['token_id'] ?? 0);
                $tokenService->revokeToken($tokenId);
                $message = 'Token 已撤销';
            }
        } catch (ApiException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            $error = '操作失败: ' . $e->getMessage();
        }
    }
}

$tokens = $tokenService->listTokens();
$availableScopes = $tokenService->getAvailableScopes();

$page_title = 'API Token 管理';
$page_header = '
<div class="flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">API Token 管理</h1>
        <p class="mt-1 text-sm text-gray-600">为 CLI 和后续 skill 生成机器访问凭证。</p>
    </div>
</div>
';

require_once __DIR__ . '/includes/header.php';
?>

<div class="space-y-8">
    <?php if ($newToken !== ''): ?>
        <div class="bg-amber-50 border border-amber-300 rounded-lg px-4 py-4">
            <div class="text-sm font-medium text-amber-900">新 Token 只会显示这一次</div>
            <div class="mt-3 flex items-center gap-3">
                <code class="flex-1 bg-white border border-amber-200 rounded px-3 py-2 text-sm break-all"><?php echo htmlspecialchars($newToken); ?></code>
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">创建 Token</h3>
        </div>
        <div class="px-6 py-4">
            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                <input type="hidden" name="action" value="create_token">

                <div>
                    <label class="block text-sm font-medium text-gray-700">名称 *</label>
                    <input type="text" name="name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="例如：本地 CLI Token">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">过期时间</label>
                    <input type="datetime-local" name="expires_at" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                    <p class="mt-1 text-xs text-gray-500">留空表示长期有效。</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">Scopes *</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <?php foreach ($availableScopes as $scope): ?>
                            <label class="flex items-center gap-2 rounded border border-gray-200 px-3 py-2 text-sm text-gray-700">
                                <input type="checkbox" name="scopes[]" value="<?php echo htmlspecialchars($scope); ?>" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span><?php echo htmlspecialchars($scope); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                        <i data-lucide="plus" class="w-4 h-4 mr-2"></i>
                        创建 Token
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">现有 Tokens</h3>
        </div>

        <?php if (empty($tokens)): ?>
            <div class="px-6 py-8 text-center text-gray-500">暂无 Token</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">名称</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Scopes</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">创建人</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">最近使用</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">过期时间</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">状态</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">操作</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($tokens as $token): ?>
                            <tr>
                                <td class="px-6 py-4 text-sm text-gray-900"><?php echo htmlspecialchars($token['name']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars(implode(', ', $token['scopes'] ?? [])); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($token['created_by_username'] ?: '系统'); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($token['last_used_at'] ?: '未使用'); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($token['expires_at'] ?: '长期有效'); ?></td>
                                <td class="px-6 py-4 text-sm">
                                    <?php if (($token['status'] ?? 'active') === 'active'): ?>
                                        <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">active</span>
                                    <?php else: ?>
                                        <span class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700">revoked</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <?php if (($token['status'] ?? 'active') === 'active'): ?>
                                        <form method="POST" onsubmit="return confirm('确认撤销这个 Token 吗？');">
                                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                            <input type="hidden" name="action" value="revoke_token">
                                            <input type="hidden" name="token_id" value="<?php echo (int) $token['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800">撤销</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-gray-400">已撤销</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
