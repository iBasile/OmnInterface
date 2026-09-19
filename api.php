<?php
require_once __DIR__ . '/bootstrap.php';
$uid = require_login_api();
$action = $_GET['action'] ?? '';
$db = Database::get();
if (!csrf_check($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null))) json_response(['error' => 'Jeton CSRF invalide.'], 403);
if ($action === 'new') {
    $db->prepare('INSERT INTO conversations (user_id) VALUES (?)')->execute([$uid]);
    json_response(['id' => (int)$db->lastInsertId()]);
}
if ($action === 'save') {
    $body = json_body();
    $conversationId = (int) ($body['conversation_id'] ?? 0);
    $userContent = trim((string) ($body['user_content'] ?? ''));
    $assistantContent = trim((string) ($body['assistant_content'] ?? ''));
    $check = $db->prepare('SELECT id FROM conversations WHERE id = ? AND user_id = ?');
    $check->execute([$conversationId, $uid]);
    if (!$check->fetch() || $userContent === '' || $assistantContent === '') {
        json_response(['error' => 'Données de discussion invalides.'], 422);
    }
    $db->beginTransaction();
    try {
        $db->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, 'user', ?)")->execute([$conversationId, $userContent]);
        $db->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, 'assistant', ?)")->execute([$conversationId, $assistantContent]);
        $db->prepare('UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$conversationId]);
        $db->commit();
    } catch (Throwable $exception) {
        $db->rollBack();
        throw $exception;
    }
    json_response(['conversation_id' => $conversationId, 'reply' => $assistantContent]);
}
if ($action === 'send') {
    $body = json_body();
    $content = trim((string)($body['content'] ?? ''));
    $conversationId = (int)($body['conversation_id'] ?? 0);
    if ($content === '') json_response(['error' => 'Message vide.'], 422);
    if ($conversationId === 0) {
        $db->prepare('INSERT INTO conversations (user_id, title) VALUES (?, ?)')->execute([$uid, mb_substr($content, 0, 48)]);
        $conversationId = (int)$db->lastInsertId();
    }
    $check = $db->prepare('SELECT id, model FROM conversations WHERE id = ? AND user_id = ?');
    $check->execute([$conversationId, $uid]);
    $conversation = $check->fetch();
    if (!$conversation) json_response(['error' => 'Discussion introuvable.'], 404);
    $db->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, 'user', ?)")->execute([$conversationId, $content]);
    $stmt = $db->prepare('SELECT role, content FROM messages WHERE conversation_id = ? ORDER BY id');
    $stmt->execute([$conversationId]);
    $history = $stmt->fetchAll();
    $account = current_user();
    $apiKey = decrypt_secret($account['omniroute_api_key'] ?? null);
    if ($apiKey === null || $apiKey === '') {
        json_response(['error' => 'Clé API OmniRoute non configurée. Ouvrez Configuration.'], 409);
    }
    try {
        $reply = (new OmniRouteClient((string)$account['omniroute_url'], decrypt_secret($account['omniroute_api_key'] ?? null)))->chat($history, $conversation['model']);
    } catch (Throwable $e) {
        json_response(['error' => $e->getMessage()], 502);
    }
    $db->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, 'assistant', ?)")->execute([$conversationId, $reply]);
    $db->prepare('UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$conversationId]);
    json_response(['conversation_id' => $conversationId, 'reply' => $reply]);
}
json_response(['error' => 'Action inconnue.'], 404);
