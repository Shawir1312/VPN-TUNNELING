<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/Auth.php';

$user = Auth::user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$theme = $input['theme'] ?? 'dark';

if (in_array($theme, ['light', 'dark', 'system'], true)) {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE admin_users SET theme_preference = ? WHERE id = ?");
        $stmt->execute([$theme, $user['id']]);
        $_SESSION['admin_user']['theme_preference'] = $theme;
        echo json_encode(['success' => true]);
        exit;
    } catch (Exception $e) {
        // Fallback
    }
}

echo json_encode(['success' => false]);

