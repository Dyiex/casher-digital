<?php
$pageTitle = $pageTitle ?? 'Dashboard';
$active    = $active ?? '';
$bodyClass = $bodyClass ?? '';
$user      = current_user();

$nav = [
    ['dashboard',  'Dashboard',  'bi-grid-1x2',  '/dashboard.php',  false],
    ['pos',        'New sale',   'bi-bag-check', '/pos.php',        false],
    ['sales',      'Sales',      'bi-receipt',   '/sales.php',      false],
    ['products',   'Products',   'bi-box-seam',  '/products.php',   true],
    ['categories', 'Categories', 'bi-tags',      '/categories.php', true],
    ['inventory',  'Inventory',  'bi-boxes',     '/inventory.php',  true],
    ['users',      'Users',      'bi-people',    '/users.php',      true],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(STORE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(url('/assets/css/style.css')) ?>" rel="stylesheet">
</head>
<body class="<?= e($bodyClass) ?>">

<div class="app-topbar d-lg-none">
    <button class="btn btn-icon" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-label="Open menu">
        <i class="bi bi-list fs-4"></i>
    </button>
    <span class="brand-name"><?= e(STORE_NAME) ?></span>
</div>

<aside class="sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar">
    <div class="sidebar-inner">
        <div class="brand">
            <span class="brand-mark"><i class="bi bi-basket2-fill"></i></span>
            <span class="brand-name"><?= e(STORE_NAME) ?></span>
        </div>

        <nav class="nav flex-column">
            <?php foreach ($nav as [$key, $label, $icon, $href, $adminOnly]): ?>
                <?php if ($adminOnly && !is_admin()) continue; ?>
                <a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= e(url($href)) ?>">
                    <i class="bi <?= e($icon) ?>"></i><span><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-user">
            <div class="avatar"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></div>
            <div class="who">
                <strong><?= e($user['full_name']) ?></strong>
                <small><?= e(ucfirst($user['role'])) ?></small>
            </div>
            <a class="btn btn-icon" href="<?= e(url('/logout.php')) ?>" title="Sign out" aria-label="Sign out">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</aside>

<main class="app-main">
    <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show flash" role="alert">
            <?= e($f['msg']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endforeach; ?>
