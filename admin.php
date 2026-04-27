<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

$user = requireAuth();
if ($user['role'] !== 'admin') {
    flash('error', 'Admins only.');
    redirect('dashboard.php');
}

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetId = (int) ($_POST['user_id'] ?? 0);
    $role = $_POST['role'] ?? 'member';

    if ($targetId === (int) $user['id']) {
        flash('error', 'You cannot change your own role.');
        redirect('admin.php');
    }

    if (!in_array($role, ['admin', 'member'], true)) {
        flash('error', 'Invalid role.');
        redirect('admin.php');
    }

    $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
    $stmt->execute(['role' => $role, 'id' => $targetId]);

    flash('success', 'User role updated.');
    redirect('admin.php');
}

$users = $pdo->query('SELECT id, name, email, role, created_at FROM users ORDER BY created_at ASC')->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <h1>Admin Panel</h1>
    <nav><a href="dashboard.php">Dashboard</a> <a href="logout.php">Logout</a></nav>
</header>

<main class="content single">
    <?php if ($msg = flash('error')): ?><p class="error"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
    <?php if ($msg = flash('success')): ?><p class="success"><?= htmlspecialchars($msg) ?></p><?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Name</th><th>Email</th><th>Role</th><th>Created</th><th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['role']) ?></td>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td>
                    <?php if ((int) $row['id'] !== (int) $user['id']): ?>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                            <select name="role">
                                <option value="member" <?= $row['role'] === 'member' ? 'selected' : '' ?>>Member</option>
                                <option value="admin" <?= $row['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <button type="submit">Update</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</main>
</body>
</html>
