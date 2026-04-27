<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

$_SESSION = [];
session_destroy();

redirect('login.php');
