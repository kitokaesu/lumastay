<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_admin_logged_in()) {
    redirect('admin/index.php');
}

$errors = [];
$email = input_string('email', 'post');
$password = input_string('password', 'post');

if (is_post()) {
    verify_csrf('login.php');

    $result = authenticate_admin($email, $password);

    if ($result['ok']) {
        $intended = $_SESSION['intended_url'] ?? null;
        unset($_SESSION['intended_url']);

        set_flash('success', 'Bem-vindo de volta à área de gestão da LumaStay.');

        if (is_string($intended) && str_starts_with($intended, base_url() . '/admin/')) {
            header('Location: ' . $intended);
            exit;
        }

        redirect('admin/index.php');
    }

    $errors[] = $result['message'];
}

$showDemoCredentials = (string) lumastay_config_value('app_env', 'local') !== 'production';

$pageTitle = 'Entrar na área de gestão';
$pageDescription = 'Acesso reservado à equipa LumaStay.';
$currentPage = 'login';
$bodyClass = 'page-login';

include __DIR__ . '/includes/header.php';
?>

<section class="login-section">
    <div class="container login-shell">
        <div class="login-visual">
            <img src="<?= e(asset('images/hero.svg')) ?>" alt="" aria-hidden="true">
            <div class="login-visual-overlay"></div>
            <div class="login-visual-content">
                <p class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> Área reservada</p>
                <h2>O lado de dentro da LumaStay.</h2>
                <p>Gerir alojamentos, acompanhar reservas e conhecer melhor cada hóspede, num só lugar.</p>
                <ul>
                    <li><?= icon('grid') ?> Visão clara da operação</li>
                    <li><?= icon('calendar') ?> Reservas e disponibilidade</li>
                    <li><?= icon('users') ?> Relação com os hóspedes</li>
                </ul>
            </div>
            <span class="login-visual-badge"><?= icon('shield') ?> Acesso seguro</span>
        </div>

        <div class="login-panel">
            <div class="login-panel-head">
                <p class="eyebrow"><span class="eyebrow-line"></span> Equipa LumaStay</p>
                <h1>Entrar</h1>
                <p>Introduza as credenciais da sua conta de administração.</p>
            </div>

            <?= render_errors($errors) ?>

            <form method="post" action="<?= e(url('login.php')) ?>" class="login-form">
                <?= csrf_field() ?>
                <div class="form-field">
                    <label for="login-email">Email</label>
                    <input type="email" id="login-email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
                </div>
                <div class="form-field">
                    <label for="login-password">Palavra-passe</label>
                    <div class="password-field">
                        <input type="password" id="login-password" name="password" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" data-toggle-password aria-label="Mostrar palavra-passe" aria-pressed="false">
                            <?= icon('eye') ?>
                        </button>
                    </div>
                </div>
                <button class="btn btn-primary btn-block btn-large" type="submit">Entrar na área de gestão <?= icon('arrow-right') ?></button>
            </form>

            <?php if ($showDemoCredentials): ?>
                <div class="demo-credentials">
                    <span class="demo-credentials-icon"><?= icon('info') ?></span>
                    <div>
                        <strong>Credenciais de demonstração</strong>
                        <p>Email: <code>admin@lumastay.pt</code><br>Palavra-passe: <code>LumaStay2026!</code></p>
                        <small>Visíveis apenas em ambiente local. Em produção, desativar em <code>config/config.local.php</code>.</small>
                    </div>
                </div>
            <?php endif; ?>

            <p class="login-footer-note"><?= icon('lock') ?> A sessão expira automaticamente após 2 horas de inatividade.</p>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
