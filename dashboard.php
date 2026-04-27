<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

$user = requireAuth();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_board') {
        $boardName = trim($_POST['board_name'] ?? '');
        if ($boardName === '') {
            flash('error', 'Board name is required.');
            redirect('dashboard.php');
        }

        $stmt = $pdo->prepare('INSERT INTO boards (name, owner_id) VALUES (:name, :owner_id)');
        $stmt->execute(['name' => $boardName, 'owner_id' => $user['id']]);
        $boardId = (int) $pdo->lastInsertId();

        $memberStmt = $pdo->prepare('INSERT INTO board_members (board_id, user_id) VALUES (:board_id, :user_id)');
        $memberStmt->execute(['board_id' => $boardId, 'user_id' => $user['id']]);

        flash('success', 'Board created.');
        redirect('dashboard.php?board=' . $boardId);
    }

    $boardId = (int) ($_POST['board_id'] ?? 0);
    if ($boardId < 1) {
        flash('error', 'Invalid board.');
        redirect('dashboard.php');
    }

    $accessStmt = $pdo->prepare(
        'SELECT b.id, b.owner_id FROM boards b
         JOIN board_members bm ON bm.board_id = b.id
         WHERE b.id = :board_id AND bm.user_id = :user_id LIMIT 1'
    );
    $accessStmt->execute(['board_id' => $boardId, 'user_id' => $user['id']]);
    $activeBoard = $accessStmt->fetch();

    if (!$activeBoard) {
        flash('error', 'You do not have access to this board.');
        redirect('dashboard.php');
    }

    if ($action === 'create_task') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priority = $_POST['priority'] ?? 'medium';
        $status = $_POST['status'] ?? 'todo';

        if ($title === '') {
            flash('error', 'Task title is required.');
            redirect('dashboard.php?board=' . $boardId);
        }

        if (!in_array($priority, ['low', 'medium', 'high'], true)) {
            $priority = 'medium';
        }

        if (!in_array($status, ['todo', 'in_progress', 'done'], true)) {
            $status = 'todo';
        }

        $stmt = $pdo->prepare(
            'INSERT INTO tasks (board_id, title, description, status, priority, created_by)
             VALUES (:board_id, :title, :description, :status, :priority, :created_by)'
        );
        $stmt->execute([
            'board_id' => $boardId,
            'title' => $title,
            'description' => $description,
            'status' => $status,
            'priority' => $priority,
            'created_by' => $user['id'],
        ]);

        flash('success', 'Task added.');
        redirect('dashboard.php?board=' . $boardId);
    }

    if ($action === 'move_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $status = $_POST['status'] ?? 'todo';
        if (!in_array($status, ['todo', 'in_progress', 'done'], true)) {
            flash('error', 'Invalid status.');
            redirect('dashboard.php?board=' . $boardId);
        }

        $stmt = $pdo->prepare('UPDATE tasks SET status = :status WHERE id = :id AND board_id = :board_id');
        $stmt->execute(['status' => $status, 'id' => $taskId, 'board_id' => $boardId]);

        flash('success', 'Task moved.');
        redirect('dashboard.php?board=' . $boardId);
    }

    if ($action === 'delete_task') {
        $taskId = (int) ($_POST['task_id'] ?? 0);
        $stmt = $pdo->prepare('DELETE FROM tasks WHERE id = :id AND board_id = :board_id');
        $stmt->execute(['id' => $taskId, 'board_id' => $boardId]);

        flash('success', 'Task removed.');
        redirect('dashboard.php?board=' . $boardId);
    }

    if ($action === 'add_member') {
        if ($activeBoard['owner_id'] != $user['id'] && $user['role'] !== 'admin') {
            flash('error', 'Only the owner or admin can add members.');
            redirect('dashboard.php?board=' . $boardId);
        }

        $memberEmail = strtolower(trim($_POST['member_email'] ?? ''));
        $memberStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $memberStmt->execute(['email' => $memberEmail]);
        $member = $memberStmt->fetch();

        if (!$member) {
            flash('error', 'User not found.');
            redirect('dashboard.php?board=' . $boardId);
        }

        $insertMember = $pdo->prepare('INSERT IGNORE INTO board_members (board_id, user_id) VALUES (:board_id, :user_id)');
        $insertMember->execute(['board_id' => $boardId, 'user_id' => $member['id']]);

        flash('success', 'Member added to board.');
        redirect('dashboard.php?board=' . $boardId);
    }
}

$boardsStmt = $pdo->prepare(
    'SELECT b.id, b.name, b.owner_id, u.name AS owner_name
     FROM boards b
     JOIN board_members bm ON bm.board_id = b.id
     JOIN users u ON u.id = b.owner_id
     WHERE bm.user_id = :user_id
     ORDER BY b.created_at DESC'
);
$boardsStmt->execute(['user_id' => $user['id']]);
$boards = $boardsStmt->fetchAll();

$selectedBoardId = isset($_GET['board']) ? (int) $_GET['board'] : (isset($boards[0]) ? (int) $boards[0]['id'] : 0);
$selectedBoard = null;
$tasks = [];
$members = [];

