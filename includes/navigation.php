<?php
$currentPage = $currentPage ?? '';
$admin = current_admin();
?>
<nav class="primary-nav" id="primary-nav" aria-label="Navegação principal">
    <a class="nav-link<?= is_active_nav('alojamentos', $currentPage) ? ' is-active' : '' ?>" href="<?= e(url('alojamentos.php')) ?>">Alojamentos</a>
    <a class="nav-link<?= is_active_nav('consultar', $currentPage) ? ' is-active' : '' ?>" href="<?= e(url('consultar-reserva.php')) ?>">Consultar reserva</a>
    <a class="nav-link<?= is_active_nav('sobre', $currentPage) ? ' is-active' : '' ?>" href="<?= e(url('index.php')) ?>#sobre">A LumaStay</a>

    <div class="nav-actions">
        <?php if ($admin !== null): ?>
            <a class="nav-link nav-admin-link" href="<?= e(url('admin/index.php')) ?>">
                <?= icon('grid') ?> Dashboard
            </a>
            <form action="<?= e(url('logout.php')) ?>" method="post" class="nav-logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="nav-link nav-logout-button"><?= icon('logout') ?> Sair</button>
            </form>
        <?php else: ?>
            <a class="nav-link nav-login-link" href="<?= e(url('login.php')) ?>"><?= icon('lock') ?> Entrar</a>
        <?php endif; ?>
        <a class="btn btn-primary btn-small" href="<?= e(url('alojamentos.php')) ?>">Reservar agora</a>
    </div>
</nav>
