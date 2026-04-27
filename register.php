<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (currentUser()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        flash('error', 'Please fill in all fields.');
        redirect('register.php');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Invalid email format.');
        redirect('register.php');
    }

    $pdo = db();
    $exists = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $exists->execute(['email' => $email]);

    if ($exists->fetch()) {
        flash('error', 'Email is already in use.');
        redirect('register.php');
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $role = $count === 0 ? 'admin' : 'member';

    $insert = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role) VALUES (:name, :email, :password_hash, :role)'
    );
    $insert->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
    ]);

    flash('success', $role === 'admin' ? 'Registered as first user (admin).' : 'Registration successful. Please log in.');
    redirect('login.php');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<div class="auth-card">
    <h1>Create Account</h1>
    <?php if ($msg = flash('error')): ?><p class="error"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <?php if ($msg = flash('success')): ?><p class="success"><?= htmlspecialchars($msg) ?></p><?php endif; ?>

    <form method="post">
        <label>Name
            <input type="text" name="name" required>
        </label>
        <label>Email
            <input type="email" name="email" required>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Sign in</a></p>
</div>
</body>
</html>
