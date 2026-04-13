<?php
/**
 * 素材库管理共享辅助函数
 */

if (!defined('FEISHU_TREASURE')) {
    die('Access denied');
}

function material_library_root_path(): string {
    return dirname(__DIR__, 2);
}

function material_library_absolute_path(string $relativePath): string {
    $relativePath = ltrim(str_replace('\\', '/', trim($relativePath)), '/');
    $segments = array_filter(explode('/', $relativePath), static fn(string $segment): bool => $segment !== '');
    foreach ($segments as $segment) {
        if ($segment === '.' || $segment === '..') {
            throw new InvalidArgumentException('非法文件路径');
        }
    }

    return material_library_root_path() . '/' . implode('/', $segments);
}

function refresh_keyword_library_count(PDO $db, int $libraryId): void {
    $stmt = $db->prepare("
        UPDATE keyword_libraries
        SET keyword_count = (SELECT COUNT(*) FROM keywords WHERE library_id = ?),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$libraryId, $libraryId]);
}

function refresh_title_library_count(PDO $db, int $libraryId): void {
    $stmt = $db->prepare("
        UPDATE title_libraries
        SET title_count = (SELECT COUNT(*) FROM titles WHERE library_id = ?),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$libraryId, $libraryId]);
}

function refresh_image_library_count(PDO $db, int $libraryId): void {
    $stmt = $db->prepare("
        UPDATE image_libraries
        SET image_count = (SELECT COUNT(*) FROM images WHERE library_id = ?),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$libraryId, $libraryId]);
}

function get_knowledge_base_task_references(PDO $db, int $knowledgeBaseId, int $limit = 5): array {
    $countStmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE knowledge_base_id = ?");
    $countStmt->execute([$knowledgeBaseId]);
    $total = (int) $countStmt->fetchColumn();

    $taskStmt = $db->prepare("
        SELECT id, name, status
        FROM tasks
        WHERE knowledge_base_id = ?
        ORDER BY updated_at DESC, id DESC
        LIMIT ?
    ");
    $taskStmt->bindValue(1, $knowledgeBaseId, PDO::PARAM_INT);
    $taskStmt->bindValue(2, $limit, PDO::PARAM_INT);
    $taskStmt->execute();

    return [
        'count' => $total,
        'tasks' => $taskStmt->fetchAll(PDO::FETCH_ASSOC),
    ];
}

function validate_uploaded_image_file(array $file, int $maxBytes = 10_485_760): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('上传失败，请重新选择图片');
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new InvalidArgumentException('未检测到有效的上传文件');
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        throw new InvalidArgumentException('图片文件为空');
    }

    if ($size > $maxBytes) {
        throw new InvalidArgumentException('图片大小超过限制，单张请控制在 10MB 以内');
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false) {
        throw new InvalidArgumentException('文件内容不是有效图片');
    }

    $detectedMime = (string) ($imageInfo['mime'] ?? '');
    if ($detectedMime === '' && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detectedMime = (string) finfo_file($finfo, $tmpName);
            finfo_close($finfo);
        }
    }

    $allowedMimeMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedMimeMap[$detectedMime])) {
        throw new InvalidArgumentException('仅支持 JPG、PNG、GIF、WEBP 图片');
    }

    return [
        'mime_type' => $detectedMime,
        'extension' => $allowedMimeMap[$detectedMime],
        'width' => (int) ($imageInfo[0] ?? 0),
        'height' => (int) ($imageInfo[1] ?? 0),
        'file_size' => $size,
    ];
}

function store_uploaded_image_file(array $file, ?string $subDirectory = null): array {
    $metadata = validate_uploaded_image_file($file);
    $relativeDirectory = 'uploads/images/' . trim($subDirectory ?? date('Y/m'), '/');
    $absoluteDirectory = material_library_absolute_path($relativeDirectory);

    if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0755, true) && !is_dir($absoluteDirectory)) {
        throw new RuntimeException('创建图片目录失败');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $metadata['extension'];
    $relativePath = $relativeDirectory . '/' . $filename;
    $absolutePath = material_library_absolute_path($relativePath);

    if (!move_uploaded_file((string) $file['tmp_name'], $absolutePath)) {
        throw new RuntimeException('保存上传图片失败');
    }

    @chmod($absolutePath, 0644);

    return $metadata + [
        'filename' => $filename,
        'file_name' => $filename,
        'file_path' => $relativePath,
        'absolute_path' => $absolutePath,
        'original_name' => (string) ($file['name'] ?? $filename),
    ];
}

function delete_material_files(array $relativePaths): array {
    $failed = [];

    foreach (array_unique($relativePaths) as $relativePath) {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '') {
            continue;
        }

        try {
            $absolutePath = material_library_absolute_path($relativePath);
        } catch (InvalidArgumentException $e) {
            $failed[] = $relativePath;
            continue;
        }
        if (!file_exists($absolutePath)) {
            continue;
        }

        if (!@unlink($absolutePath)) {
            $failed[] = $relativePath;
        }
    }

    return $failed;
}
