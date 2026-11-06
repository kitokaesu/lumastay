<?php
declare(strict_types=1);

/**
 * LumaStay — configuração e ligação à base de dados.
 *
 * Ordem de prioridade:
 *   1. Variáveis de ambiente (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, APP_ENV, APP_URL)
 *   2. config/config.local.php (não versionado)
 *   3. Valores por omissão adequados ao Laragon (root sem palavra-passe)
 *
 * Nunca colocar credenciais reais em repositórios públicos.
 */

function lumastay_config(): array
{
    static $config = null;

    if (is_array($config)) {
        return $config;
    }

    $config = [
        'app_name'     => 'LumaStay',
        'app_env'      => 'local',
        'app_url'      => '',
        'timezone'     => 'Europe/Lisbon',
        'db_host'      => '127.0.0.1',
        'db_port'      => '3306',
        'db_name'      => 'lumastay',
        'db_user'      => 'root',
        'db_password'  => '',
        'db_charset'   => 'utf8mb4',
    ];

    $localFile = __DIR__ . '/config.local.php';
    if (is_file($localFile)) {
        $local = require $localFile;
        if (is_array($local)) {
            $config = array_merge($config, $local);
        }
    }

    $environmentMap = [
        'APP_ENV'     => 'app_env',
        'APP_URL'     => 'app_url',
        'DB_HOST'     => 'db_host',
        'DB_PORT'     => 'db_port',
        'DB_NAME'     => 'db_name',
        'DB_USER'     => 'db_user',
        'DB_PASSWORD' => 'db_password',
        'DB_CHARSET'  => 'db_charset',
    ];

    foreach ($environmentMap as $envKey => $configKey) {
        $value = getenv($envKey);
        if ($value !== false && $value !== '') {
            $config[$configKey] = $value;
        }
    }

    return $config;
}

function lumastay_config_value(string $key, mixed $default = null): mixed
{
    $config = lumastay_config();
    return $config[$key] ?? $default;
}

/**
 * Devolve uma ligação PDO reutilizável.
 * Os erros internos são registados no log e nunca mostrados ao visitante.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = lumastay_config();
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $config['db_host'],
        $config['db_port'],
        $config['db_name'],
        $config['db_charset']
    );

    try {
        $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $exception) {
        error_log('[LumaStay] Falha na ligação à base de dados: ' . $exception->getMessage());

        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');

        $debug = getenv('APP_DEBUG') === '1';
        $detail = $debug ? '<p class="error-detail">' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>' : '';

        echo '<!doctype html><html lang="pt-PT"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Serviço temporariamente indisponível — LumaStay</title>'
            . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#FAF9F6;color:#292D2C;font-family:"Segoe UI",Arial,sans-serif;padding:24px}'
            . '.box{max-width:560px;background:#fff;border:1px solid #EAE4D9;border-radius:18px;padding:40px;box-shadow:0 18px 50px rgba(24,58,58,.10)}'
            . 'h1{font-family:Georgia,serif;color:#183A3A;margin:0 0 12px;font-size:2rem}p{line-height:1.7;color:#5b625f}'
            . 'code{background:#FAF9F6;padding:2px 6px;border-radius:6px}.error-detail{font-size:.85rem;word-break:break-word;color:#8a3b32}'
            . 'a{color:#234B45}</style></head><body><div class="box"><h1>Serviço temporariamente indisponível</h1>'
            . '<p>Não foi possível estabelecer ligação à base de dados da LumaStay. Verifique se o MySQL/MariaDB está ativo e se as credenciais em <code>config/config.local.php</code> estão corretas.</p>'
            . $detail . '</div></body></html>';
        exit;
    }

    return $pdo;
}
