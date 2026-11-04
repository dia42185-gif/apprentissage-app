# Mon apprentissage

**Auteur :** Abdou Latif DIA

Application web locale pour organiser les formations, les objectifs, le planning, les sessions d'apprentissage, les révisions, le journal et les périodes de vacances.

## Prérequis

- PHP 8.0 ou supérieur, avec les extensions `PDO` et `pdo_mysql`
- Un serveur MySQL ou MariaDB démarré localement
- Le client en ligne de commande `mysql` pour importer le schéma (ou un outil MySQL permettant d'exécuter un fichier SQL)

## Installation locale

Depuis la racine du projet, dans l'invite de commandes Windows, importez le schéma dans MySQL pour créer la base et ses tables. Exécutez cette étape sur une base vide; ne réimportez pas le schéma sur une base qui contient des données.

```text
mysql -u root -p < sql/schema.sql
```

Entrez le mot de passe MySQL si le compte en possède un. Si `mysql` n'est pas reconnu, ajoutez le dossier `bin` de MySQL au `PATH` ou ouvrez le client MySQL fourni avec votre installation.

Dans PowerShell, lancez la même commande par l'invite de commandes :

```powershell
cmd /c "mysql -u root -p < sql\schema.sql"
```

La configuration locale par défaut est `localhost`, la base `learning_app`, l'utilisateur `root` et un mot de passe vide. Si votre configuration MySQL est différente, définissez `DB_HOST`, `DB_NAME`, `DB_USER` et `DB_PASS`, ou adaptez les valeurs correspondantes dans `config/database.php`.

Lancez ensuite le serveur PHP depuis la racine du projet :

```text
php -S localhost:8000 router.php
```

Ouvrez `http://localhost:8000/create_user.php` pour créer le premier compte. Le mot de passe doit contenir au moins 12 caractères. Connectez-vous ensuite sur `http://localhost:8000/login.php`.

La création est limitée au premier compte de la base. Si la page n'affiche que le bouton **Se connecter**, cela signifie qu'un compte existe déjà dans la base sélectionnée. Connectez-vous avec ce compte. Pour repartir de zéro, sauvegardez d'abord vos données puis utilisez une base de données vide.

## Utilisation

- **Dashboard** : consulter le résumé des activités, des formations, des objectifs et du temps enregistré.
- **Aujourd'hui** et **Planning** : créer des tâches, organiser les horaires et suivre leur état. Démarrez et mettez en pause le chronomètre pendant les interruptions. À la fin, la saisie manuelle correspond au temps réel supplémentaire, distinct des intervalles chronométrés et des Pomodoros associés, même si un Pomodoro n'est pas encore enregistré. La durée planifiée n'est jamais utilisée comme durée apprise.
- **Formations** : ajouter une formation, préciser son statut et sa priorité, puis suivre ses modules.
- **Objectifs** : définir et suivre des objectifs.
- **Temps d'apprentissage** et **Pomodoro** : saisir du temps réellement étudié ou mesurer les intervalles actifs du Pomodoro. Associez un Pomodoro à sa tâche pour fusionner les intervalles qui se chevauchent; si un chronomètre de tâche est actif, l'application peut aussi rattacher automatiquement le Pomodoro à cette tâche. À la fin d'une tâche, indiquez uniquement le temps réel supplémentaire qui n'est pas inclus dans un chronomètre ou un Pomodoro associé.
- **Révisions** : créer des notions et suivre leur révision.
- **Journal** : consigner les apprentissages et les difficultés.
- **Vacances** : saisir une période et ses objectifs.
- **Statistiques** : consulter les données d'activité enregistrées.

Les formulaires de suppression demandent une confirmation. La déconnexion se fait depuis le menu de navigation.

## Sécurité et données

- Le serveur PHP est lancé sur `localhost` et ne doit pas être exposé au réseau ni à Internet.
- Le routeur `router.php` bloque l'accès HTTP direct aux dossiers internes et aux fichiers cachés ; le serveur PHP intégré n'applique pas les règles `.htaccess`.
- Les mots de passe sont stockés sous forme de hachage. Les formulaires utilisent une protection CSRF. Les cookies de session sont `HttpOnly`, `SameSite=Lax` et `Secure` lorsque la connexion utilise HTTPS.
- Les sessions datées dans le futur restent enregistrées comme données planifiées, mais elles ne sont comptées dans les totaux de temps réel qu'à partir de leur date.
- La base conserve les données de l'application. Sauvegardez-la régulièrement avec les outils MySQL.

## Licences des bibliothèques

Les bibliothèques tierces embarquées et leurs licences sont répertoriées dans [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md). Conservez ce fichier ainsi que les avis de licence présents dans les fichiers distribués si vous copiez ou redistribuez le projet.

## Dépannage

- Si la connexion à la base échoue, vérifiez que le serveur MySQL est démarré, que `sql/schema.sql` a été importé et que les paramètres `DB_*` correspondent à votre installation.
- Si les styles ne s'affichent pas, lancez le serveur depuis la racine du projet et vérifiez que le dossier `public` est présent.
- Consultez les journaux PHP/MySQL pour les détails techniques. Les erreurs de connexion à la base ne sont pas affichées dans la page.
