# PHP + MySQL Kanban Board

A fully functional Kanban board application built with native PHP and MySQL.

## Features

- User registration & login with hashed passwords.
- The first registered user automatically gets the `admin` role.
- Board creation and member assignment.
- Task CRUD inside boards.
- Kanban columns: **To Do**, **In Progress**, **Done**.
- Admin panel for changing member roles.

## Setup

1. Create a MySQL database and tables:
   ```bash
   mysql -u root -p < schema.sql
   ```
2. Update database credentials in `config.php` if needed.
3. Run PHP's built-in server:
   ```bash
   php -S localhost:8000
   ```
4. Open:
   - `http://localhost:8000/register.php` to create first account (admin)
   - `http://localhost:8000/login.php` to sign in.

## Notes

- This app uses session authentication.
- Board access is limited to members of each board.
- Admin users can manage user roles in `admin.php`.
