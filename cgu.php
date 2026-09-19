<?php require_once __DIR__ . '/bootstrap.php'; ?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Conditions générales d'utilisation — <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body class="auth-body">
    <main class="auth-card legal-card">
        <h1>Conditions générales d'utilisation</h1>
        <h2>1. Objet</h2>
        <p><?= h(APP_NAME) ?> est une interface permettant d'envoyer des messages à un serveur OmniRoute que vous configurez vous-même. L'éditeur de <?= h(APP_NAME) ?> n'héberge ni n'opère ce serveur.</p>
        <h2>2. Compte et authentification</h2>
        <p>La création de compte nécessite une adresse e-mail valide et la création d'une clé d'accès (passkey) stockée sur votre appareil. Aucun mot de passe n'est utilisé ni conservé.</p>
        <h2>3. Données conservées</h2>
        <p>Sont conservés : votre adresse e-mail, l'adresse de votre serveur OmniRoute, et l'historique de vos discussions, afin d'assurer le fonctionnement du service. Ce site est pour l'instant expérimental : vos données sont stocké dans une base de donnée, de manière non-chiffré.</p>
        <h2>4. Responsabilité</h2>
        <p>Le contenu généré par le serveur OmniRoute relève de la responsabilité du fournisseur de modèle configuré par l'utilisateur.</p>
        <h2>5. Contact</h2>
        <p>Pour nous contacter : omninterface@alwaysdata.net</p>
        <p><a href="register.php">&larr; Retour</a></p>
    </main>
</body>

</html>