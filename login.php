<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect('/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE username = ? AND active = 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if ($row && password_verify($password, $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id'        => (int) $row['id'],
            'full_name' => $row['full_name'],
            'username'  => $row['username'],
            'role'      => $row['role'],
        ];
        redirect($row['role'] === 'cashier' ? '/pos.php' : '/dashboard.php');
    }
    $error = 'Wrong username or password. Check both and try again.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · <?= e(STORE_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="login-page">
<div class="login-shell">
    <section class="login-art" aria-hidden="true">
        <div class="brand">
            <span class="brand-mark"><i class="bi bi-basket2-fill"></i></span>
            <span class="brand-name"><?= e(STORE_NAME) ?></span>
        </div>
        <div class="login-tape">
            <div class="t-row"><span>Rice 1kg</span><span>62.00</span></div>
            <div class="t-row"><span>Bottled Water ×2</span><span>30.00</span></div>
            <div class="t-row"><span>Chocolate Bar</span><span>28.00</span></div>
            <div class="t-rule"></div>
            <div class="t-row t-total"><span>Total</span><span>120.00</span></div>
        </div>
    </section>

    <section class="login-form-wrap">
        <form method="post" class="login-form" autocomplete="on">
            <?= csrf_field() ?>
            <h1>Sign in</h1>
            <p class="text-muted mb-4">Use your cashier or admin account to open the register.</p>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><?= e($error) ?></div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input class="form-control form-control-lg" id="username" name="username" required autofocus
                       value="<?= e($_POST['username'] ?? '') ?>">
            </div>
            <div class="mb-4">
                <label class="form-label" for="password">Password</label>
                <input class="form-control form-control-lg" id="password" name="password" type="password" required>
            </div>
            <button class="btn btn-primary btn-lg w-100" type="submit">Sign in</button>
        </form>
    </section>
</div>
</body>
</html>
