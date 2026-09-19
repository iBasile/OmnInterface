<?php
require_once __DIR__ . '/bootstrap.php';
$uid = require_login();
$db = Database::get();
$user = current_user();
$stmt = $db->prepare('SELECT * FROM conversations WHERE user_id = ? ORDER BY updated_at DESC');
$stmt->execute([$uid]);
$conversations = $stmt->fetchAll();
$active = (int) ($_GET['conversation'] ?? ($conversations[0]['id'] ?? 0));
$messages = [];
$activeTitle = 'Nouvelle discussion';
$activeModel = (string) ($user['default_model'] ?? '');
foreach ($conversations as $conversation) {
    if ((int) $conversation['id'] === $active) {
        $activeTitle = $conversation['title'];
        $activeModel = (string) ($conversation['model'] ?? '');
        break;
    }
}
if ($active) {
    $stmt = $db->prepare('SELECT * FROM messages WHERE conversation_id = ? AND conversation_id IN (SELECT id FROM conversations WHERE user_id = ?) ORDER BY id');
    $stmt->execute([$active, $uid]);
    $messages = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body class="app-body">
    <aside class="sidebar"><a class="sidebar-brand" href="chat.php"><span class="brand-mark small">◌</span><span>OmnInterface</span></a><button class="new-chat" id="new-chat">＋ <span>Nouvelle discussion</span></button>
        <div class="conversation-list"><?php foreach ($conversations as $conversation): ?><a class="conversation <?= $active === (int)$conversation['id'] ? 'is-active' : '' ?>" href="?conversation=<?= (int)$conversation['id'] ?>"><span class="conversation-dot"></span><?= h($conversation['title']) ?></a><?php endforeach; ?></div>
        <div class="sidebar-bottom"><a href="setup.php">⚙ <span>Configuration</span></a><a href="logout.php">↗ <span>Se déconnecter</span></a></div>
    </aside>
    <main class="chat-shell">
        <header class="topbar"><button class="mobile-menu" id="mobile-menu">☰</button>
            <div>
                <p class="eyebrow">ESPACE PRIVÉ</p>
                <h2><?= h($activeTitle) ?></h2>
            </div>
            <div class="topbar-actions"><label class="model-picker"><span>Modèle</span><select id="model-select"><option value="auto" <?= $activeModel === '' ? 'selected' : '' ?>>auto</option></select></label><div class="connection"><span></span> OmniRoute connecté</div></div>
        </header>
        <section class="messages" id="messages"><?php if (!$messages): ?><div class="empty-state">
                    <div class="empty-icon">✦</div>
                    <h1>Que voulez-vous<br><span>explorer aujourd’hui ?</span></h1>
                    <p>Posez une question à vos modèles via OmniRoute.</p>
                    <div class="suggestions"><button>Résume-moi les possibilités d’OmniRoute</button><button>Aide-moi à structurer une idée</button></div>
                </div><?php else: foreach ($messages as $message): ?><article class="message <?= h($message['role']) ?>">
                        <div class="avatar"><?= $message['role'] === 'user' ? 'Vous' : '✦' ?></div>
                        <div class="message-content"><?= nl2br(h($message['content'])) ?></div>
                    </article><?php endforeach;
                                                endif; ?></section>
        <form class="composer" id="composer"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="conversation_id" value="<?= $active ?>"><textarea name="content" rows="1" placeholder="Décrivez votre besoin ou demandez un fichier…" aria-label="Message"></textarea><button type="submit" aria-label="Envoyer">↑</button>
            <div class="composer-note">Demandez « crée le fichier src/app.js » pour obtenir un fichier téléchargeable. Vérifiez les informations importantes.</div>
        </form>
    </main>
    <script>
        window.omni = {
            csrf: <?= json_encode(csrf_token()) ?>,
            conversation: <?= $active ?>,
            omnirouteUrl: <?= json_encode((string) ($user['omniroute_url'] ?? DEFAULT_OMNIROUTE_URL)) ?>,
            localBridge: false,
            model: <?= json_encode($activeModel !== '' ? $activeModel : 'auto') ?>,
            history: <?= json_encode(array_map(static fn(array $message): array => ['role' => $message['role'], 'content' => $message['content']], $messages), JSON_UNESCAPED_UNICODE) ?>
        };
    </script>
    <script src="assets/app.js?v=20260918-3"></script>
</body>

</html>