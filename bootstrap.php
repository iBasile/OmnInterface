<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Autoload simple pour la librairie lbuchs/WebAuthn (namespace lbuchs\WebAuthn\...)
spl_autoload_register(function (string $class) {
    $prefix = 'lbuchs\\WebAuthn\\';
    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $path = __DIR__ . '/webauthn-lib/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
});

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/OmniRouteClient.php';

boot_session();
