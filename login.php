<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (currentUser()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        flash('error', 'Invalid credentials.');
        redirect('login.php');
    }

    $_SESSION['user_id'] = (int) $user['id'];
    redirect('dashboard.php');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="auth-card">
    <h1>Sign In</h1>
    <?php if ($msg = flash('error')): ?><p class="error"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <?php if ($msg = flash('success')): ?><p class="success"><?= htmlspecialchars($msg) ?></p><?php endif; ?>

    <form method="post">
        <label>Email
            <input type="email" name="email" required>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button type="submit">Login</button>
    </form>
    <p>Need an account? <a href="register.php">Register</a></p>
</div>
</body>
</html>
