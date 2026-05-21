<?php
$currentPage = basename($_SERVER['SCRIPT_NAME']);
$isAdminPage = str_starts_with($currentPage, 'admin');
$notice = flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'STEAM Festival') ?></title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <header class="topbar">
        <div>
            <p class="eyebrow">STEAM Festival</p>
            <h1><?= e($pageTitle ?? 'Dashboard') ?></h1>
        </div>
        <nav>
            <a class="<?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php">Register</a>
            <a class="<?= $isAdminPage ? 'active' : '' ?>" href="admin.php">Admin</a>
        </nav>
    </header>
    <main class="shell <?= $isAdminPage ? 'admin-shell' : '' ?>">
        <?php if ($isAdminPage): ?>
            <aside class="admin-sidebar" aria-label="Admin navigation">
                <p class="eyebrow">Participation</p>
                <nav class="admin-side-nav">
                    <a class="<?= $currentPage === 'admin.php' ? 'active' : '' ?>" href="admin.php">Overview</a>
                    <a class="<?= $currentPage === 'admin_schools.php' ? 'active' : '' ?>" href="admin_schools.php">School Participation</a>
                    <a class="<?= $currentPage === 'admin_learners.php' ? 'active' : '' ?>" href="admin_learners.php">Learner Entries</a>
                </nav>
            </aside>
            <div class="admin-content">
        <?php endif; ?>
        <?php if ($notice): ?>
            <div class="notice <?= e($notice['type']) ?>"><?= e($notice['message']) ?></div>
        <?php endif; ?>
