<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (!is_post()) {
    redirect('index.php');
}

verify_csrf('index.php');
admin_logout();

set_flash('success', 'Sessão terminada com sucesso.');
redirect('login.php');
