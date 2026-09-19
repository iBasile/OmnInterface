# OmnInterface

Interface graphique pour interroger un serveur [OmniRoute](https://github.com/diegosouzapw/OmniRoute).

## Installation sur un serveur PHP

1. Déposez le contenu du projet dans le dossier web.
2. Vérifiez que PHP dispose de `pdo_sqlite` (SQLite) ou `pdo_mysql` (MySQL), ainsi que `curl` et `mbstring`.
3. Dans [config.php](./config.php), choisissez `DB_DRIVER` (`sqlite` ou `mysql`), puis renseignez les paramètres `DB_*` correspondants.
4. Définissez aussi `APP_ENCRYPTION_KEY` avec une valeur secrète aléatoire d’au moins 32 caractères. Elle sert à chiffrer les clés OmniRoute enregistrées.
5. Pour SQLite, rendez le dossier `data/` inscriptible par PHP (`chmod 770 data` si nécessaire). La base est créée au premier accès. Pour MySQL, créez d’abord la base et son utilisateur dans AlwaysData ; les tables sont créées automatiquement.
6. Choisissez `MAIL_TRANSPORT` (`mail` ou `smtp`) et renseignez les paramètres `SMTP_*` si nécessaire. Pour un relais sans authentification, utilisez `SMTP_AUTH = false`.
7. Activez HTTPS. Les passkeys WebAuthn exigent un contexte sécurisé (HTTPS), sauf sur `localhost`.

Le dossier `data/` est bloqué par `.htaccess`. Si AlwaysData utilise une configuration Nginx, placez la base SQLite dans un dossier privé hors webroot et adaptez `DB_PATH`.

Pour AlwaysData, utilisez généralement `SMTP_ENCRYPTION = 'ssl'` avec le port 465, ou `SMTP_ENCRYPTION = 'tls'` avec le port 587. Le port 465 attend une négociation SSL dès l’ouverture de la connexion ; il ne faut pas y envoyer `STARTTLS`.

## OmniRoute local et AlwaysData

`http://localhost:20128/v1` est la valeur proposée par défaut, mais `localhost` côté AlwaysData désigne le serveur AlwaysData, pas l’ordinateur de l’utilisateur. Pour interroger un OmniRoute local depuis AlwaysData, utilisez une URL joignable par le serveur (tunnel sécurisé, reverse proxy ou serveur public protégé). N’exposez jamais OmniRoute sans authentification et chiffrement.

### Extension Chrome OmnInterface Unblock

Pour conserver OmniRoute uniquement sur votre ordinateur tout en utilisant l’interface AlwaysData, chargez l’extension non empaquetée depuis [OmnInterface-Unblock](./OmnInterface-Unblock). Elle relaie les appels du navigateur vers `http://127.0.0.1:20128/v1`; AlwaysData ne contacte donc jamais directement votre machine.

Après installation, modifiez [OmnInterface-Unblock/config.js](./OmnInterface-Unblock/config.js) afin d’indiquer l’origine exacte de votre site AlwaysData. L’extension limite les cibles à `localhost`, `127.0.0.1` et `[::1]`, sur un chemin `/v1`.

## Parcours utilisateur

- inscription par e-mail et acceptation des CGU ;
- code de vérification envoyé via `mail()` ou SMTP ;
- création d’une passkey via WebAuthn du navigateur ;
- liaison de l’URL OmniRoute ;
- configuration et chiffrement d’une clé API OmniRoute ;
- création et sauvegarde de discussions SQLite.

La configuration initiale de passkey conserve l’identifiant de credential et doit être complétée par une vérification WebAuthn côté serveur avant une mise en production sensible. Pour un déploiement production, ajoutez `web-auth/webauthn-lib` via Composer et remplacez la validation de credential dans `passkey-create.php` et `passkey-login.php` par la vérification d’attestation/assertion de la bibliothèque.