if ($selectedBoardId > 0) {
    foreach ($boards as $board) {
        if ((int) $board['id'] === $selectedBoardId) {
            $selectedBoard = $board;
            break;
        }
    }

    if ($selectedBoard) {
        $taskStmt = $pdo->prepare(
            'SELECT t.*, u.name AS creator_name
             FROM tasks t
             JOIN users u ON u.id = t.created_by
             WHERE t.board_id = :board_id
             ORDER BY t.created_at DESC'
        );
        $taskStmt->execute(['board_id' => $selectedBoardId]);
        $tasks = $taskStmt->fetchAll();

        $memberListStmt = $pdo->prepare(
            'SELECT u.name, u.email FROM board_members bm
             JOIN users u ON u.id = bm.user_id
             WHERE bm.board_id = :board_id ORDER BY u.name'
        );
        $memberListStmt->execute(['board_id' => $selectedBoardId]);
        $members = $memberListStmt->fetchAll();
    }
}

$grouped = ['todo' => [], 'in_progress' => [], 'done' => []];
foreach ($tasks as $task) {
    $grouped[$task['status']][] = $task;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kanban Dashboard</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
<header class="topbar">
    <div>
        <h1>Kanban Board</h1>
        <p>Welcome, <?= htmlspecialchars($user['name']) ?> (<?= htmlspecialchars($user['role']) ?>)</p>
    </div>
    <nav>
        <?php if ($user['role'] === 'admin'): ?><a href="admin.php">Admin Panel</a><?php endif; ?>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main class="layout">
    <aside class="sidebar">
        <h2>Your Boards</h2>
        <ul>
            <?php foreach ($boards as $board): ?>
                <li>
                    <a href="dashboard.php?board=<?= (int) $board['id'] ?>" class="<?= (int) $board['id'] === $selectedBoardId ? 'active' : '' ?>">
                        <?= htmlspecialchars($board['name']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <form method="post" class="card">
            <input type="hidden" name="action" value="create_board">
            <label>New board
                <input type="text" name="board_name" required>
            </label>
            <button type="submit">Create</button>
        </form>
    </aside>

    <section class="content">
        <?php if ($msg = flash('error')): ?><p class="error"><?= htmlspecialchars($msg) ?></p><?php endif; ?>
        <?php if ($msg = flash('success')): ?><p class="success"><?= htmlspecialchars($msg) ?></p><?php endif; ?>

        <?php if (!$selectedBoard): ?>
            <p>Create a board to begin.</p>
        <?php else: ?>
            <h2><?= htmlspecialchars($selectedBoard['name']) ?></h2>

            <div class="board-meta">
                <div class="card">
                    <h3>Members</h3>
                    <ul>
                        <?php foreach ($members as $member): ?>
                            <li><?= htmlspecialchars($member['name']) ?> (<?= htmlspecialchars($member['email']) ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($selectedBoard['owner_id'] == $user['id'] || $user['role'] === 'admin'): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="add_member">
                        <input type="hidden" name="board_id" value="<?= (int) $selectedBoardId ?>">
                        <label>Add member by email
                            <input type="email" name="member_email" required>
                        </label>
                        <button type="submit">Add</button>
                    </form>
                    <?php endif; ?>
                </div>

                <form method="post" class="card">
                    <h3>Add Task</h3>
                    <input type="hidden" name="action" value="create_task">
                    <input type="hidden" name="board_id" value="<?= (int) $selectedBoardId ?>">
                    <label>Title
                        <input type="text" name="title" required>
                    </label>
                    <label>Description
                        <textarea name="description" rows="3"></textarea>
                    </label>
                    <label>Priority
                        <select name="priority">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                        </select>
                    </label>
                    <label>Column
                        <select name="status">
                            <option value="todo">To Do</option>
                            <option value="in_progress">In Progress</option>
                            <option value="done">Done</option>
                        </select>
                    </label>
                    <button type="submit">Create Task</button>
                </form>
            </div>

            <div class="kanban">
                <?php $titles = ['todo' => 'To Do', 'in_progress' => 'In Progress', 'done' => 'Done']; ?>
                <?php foreach ($titles as $status => $title): ?>
                <div class="column">
                    <h3><?= htmlspecialchars($title) ?></h3>
                    <?php foreach ($grouped[$status] as $task): ?>
                        <article class="task">
                            <strong><?= htmlspecialchars($task['title']) ?></strong>
                            <p><?= nl2br(htmlspecialchars($task['description'] ?? '')) ?></p>
                            <small>Priority: <?= htmlspecialchars($task['priority']) ?> | By: <?= htmlspecialchars($task['creator_name']) ?></small>

                            <form method="post" class="inline-form">
                                <input type="hidden" name="action" value="move_task">
                                <input type="hidden" name="board_id" value="<?= (int) $selectedBoardId ?>">
                                <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                                <select name="status">
                                    <option value="todo" <?= $task['status'] === 'todo' ? 'selected' : '' ?>>To Do</option>
                                    <option value="in_progress" <?= $task['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="done" <?= $task['status'] === 'done' ? 'selected' : '' ?>>Done</option>
                                </select>
                                <button type="submit">Move</button>
                            </form>

                            <form method="post" onsubmit="return confirm('Delete this task?');">
                                <input type="hidden" name="action" value="delete_task">
                                <input type="hidden" name="board_id" value="<?= (int) $selectedBoardId ?>">
                                <input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>">
                                <button class="danger" type="submit">Delete</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
