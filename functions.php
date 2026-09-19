<?php

/**
 * Fonctions utilitaires partagées par toutes les pages.
 */

function boot_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('omninterface_session');
        session_start();
    }
}

/** Échappe une chaîne pour affichage HTML. */
function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/** Génère (ou réutilise) le jeton CSRF de la session. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Vérifie le jeton CSRF fourni (formulaire ou en-tête X-CSRF-Token). */
function csrf_check(?string $token): bool
{
    return !empty($_SESSION['csrf_token']) && !empty($token)
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Envoie une réponse JSON et termine le script. */
function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Lit le corps JSON de la requête courante. */
function json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Redirige vers une autre page du site et arrête l'exécution. */
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Identifiant de l'utilisateur connecté, ou null. */
function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

/** Bloque l'accès à une page si l'utilisateur n'est pas connecté. */
function require_login(): int
{
    $id = current_user_id();
    if ($id === null) {
        redirect('login.php');
    }
    return $id;
}

/** Pour les endpoints API : renvoie une erreur JSON 401 si non connecté. */
function require_login_api(): int
{
    $id = current_user_id();
    if ($id === null) {
        json_response(['error' => 'Non authentifié.'], 401);
    }
    return $id;
}

function current_user(): ?array
{
    $id = current_user_id();
    if ($id === null) {
        return null;
    }
    $stmt = Database::get()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    return $user ?: null;
}

/** Encodage base64url (WebAuthn) sans dépendance. */
function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    $data = strtr($data, '-_', '+/');
    $pad = strlen($data) % 4;
    if ($pad > 0) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return base64_decode($data);
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

function encrypt_secret(string $value): string
{
    $key = hash('sha256', APP_ENCRYPTION_KEY, true);
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Chiffrement impossible.');
    }
    return base64url_encode($iv . $tag . $ciphertext);
}

function decrypt_secret(?string $value): ?string
{
    if (!$value) return null;
    $raw = base64url_decode($value);
    if (strlen($raw) < 28) return null;
    $key = hash('sha256', APP_ENCRYPTION_KEY, true);
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);
    $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? null : $plain;
}
