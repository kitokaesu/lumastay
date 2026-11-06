<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

/**
 * Sessão segura e comum a toda a aplicação.
 */
function start_lumastay_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off');

    session_name('LumaStaySess');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

start_lumastay_session();

/* ------------------------------------------------------------------
 * CSRF
 * ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_is_valid(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || $token === '') {
        return false;
    }

    return hash_equals(csrf_token(), $token);
}

/**
 * Interrompe operações POST com token inválido/expirado.
 */
function verify_csrf(string $fallback = 'index.php'): void
{
    if (csrf_is_valid()) {
        return;
    }

    set_flash('error', 'A sessão expirou ou o pedido não é válido. Tente novamente.');
    redirect($fallback);
}

/* ------------------------------------------------------------------
 * Autenticação administrativa
 * ------------------------------------------------------------------ */

function current_admin(): ?array
{
    $admin = $_SESSION['admin'] ?? null;
    return is_array($admin) ? $admin : null;
}

function is_admin_logged_in(): bool
{
    return current_admin() !== null;
}

function admin_session_is_expired(): bool
{
    $lastActivity = $_SESSION['admin_last_activity'] ?? null;
    return is_int($lastActivity) && (time() - $lastActivity) > 7200;
}

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? url('admin/index.php');
        set_flash('warning', 'Inicie sessão para aceder à área administrativa.');
        redirect('login.php');
    }

    if (admin_session_is_expired()) {
        admin_logout();
        set_flash('warning', 'A sessão expirou por inatividade. Inicie sessão novamente.');
        redirect('login.php');
    }

    $_SESSION['admin_last_activity'] = time();
}

function admin_logout(): void
{
    unset($_SESSION['admin'], $_SESSION['admin_last_activity']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function failed_login_count(): int
{
    $attempts = $_SESSION['login_attempts'] ?? [];
    if (!is_array($attempts)) {
        return 0;
    }

    $cutoff = time() - 600;
    $recent = array_filter($attempts, static fn ($timestamp) => is_int($timestamp) && $timestamp >= $cutoff);
    $_SESSION['login_attempts'] = array_values($recent);

    return count($recent);
}

function login_is_locked(): bool
{
    return failed_login_count() >= 5;
}

function register_failed_login(): void
{
    $attempts = $_SESSION['login_attempts'] ?? [];
    if (!is_array($attempts)) {
        $attempts = [];
    }
    $attempts[] = time();
    $_SESSION['login_attempts'] = $attempts;
}

/**
 * Valida credenciais de administração.
 *
 * @return array{ok: bool, message: string}
 */
function authenticate_admin(string $email, string $password): array
{
    if (login_is_locked()) {
        return ['ok' => false, 'message' => 'Demasiadas tentativas. Aguarde alguns minutos antes de tentar novamente.'];
    }

    if (!validate_email_value($email) || $password === '') {
        register_failed_login();
        return ['ok' => false, 'message' => 'Email ou palavra-passe incorretos.'];
    }

    $stmt = db()->prepare('SELECT id, name, email, password_hash FROM admin_users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $admin = $stmt->fetch();

    if (!is_array($admin) || !password_verify($password, (string) $admin['password_hash'])) {
        register_failed_login();
        return ['ok' => false, 'message' => 'Email ou palavra-passe incorretos.'];
    }

    // Rehash transparente caso o custo do algoritmo tenha mudado.
    if (password_needs_rehash((string) $admin['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $update = db()->prepare('UPDATE admin_users SET password_hash = :hash WHERE id = :id');
        $update->execute([':hash' => $newHash, ':id' => (int) $admin['id']]);
    }

    session_regenerate_id(true);
    $_SESSION['admin'] = [
        'id'    => (int) $admin['id'],
        'name'  => (string) $admin['name'],
        'email' => (string) $admin['email'],
    ];
    $_SESSION['admin_last_activity'] = time();
    unset($_SESSION['login_attempts'], $_SESSION['csrf_token']);

    return ['ok' => true, 'message' => 'Sessão iniciada com sucesso.'];
}
