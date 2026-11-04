<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';

startSecureSession();
$pdo = getPDO();
$error = '';
$message = '';

$userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$accountExists = $userCount > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$accountExists) {
    verifyCsrf();

    $emailInput = $_POST['email'] ?? '';
    $nameInput = $_POST['name'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $email = is_string($emailInput) ? strtolower(trim($emailInput)) : '';
    $name = is_string($nameInput) ? trim($nameInput) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
        $error = 'Adresse email invalide.';
    } elseif ($name === '' || strlen($name) > 100) {
        $error = 'Le nom est obligatoire et ne doit pas dépasser 100 caractères.';
    } elseif (strlen($password) < 12) {
        $error = 'Le mot de passe doit contenir au moins 12 caractères.';
    } else {
        $lockAcquired = false;

        try {
            $lockAcquired = (int) $pdo->query("SELECT GET_LOCK('learning_app_initial_account', 5)")->fetchColumn() === 1;
            if (!$lockAcquired) {
                $error = 'La création du compte est temporairement indisponible. Réessayez.';
            } else {
                $accountExists = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
                if ($accountExists) {
                    $message = 'Un compte existe déjà. Connectez-vous à l’application.';
                } else {
                    $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)');
                    $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), $name]);
                    $accountExists = true;
                    $message = 'Compte créé. Connectez-vous à l’application.';
                }
            }
        } catch (PDOException $e) {
            error_log('Initial account creation failed: ' . $e->getMessage());
            $error = 'La création du compte a échoué. Vérifiez la base de données.';
        } finally {
            if ($lockAcquired) {
                $pdo->query("SELECT RELEASE_LOCK('learning_app_initial_account')");
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
    <title>Création du compte — Mon apprentissage</title>
    <link href="public/css/bootstrap.min.css" rel="stylesheet">
    <link href="public/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<main class="container" style="max-width: 480px; margin-top: 80px;">
    <h3 class="mb-4">Créer le compte initial</h3>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($message !== ''): ?>
        <div class="alert alert-info"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($accountExists): ?>
        <p class="text-muted">Un compte existe déjà dans cette base de données. La création du compte initial est désactivée.</p>
        <a class="btn btn-primary w-100" href="login.php">Se connecter</a>
    <?php else: ?>
        <form method="POST">
            <?= csrfField() ?>
            <div class="mb-3">
                <label class="form-label" for="name">Nom</label>
                <input id="name" type="text" name="name" class="form-control" maxlength="100" required autocomplete="name">
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input id="email" type="email" name="email" class="form-control" maxlength="255" required autocomplete="username">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Mot de passe (12 caractères minimum)</label>
                <input id="password" type="password" name="password" class="form-control" required minlength="12" autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary w-100">Créer le compte</button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
