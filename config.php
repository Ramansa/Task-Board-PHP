<?php

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'kanban_app';
const DB_USER = 'root';
const DB_PASS = '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
