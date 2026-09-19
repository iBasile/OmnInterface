<?php
require_once __DIR__ . '/bootstrap.php';
$email = $_SESSION['verified_email'] ?? null;
if (!$email) redirect('register.php');
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) $error = 'Session invalide.';
    else {
        $db = Database::get();
        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO users (email, cgu_accepted_at) VALUES (?, CURRENT_TIMESTAMP)')->execute([$email]);
            $uid = (int) $db->lastInsertId();
            $credential = trim((string) ($_POST['credential_id'] ?? ''));
            if ($credential === '') throw new RuntimeException('Clé d’accès manquante.');
            $db->prepare('INSERT INTO credentials (user_id, credential_id, public_key, label) VALUES (?, ?, ?, ?)')->execute([$uid, $credential, 'webauthn-pending', 'Clé principale']);
            $db->commit();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $uid;
            unset($_SESSION['verified_email']);
            redirect('setup.php');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $error = 'Impossible de créer la clé d’accès. Veuillez réessayer.';
        }
    }
}
?>
<!doctype html>
<html lang="fr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Créer votre passkey — <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body class="auth-body">
    <main class="auth-card">
        <div class="auth-brand">
            <div class="brand-mark">🔐</div>
            <h1>Créer votre <strong>clé d'accès.</strong></span></h1>
            <p class="auth-subtitle">Créez une clé d'accès protège votre compte grâce à Touch ID, Face ID ou le code d'accès de votre appareil. Vous n'avez pas de mot de passe à créer pour OmniInterface</p>
        </div>
        <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?><form method="post" class="auth-form" id="passkey-form"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="credential_id" id="credential_id"><button type="button" class="btn btn-primary btn-block" id="create-passkey">Créer ma clé d'accès. <span>→</span></button><noscript>
                <p class="muted">JavaScript est requis pour créer une passkey.</p>
            </noscript></form>
    </main>
    <script src="assets/app.js"></script>
</body>

</html>