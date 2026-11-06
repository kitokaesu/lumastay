<?php
$pageTitle = $pageTitle ?? 'Alojamento turístico premium em Portugal';
$pageDescription = $pageDescription ?? 'A LumaStay seleciona alojamentos extraordinários em Portugal. Descubra casas, villas e quintas para estadias memoráveis.';
$bodyClass = $bodyClass ?? '';
$currentPage = $currentPage ?? '';
?><!doctype html>
<html lang="pt-PT" data-base="<?= e(base_url()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> — LumaStay</title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta name="theme-color" content="#183A3A">
    <link rel="icon" type="image/svg+xml" href="<?= e(asset('images/logo-mark.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=1.0">
    <link rel="stylesheet" href="<?= e(asset('css/responsive.css')) ?>?v=1.0">
    <script>document.documentElement.classList.add('js');</script>
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#conteudo">Saltar para o conteúdo</a>

<header class="site-header" data-site-header>
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('index.php')) ?>" aria-label="LumaStay — página inicial">
            <img class="brand-mark" src="<?= e(asset('images/logo-mark.svg')) ?>" alt="" width="40" height="40">
            <span class="brand-wordmark">Luma<span>Stay</span></span>
        </a>

        <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="primary-nav" aria-label="Abrir menu">
            <span class="nav-toggle-open"><?= icon('menu') ?></span>
            <span class="nav-toggle-close"><?= icon('close') ?></span>
        </button>

        <?php include __DIR__ . '/navigation.php'; ?>
    </div>
</header>

<?= render_flashes() ?>

<main id="conteudo">
