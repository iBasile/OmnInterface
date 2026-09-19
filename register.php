<?php
require_once __DIR__ . '/bootstrap.php';

if (current_user_id() !== null) {
    redirect('chat.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $cguAccepted = isset($_POST['cgu']);

    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = 'Session invalide, veuillez réessayer.';
    } elseif (!valid_email($email)) {
        $error = 'Merci de saisir une adresse e-mail valide.';
    } elseif (!$cguAccepted) {
        $error = 'Vous devez accepter les conditions générales d\'utilisation.';
    } else {
        $db = Database::get();

        // Un compte est considéré "complet" seulement s'il possède déjà une clé d'accès.
        $stmt = $db->prepare(
            'SELECT u.id FROM users u
             INNER JOIN credentials c ON c.user_id = u.id
             WHERE u.email = ? LIMIT 1'
        );
        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            $error = 'Un compte existe déjà avec cette adresse. Connectez-vous plutôt.';
        } else {
            $code = str_pad((string) random_int(0, (int) str_repeat('9', VERIFICATION_CODE_LENGTH)), VERIFICATION_CODE_LENGTH, '0', STR_PAD_LEFT);
            $hash = password_hash($code, PASSWORD_DEFAULT);
            $expiresAt = date('Y-m-d H:i:s', time() + VERIFICATION_CODE_TTL);

            $db->prepare("DELETE FROM email_verifications WHERE email = ? AND purpose = 'register'")->execute([$email]);
            $db->prepare("INSERT INTO email_verifications (email, code_hash, purpose, expires_at) VALUES (?, ?, 'register', ?)")
                ->execute([$email, $hash, $expiresAt]);

            try {
                Mailer::sendVerificationCode($email, $code);
                $_SESSION['pending_registration_email'] = $email;
                $_SESSION['pending_cgu_accepted'] = true;
                redirect('verify.php');
            } catch (Throwable $exception) {
                $db->prepare('DELETE FROM email_verifications WHERE email = ? AND purpose = \'register\'')->execute([$email]);
                $error = 'Le code n’a pas pu être envoyé. Vérifiez la configuration SMTP puis réessayez.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Créer un compte — <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body class="auth-body">
    <main class="auth-card">
        <div class="auth-brand">
            <div class="brand-mark">🤖</div>
            <h1><?= h(APP_NAME) ?></h1>
            <p class="auth-subtitle">Votre interface pour dialoguer avec OmniRoute</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">

            <label class="field">
                <span class="field-label">Adresse e-mail</span>
                <input type="email" name="email" required autofocus placeholder="vous@exemple.fr"
                    value="<?= h($_POST['email'] ?? '') ?>">
            </label>

            <label class="checkbox-field">
                <input type="checkbox" name="cgu" <?= isset($_POST['cgu']) ? 'checked' : '' ?>>
                <span>J'accepte les <a href="cgu.php" target="_blank">conditions générales d'utilisation</a></span>
            </label>

            <button type="submit" class="btn btn-primary btn-block">Continuer</button>
        </form>

        <p class="auth-switch">Déjà un compte ? <a href="login.php">Se connecter</a></p>
    </main>
</body>

</html>