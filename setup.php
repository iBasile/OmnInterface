<?php
require_once __DIR__ . '/bootstrap.php';
$uid = require_login();
$user = current_user();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf_token'] ?? null)) {
    $url = trim((string) ($_POST['omniroute_url'] ?? ''));
    $apiKey = trim((string) ($_POST['omniroute_api_key'] ?? ''));
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) $error = 'Saisissez une URL HTTP ou HTTPS valide.';
    elseif ($apiKey === '') $error = 'Saisissez la clé API OmniRoute.';
    else {
        Database::get()->prepare('UPDATE users SET omniroute_url = ?, omniroute_api_key = ? WHERE id = ?')->execute([$url, encrypt_secret($apiKey), $uid]);
        redirect('chat.php');
    }
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Lier OmniRoute — <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body class="auth-body">
    <main class="auth-card">
        <div class="auth-brand">
            <div class="brand-mark">⚙️</div>
            <h1>Où vit votre<br><span>OmniRoute ?</span></h1>
            <p class="auth-subtitle">Vous pourrez modifier cette adresse à tout moment.</p>
        </div><?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?><form method="post" class="auth-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><label class="field"><span class="field-label">Adresse du serveur OmniRoute</span><input type="url" name="omniroute_url" required value="<?= h($user['omniroute_url'] ?? DEFAULT_OMNIROUTE_URL) ?>"></label><label class="field"><span class="field-label">Clé API OmniRoute</span><input type="password" name="omniroute_api_key" required autocomplete="off" placeholder="sk-…"></label>
            <button class="btn btn-primary btn-block">Démarer Omninterface <span>→</span></button>
        </form>
    </main>
</body>
</html>