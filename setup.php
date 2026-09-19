<?php
require_once __DIR__ . '/bootstrap.php';
$uid = require_login();
$user = current_user();
$error = null;
$success = null;
$models = [];
$apiKey = decrypt_secret($user['omniroute_api_key'] ?? null);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf_token'] ?? null)) {
    if (($_POST['action'] ?? '') === 'delete-account') {
        if (trim((string) ($_POST['confirmation'] ?? '')) !== 'SUPPRIMER') {
            $error = 'Saisissez SUPPRIMER pour confirmer la suppression.';
        } else {
            Database::get()->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            $_SESSION = [];
            session_destroy();
            redirect('index.php');
        }
    } else {
        $url = trim((string) ($_POST['omniroute_url'] ?? ''));
        $apiKey = trim((string) ($_POST['omniroute_api_key'] ?? ''));
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) $error = 'Saisissez une URL HTTP ou HTTPS valide.';
        elseif ($apiKey === '' && empty($user['omniroute_api_key'])) $error = 'Saisissez la clé API OmniRoute.';
        else {
            $encryptedKey = $apiKey === '' ? $user['omniroute_api_key'] : encrypt_secret($apiKey);
            $model = trim((string) ($_POST['default_model'] ?? ''));
            Database::get()->prepare('UPDATE users SET omniroute_url = ?, omniroute_api_key = ?, default_model = ? WHERE id = ?')->execute([$url, $encryptedKey, $model !== '' ? $model : null, $uid]);
            $user = current_user();
            $apiKey = decrypt_secret($user['omniroute_api_key'] ?? null);
            $success = 'Configuration enregistrée.';
        }
    }
}
$modelsError = null;
if ($apiKey) {
    try {
        $models = (new OmniRouteClient((string) $user['omniroute_url'], $apiKey))->listModels();
    } catch (Throwable $e) {
        $modelsError = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Réglages — <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body class="auth-body">
    <main class="auth-card">
        <div class="auth-brand">
            <div class="brand-mark">⚙️</div>
            <h1>Réglages<br><span>OmniRoute</span></h1>
            <p class="auth-subtitle">Connexion, modèles et données de votre compte.</p>
        </div><?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?><?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
        <form method="post" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <label class="field"><span class="field-label">Adresse du serveur OmniRoute</span><input type="url" name="omniroute_url" required value="<?= h($user['omniroute_url'] ?? DEFAULT_OMNIROUTE_URL) ?>"><span class="hint">Exemple : http://localhost:20128/v1</span></label>
            <label class="field"><span class="field-label">Clé API OmniRoute</span><input type="password" name="omniroute_api_key" autocomplete="off" placeholder="<?= $apiKey ? 'Clé enregistrée — laisser vide pour la conserver' : 'sk-…' ?>"></label>
            <label class="field"><span class="field-label">Modèle par défaut</span><select name="default_model"><option value="">auto (routage intelligent)</option><?php foreach ($models as $model): ?><option value="<?= h($model) ?>" <?= ($user['default_model'] ?? '') === $model ? 'selected' : '' ?>><?= h($model) ?></option><?php endforeach; ?></select></label>
            <?php if ($modelsError): ?><div class="alert alert-error">Impossible de récupérer les modèles : <?= h($modelsError) ?></div><?php elseif ($apiKey && !$models): ?><p class="hint">Aucun modèle n’est actuellement publié par OmniRoute.</p><?php endif; ?>
            <button class="btn btn-primary btn-block">Enregistrer les réglages <span>→</span></button>
        </form>
        <section class="danger-zone"><h2>Zone de danger</h2><p>La suppression efface définitivement votre compte, vos discussions et les fichiers associés.</p><form method="post" onsubmit="return confirm('Cette action est définitive. Continuer ?')"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="action" value="delete-account"><label class="field"><span class="field-label">Tapez SUPPRIMER pour confirmer</span><input name="confirmation" required autocomplete="off"></label><button class="btn btn-danger btn-block">Supprimer mon compte</button></form></section>
        <p class="auth-switch"><a href="chat.php">← Retour à la conversation</a></p>
    </main>
</body>
</html>