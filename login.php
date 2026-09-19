<?php
require_once __DIR__ . '/bootstrap.php';

if (current_user_id() !== null) redirect('chat.php');
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) $error = 'Session invalide, veuillez réessayer.';
    else {
        $email = trim((string) ($_POST['email'] ?? ''));
        $stmt = Database::get()->prepare('SELECT u.id FROM users u INNER JOIN credentials c ON c.user_id = u.id WHERE u.email = ? LIMIT 1');
        $stmt->execute([$email]);
        if (!$stmt->fetch()) $error = 'Aucun compte trouvé pour cette adresse.';
        else {
            $_SESSION['pending_login_email'] = $email;
            redirect('passkey-login.php');
        }
    }
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Se connecter — <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body class="auth-body">
    <main class="auth-card">
        <div class="auth-brand">
            <div class="brand-mark">🤖</div>
            <p class="eyebrow">A BARGICLOUD PROJECT.</p>
            <h1>Bienvenue dans<br><span>OmnInterface.</span></h1>
            <p class="auth-subtitle">Discutez avec vos modèles grâce à OmniRoute</p>
        </div>
        <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
        <form method="post" class="auth-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><label class="field"><span class="field-label">Adresse e-mail</span><input type="email" name="email" required autofocus placeholder="vous@exemple.fr"></label><button class="btn btn-primary btn-block">Connexion <span>→</span></button></form>
        <p class="auth-switch">Pas encore de compte ? <a href="register.php">Créer un compte</a></p>
    </main>
</body>

</html>