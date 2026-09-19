<?php
require_once __DIR__ . '/bootstrap.php';

$email = $_SESSION['pending_registration_email'] ?? null;
if (!$email) {
    redirect('register.php');
}

$error = null;
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf_token'] ?? null)) {
        $error = 'Session invalide, veuillez réessayer.';
    } elseif (isset($_POST['resend'])) {
        $db = Database::get();
        $code = str_pad((string) random_int(0, (int) str_repeat('9', VERIFICATION_CODE_LENGTH)), VERIFICATION_CODE_LENGTH, '0', STR_PAD_LEFT);
        $hash = password_hash($code, PASSWORD_DEFAULT);
        $expiresAt = date('Y-m-d H:i:s', time() + VERIFICATION_CODE_TTL);

        $db->prepare("DELETE FROM email_verifications WHERE email = ? AND purpose = 'register'")->execute([$email]);
        $db->prepare("INSERT INTO email_verifications (email, code_hash, purpose, expires_at) VALUES (?, ?, 'register', ?)")
            ->execute([$email, $hash, $expiresAt]);

        Mailer::sendVerificationCode($email, $code);
        $notice = 'Un nouveau code vient d\'être envoyé.';
    } else {
        $code = trim((string) ($_POST['code'] ?? ''));
        $db = Database::get();

        $stmt = $db->prepare("SELECT * FROM email_verifications WHERE email = ? AND purpose = 'register' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch();

        if (!$row) {
            $error = 'Aucun code en attente. Demandez-en un nouveau.';
        } elseif ($row['attempts'] >= 5) {
            $error = 'Trop de tentatives. Demandez un nouveau code.';
        } elseif (strtotime($row['expires_at']) < time()) {
            $error = 'Ce code a expiré. Demandez-en un nouveau.';
        } elseif (!password_verify($code, $row['code_hash'])) {
            $db->prepare('UPDATE email_verifications SET attempts = attempts + 1 WHERE id = ?')->execute([$row['id']]);
            $error = 'Code incorrect.';
        } else {
            $db->prepare('DELETE FROM email_verifications WHERE id = ?')->execute([$row['id']]);
            $_SESSION['verified_email'] = $email;
            unset($_SESSION['pending_registration_email']);
            redirect('passkey-create.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vérification — <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body class="auth-body">
    <main class="auth-card">
        <div class="auth-brand">
            <div class="auth-logo">📧</div>
            <h1>Vérifiez votre e-mail</h1>
            <p class="auth-subtitle">Un code à <?= VERIFICATION_CODE_LENGTH ?> chiffres a été envoyé à<br><strong><?= h($email) ?></strong></p>
        </div>

        <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
        <?php if ($notice): ?><div class="alert alert-success"><?= h($notice) ?></div><?php endif; ?>

        <form method="post" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <label class="field">
                <span class="field-label">Code de vérification</span>
                <input type="text" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="<?= VERIFICATION_CODE_LENGTH ?>"
                    class="code-input" required autofocus placeholder="••••••">
            </label>
            <button type="submit" class="btn btn-primary btn-block">Vérifier</button>
        </form>

        <form method="post" class="auth-switch-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <button type="submit" name="resend" value="1" class="link-button">Je n'ai rien reçu, renvoyer le code</button>
        </form>
    </main>
</body>

</html>