<?php
require_once __DIR__ . '/bootstrap.php';
$email = $_SESSION['pending_login_email'] ?? null;
if (!$email) redirect('login.php');
$stmt = Database::get()->prepare('SELECT u.id, c.credential_id FROM users u INNER JOIN credentials c ON c.user_id = u.id WHERE u.email = ? LIMIT 1');
$stmt->execute([$email]);
$account = $stmt->fetch();
if (!$account) redirect('login.php');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check($_POST['csrf_token'] ?? null) && hash_equals($account['credential_id'], (string) ($_POST['credential_id'] ?? ''))) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $account['id'];
    unset($_SESSION['pending_login_email']);
    redirect('chat.php');
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Passkey — <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body class="auth-body">
    <main class="auth-card">
        <div class="auth-brand">
            <div class="brand-mark">🔐</div>
            <h1>Confirmez votre<br><span>identité.</span></h1>
            <p class="auth-subtitle"><?= h($email) ?><br>Utilisez votre clé d'accès pour continuer.</p>
        </div>
        <form method="post" id="login-passkey-form" class="auth-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="credential_id" id="credential_id"><button type="button" class="btn btn-primary btn-block" id="use-passkey">Utiliser ma clé d'accès <span>→</span></button></form>
    </main>
    <script src="assets/app.js"></script>
</body>

</html>