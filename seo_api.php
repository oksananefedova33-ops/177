<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/SeoManager.php';

$db = dirname(__DIR__, 2) . '/data/zerro_blog.db';
$pdo = new PDO('sqlite:' . $db);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$seo = new SeoManager($pdo);
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'getSettings':
        $stmt = $pdo->query("SELECT key, value FROM seo_settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
        echo json_encode(['ok' => true, 'settings' => $settings]);
        break;
        
    case 'saveSettings':
        $settings = json_decode(file_get_contents('php://input'), true) ?? [];
        $seo->saveSettings($settings);
        echo json_encode(['ok' => true]);
        break;
        
    case 'uploadImage':
        uploadImage();
        break;
        
    case 'deleteImage':
        deleteImage();
        break;
        
    case 'regenerateBuildVersion':
        $version = date('YmdHis');
        $cacheFile = dirname(__DIR__, 2) . '/data/.build_version';
        file_put_contents($cacheFile, $version);
        echo json_encode(['ok' => true, 'version' => $version]);
        break;
        
    default:
        echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}

function uploadImage() {
    if (empty($_FILES['file']) || empty($_FILES['file']['tmp_name'])) {
        echo json_encode(['ok' => false, 'error' => 'Файл не загружен']);
        return;
    }
    
    $type = $_POST['type'] ?? 'default';
    $uploadDir = dirname(__DIR__, 2) . '/editor/uploads/seo/';
    
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0775, true);
        @chmod($uploadDir, 0775);
    }
    
    // Проверка типа файла
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/x-icon'];
    $fileType = $_FILES['file']['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode(['ok' => false, 'error' => 'Недопустимый формат файла']);
        return;
    }
    
    // Проверка размера (макс 5MB)
    if ($_FILES['file']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['ok' => false, 'error' => 'Файл слишком большой (макс 5MB)']);
        return;
    }
    
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $newName = $type . '_' . time() . '.' . $ext;
    $destPath = $uploadDir . $newName;
    
    if (!@move_uploaded_file($_FILES['file']['tmp_name'], $destPath)) {
        echo json_encode(['ok' => false, 'error' => 'Не удалось сохранить файл']);
        return;
    }
    
    @chmod($destPath, 0664);
    
    $url = '/editor/uploads/seo/' . $newName;
    
    // Сохраняем в настройки
    global $pdo;
    $db = dirname(__DIR__, 2) . '/data/zerro_blog.db';
    $pdo = new PDO('sqlite:' . $db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $fieldMap = [
        'default_image' => 'default_image',
        'organization_logo' => 'organization_logo',
        'favicon' => 'favicon'
    ];
    
    $fieldName = $fieldMap[$type] ?? $type;
    
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO seo_settings (key, value) VALUES (?, ?)");
    $stmt->execute([$fieldName, $url]);
    
    echo json_encode(['ok' => true, 'url' => $url, 'filename' => $newName]);
}

function deleteImage() {
    $type = $_POST['type'] ?? '';
    
    if (!$type) {
        echo json_encode(['ok' => false, 'error' => 'Тип не указан']);
        return;
    }
    
    global $pdo;
    $db = dirname(__DIR__, 2) . '/data/zerro_blog.db';
    $pdo = new PDO('sqlite:' . $db);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Получаем текущий URL файла
    $stmt = $pdo->prepare("SELECT value FROM seo_settings WHERE key = ?");
    $stmt->execute([$type]);
    $url = $stmt->fetchColumn();
    
    if ($url) {
        // Удаляем физический файл
        $filePath = dirname(__DIR__, 2) . $url;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
        
        // Удаляем из базы
        $stmt = $pdo->prepare("DELETE FROM seo_settings WHERE key = ?");
        $stmt->execute([$type]);
    }
    
    echo json_encode(['ok' => true]);
}