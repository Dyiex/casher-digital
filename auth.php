<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'path'     => '/',
    ]);
    session_name('pos_session');
    session_start();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function require_login(): void
{
    if (!current_user()) {
        redirect('/login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('danger', 'Only admins can open that page.');
        redirect('/dashboard.php');
    }
}

function require_login_api(): void
{
    if (!current_user()) {
        json_out(['ok' => false, 'error' => 'Your session expired. Sign in again.'], 401);
    }
}
