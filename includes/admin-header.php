<?php
require_admin();

$adminPage = $adminPage ?? 'dashboard';
$pageTitle = $pageTitle ?? 'Dashboard';
$bodyClass = $bodyClass ?? 'admin-body';
$admin = current_admin();

$adminNavigation = [
    'dashboard'      => ['label' => 'Dashboard', 'icon' => 'grid', 'href' => 'admin/index.php'],
    'alojamentos'    => ['label' => 'Alojamentos', 'icon' => 'home', 'href' => 'admin/alojamentos.php'],
    'reservas'       => ['label' => 'Reservas', 'icon' => 'calendar', 'href' => 'admin/reservas.php'],
    'hospedes'       => ['label' => 'Hóspedes', 'icon' => 'users', 'href' => 'admin/hospedes.php'],
    'disponibilidade'=> ['label' => 'Disponibilidade', 'icon' => 'chart', 'href' => 'admin/disponibilidade.php'],
];

$currentFile = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($currentFile === 'detalhes-reserva.php') {
    $adminPage = 'reservas';
} elseif ($currentFile === 'hospede.php') {
    $adminPage = 'hospedes';
} elseif (in_array($currentFile, ['criar-alojamento.php', 'editar-alojamento.php'], true)) {
    $adminPage = 'alojamentos';
}
?><!doctype html>
<html lang="pt-PT" data-base="<?= e(base_url()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> — Administração LumaStay</title>
    <meta name="description" content="Área administrativa da LumaStay.">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#183A3A">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo-mark.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=1.0">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>?v=1.0">
    <link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>?v=1.0">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#admin-content">Saltar para o conteúdo</a>

<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="Navegação administrativa">
        <a class="brand admin-brand" href="<?= e(url('admin/index.php')) ?>">
            <img class="brand-mark" src="<?= e(asset('images/logo-mark.svg')) ?>" alt="" width="38" height="38">
            <span class="brand-wordmark">Luma<span>Stay</span></span>
        </a>
        <p class="admin-brand-label">Administração</p>

        <nav class="admin-nav">
            <?php foreach ($adminNavigation as $key => $item): ?>
                <a class="admin-nav-link<?= $adminPage === $key ? ' is-active' : '' ?>" href="<?= e(url($item['href'])) ?>"<?= $adminPage === $key ? ' aria-current="page"' : '' ?>>
                    <?= icon($item['icon']) ?>
                    <span><?= e($item['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="admin-sidebar-footer">
            <a class="admin-side-link" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener">
                <?= icon('eye') ?> Ver o site
            </a>
            <form action="<?= e(url('logout.php')) ?>" method="post">
                <?= csrf_field() ?>
                <button type="submit" class="admin-side-link admin-logout"><?= icon('logout') ?> Terminar sessão</button>
            </form>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <button type="button" class="admin-menu-toggle" data-admin-menu aria-expanded="false" aria-controls="admin-sidebar" aria-label="Abrir menu administrativo">
                <?= icon('menu') ?>
            </button>
            <div class="admin-topbar-title">
                <p class="admin-eyebrow">Painel de gestão</p>
                <h1><?= e($pageTitle) ?></h1>
            </div>
            <div class="admin-user">
                <span class="admin-avatar" aria-hidden="true"><?= e(strtoupper(substr((string) ($admin['name'] ?? 'L'), 0, 1))) ?></span>
                <span class="admin-user-info">
                    <strong><?= e($admin['name'] ?? 'Administrador') ?></strong>
                    <small><?= e($admin['email'] ?? '') ?></small>
                </span>
            </div>
        </header>

        <div class="admin-content" id="admin-content">
            <?= render_flashes() ?>
