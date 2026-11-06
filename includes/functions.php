<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

date_default_timezone_set((string) lumastay_config_value('timezone', 'Europe/Lisbon'));

/**
 * Escapa texto para utilização segura em HTML.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * URL base da aplicação, calculada a partir do script atual.
 * Exemplo: /lumastay  (ou vazio quando a app está na raiz do servidor).
 */
function base_url(): string
{
    static $base = null;

    if ($base !== null) {
        return $base;
    }

    $configured = trim((string) lumastay_config_value('app_url', ''));
    if ($configured !== '') {
        $base = rtrim($configured, '/');
        return $base;
    }

    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $directory = str_replace('\\', '/', dirname($scriptName));

    if (basename($directory) === 'admin') {
        $directory = dirname($directory);
    }

    if ($directory === '/' || $directory === '.' || $directory === '\\') {
        $directory = '';
    }

    $base = rtrim($directory, '/');
    return $base;
}

function url(string $path = ''): string
{
    return base_url() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function input_string(string $key, string $source = 'post', string $default = ''): string
{
    $data = $source === 'get' ? $_GET : $_POST;
    $value = $data[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function input_int(string $key, string $source = 'post', ?int $default = null): ?int
{
    $value = input_string($key, $source, '');
    if ($value === '' || !preg_match('/^-?\d+$/', $value)) {
        return $default;
    }
    return (int) $value;
}

function set_flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return [];
    }
    $flashes = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return is_array($flashes) ? $flashes : [];
}

function render_flashes(): string
{
    $flashes = get_flashes();
    if ($flashes === []) {
        return '';
    }

    $html = '<div class="flash-stack" aria-live="polite" aria-atomic="true">';
    foreach ($flashes as $flash) {
        $type = in_array($flash['type'] ?? '', ['success', 'error', 'warning', 'info'], true)
            ? $flash['type']
            : 'info';
        $icon = match ($type) {
            'success' => 'check',
            'error'   => 'alert',
            'warning' => 'alert',
            default   => 'info',
        };
        $html .= '<div class="alert alert-' . e($type) . '" role="' . ($type === 'error' ? 'alert' : 'status') . '">'
            . icon($icon, 'alert-icon')
            . '<p>' . e((string) ($flash['message'] ?? '')) . '</p>'
            . '<button type="button" class="alert-close" data-dismiss-alert aria-label="Fechar mensagem">'
            . icon('close') . '</button></div>';
    }
    $html .= '</div>';
    return $html;
}

function render_errors(array $errors): string
{
    if ($errors === []) {
        return '';
    }

    $html = '<div class="alert alert-error alert-validation" role="alert">'
        . icon('alert', 'alert-icon')
        . '<div><strong>Corrija os seguintes pontos:</strong><ul>';
    foreach ($errors as $error) {
        $html .= '<li>' . e((string) $error) . '</li>';
    }
    return $html . '</ul></div></div>';
}

function money(float|int|string $amount): string
{
    return number_format((float) $amount, 2, ',', ' ') . ' €';
}

function format_date(string $date, bool $long = false): string
{
    if ($date === '') {
        return '—';
    }

    try {
        $dt = new DateTimeImmutable($date);
    } catch (Exception) {
        return '—';
    }

    if (!$long) {
        return $dt->format('d/m/Y');
    }

    $months = [
        1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
        5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
        9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
    ];

    return $dt->format('j') . ' de ' . $months[(int) $dt->format('n')] . ' de ' . $dt->format('Y');
}

function today(): string
{
    return date('Y-m-d');
}

function validate_date_value(string $value): bool
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
        return false;
    }

    return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
}

function nights_between(string $checkIn, string $checkOut): int
{
    if (!validate_date_value($checkIn) || !validate_date_value($checkOut)) {
        return 0;
    }

    $in = new DateTimeImmutable($checkIn);
    $out = new DateTimeImmutable($checkOut);

    if ($out <= $in) {
        return 0;
    }

    return (int) $in->diff($out)->days;
}

function validate_email_value(string $email): bool
{
    return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 190;
}

function normalize_phone(string $phone): string
{
    return preg_replace('/[^0-9+]/', '', $phone) ?? '';
}

function validate_phone_value(string $phone): bool
{
    $normalized = normalize_phone($phone);
    return (bool) preg_match('/^\+?[0-9]{9,15}$/', $normalized);
}

function slugify(string $text): string
{
    $text = trim($text);
    $replacements = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'Á' => 'a', 'À' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a', 'Å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'É' => 'e', 'È' => 'e', 'Ê' => 'e', 'Ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'Í' => 'i', 'Ì' => 'i', 'Î' => 'i', 'Ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'Ó' => 'o', 'Ò' => 'o', 'Ô' => 'o', 'Õ' => 'o', 'Ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'Ú' => 'u', 'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u',
        'ç' => 'c', 'Ç' => 'c', 'ñ' => 'n', 'Ñ' => 'n',
    ];

    $text = strtr($text, $replacements);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-');
}

function excerpt(string $text, int $limit = 150): string
{
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') <= $limit) {
            return $text;
        }
        $cut = mb_substr($text, 0, $limit + 1, 'UTF-8');
    } else {
        if (strlen($text) <= $limit) {
            return $text;
        }
        $cut = substr($text, 0, $limit + 1);
    }

    $lastSpace = strrpos($cut, ' ');
    if ($lastSpace !== false && $lastSpace > (int) ($limit * 0.6)) {
        $cut = substr($cut, 0, $lastSpace);
    }

    return rtrim($cut, " \t\n\r\0\x0B.,;:") . '…';
}

function image_src(?string $path): string
{
    $path = trim((string) $path);

    if ($path === '') {
        return asset('images/fallback.svg');
    }

    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    if (str_starts_with($path, 'assets/')) {
        return url($path);
    }

    return asset('images/' . ltrim($path, '/'));
}

function amenities_decode(mixed $amenities): array
{
    if (is_array($amenities)) {
        return array_values(array_filter(array_map('strval', $amenities)));
    }

    if (!is_string($amenities) || trim($amenities) === '') {
        return [];
    }

    $decoded = json_decode($amenities, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map('strval', $decoded)));
    }

    return array_values(array_filter(array_map('trim', explode(',', $amenities))));
}

/**
 * Valida e normaliza os dados do formulário de alojamento.
 *
 * @return array{data: array<string, mixed>, errors: array<int, string>}
 */
function validate_accommodation_form(array $input, ?int $excludeId = null): array
{
    $errors = [];
    $textLength = static function (string $value): int {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    };
    $decimal = static function (string $value): ?float {
        $value = trim(str_replace([' ', '€'], '', $value));
        $value = str_replace(',', '.', $value);
        return is_numeric($value) ? (float) $value : null;
    };

    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '' || $textLength($name) < 3 || $textLength($name) > 150) {
        $errors[] = 'O nome deve ter entre 3 e 150 caracteres.';
    }

    $slugInput = trim((string) ($input['slug'] ?? ''));
    $slug = slugify($slugInput !== '' ? $slugInput : $name);
    if ($slug === '' || strlen($slug) > 170) {
        $errors[] = 'O slug não é válido. Utilize letras, números e hífenes (máx. 170 caracteres).';
    } else {
        $slugSql = 'SELECT COUNT(*) FROM accommodations WHERE slug = :slug';
        $slugParams = [':slug' => $slug];
        if ($excludeId !== null) {
            $slugSql .= ' AND id <> :id';
            $slugParams[':id'] = $excludeId;
        }
        $slugStmt = db()->prepare($slugSql);
        $slugStmt->execute($slugParams);
        if ((int) $slugStmt->fetchColumn() > 0) {
            $errors[] = 'Já existe um alojamento com este slug. Escolha outro identificador.';
        }
    }

    $description = trim((string) ($input['description'] ?? ''));
    if ($textLength($description) < 30 || $textLength($description) > 5000) {
        $errors[] = 'A descrição deve ter entre 30 e 5000 caracteres.';
    }

    $location = trim((string) ($input['location'] ?? ''));
    if ($location === '' || $textLength($location) > 120) {
        $errors[] = 'Indique a localização (máx. 120 caracteres).';
    }

    $address = trim((string) ($input['address'] ?? ''));
    if ($address === '' || $textLength($address) > 255) {
        $errors[] = 'Indique a morada completa (máx. 255 caracteres).';
    }

    $type = trim((string) ($input['type'] ?? ''));
    if (!array_key_exists($type, accommodation_types())) {
        $errors[] = 'Selecione um tipo de alojamento válido.';
    }

    $price = $decimal((string) ($input['price_per_night'] ?? ''));
    if ($price === null || $price <= 0 || $price > 100000) {
        $errors[] = 'O preço por noite deve ser um valor entre 0,01 € e 100 000 €.';
        $price = 0.0;
    }

    $capacity = filter_var($input['capacity'] ?? null, FILTER_VALIDATE_INT);
    if ($capacity === false || $capacity < 1 || $capacity > 30) {
        $errors[] = 'A capacidade deve estar entre 1 e 30 hóspedes.';
        $capacity = 0;
    }

    $bedrooms = filter_var($input['bedrooms'] ?? null, FILTER_VALIDATE_INT);
    if ($bedrooms === false || $bedrooms < 0 || $bedrooms > 20) {
        $errors[] = 'O número de quartos deve estar entre 0 e 20.';
        $bedrooms = 0;
    }

    $bathrooms = filter_var($input['bathrooms'] ?? null, FILTER_VALIDATE_INT);
    if ($bathrooms === false || $bathrooms < 1 || $bathrooms > 20) {
        $errors[] = 'O número de casas de banho deve estar entre 1 e 20.';
        $bathrooms = 0;
    }

    $amenitiesRaw = trim((string) ($input['amenities'] ?? ''));
    $amenities = preg_split('/\s*[,;\n]+\s*/u', $amenitiesRaw) ?: [];
    $amenities = array_values(array_unique(array_filter(array_map('trim', $amenities), static fn (string $item): bool => $item !== '')));
    if (count($amenities) < 1 || count($amenities) > 20) {
        $errors[] = 'Indique entre 1 e 20 comodidades, separadas por vírgulas.';
    }
    foreach ($amenities as $amenity) {
        if ($textLength($amenity) > 80) {
            $errors[] = 'Cada comodidade deve ter no máximo 80 caracteres.';
            break;
        }
    }

    $imageUrl = trim((string) ($input['image_url'] ?? ''));
    $hasScheme = (bool) preg_match('#^[a-z][a-z0-9+.-]*:#i', $imageUrl);
    if ($imageUrl === '' || $textLength($imageUrl) > 255
        || str_contains($imageUrl, '..')
        || ($hasScheme && !preg_match('#^https?://#i', $imageUrl))) {
        $errors[] = 'Indique um caminho local (ex.: assets/images/alojamentos/lisboa.svg) ou um URL http(s) válido para a imagem.';
    }

    $rating = $decimal((string) ($input['rating'] ?? '4.8'));
    if ($rating === null || $rating < 0 || $rating > 5) {
        $errors[] = 'A avaliação deve estar entre 0 e 5.';
        $rating = 4.8;
    }

    $reviewCount = filter_var($input['review_count'] ?? 0, FILTER_VALIDATE_INT);
    if ($reviewCount === false || $reviewCount < 0 || $reviewCount > 100000) {
        $errors[] = 'O número de avaliações deve estar entre 0 e 100 000.';
        $reviewCount = 0;
    }

    $active = isset($input['active']) && (string) $input['active'] === '1' ? 1 : 0;
    $featured = isset($input['featured']) && (string) $input['featured'] === '1' ? 1 : 0;

    if ($featured === 1 && $active === 0) {
        $errors[] = 'Um alojamento inativo não pode estar em destaque no site público.';
    }

    return [
        'data' => [
            'name'            => $name,
            'slug'            => $slug,
            'description'     => $description,
            'location'        => $location,
            'address'         => $address,
            'type'            => $type,
            'price_per_night' => round($price, 2),
            'capacity'        => (int) $capacity,
            'bedrooms'        => (int) $bedrooms,
            'bathrooms'       => (int) $bathrooms,
            'amenities'       => json_encode($amenities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
            'image_url'       => $imageUrl,
            'rating'          => round($rating, 1),
            'review_count'    => (int) $reviewCount,
            'active'          => $active,
            'featured'        => $featured,
        ],
        'errors' => $errors,
    ];
}


function amenity_icon(string $amenity): string
{
    $value = strtolower($amenity);
    $map = [
        'wifi'            => 'wifi',
        'wi-fi'           => 'wifi',
        'piscina'         => 'pool',
        'pequeno-almoço'  => 'coffee',
        'pequeno-almoco'  => 'coffee',
        'estacionamento'  => 'car',
        'lareira'         => 'flame',
        'ar condicionado' => 'snow',
        'praia'           => 'beach',
        'jardim'          => 'leaf',
        'churrasqueira'   => 'grill',
        'máquina de lavar'=> 'washer',
        'maquina de lavar'=> 'washer',
        'berço'           => 'baby',
        'berco'           => 'baby',
        'cozinha'         => 'kitchen',
        'kitchenette'     => 'kitchen',
        'vista'           => 'eye',
        'check-in'        => 'key',
        'varanda'         => 'sun',
        'terraço'         => 'sun',
        'terraco'         => 'sun',
        'pomar'           => 'leaf',
        'olival'          => 'leaf',
        'acesso à praia'  => 'beach',
    ];

    foreach ($map as $needle => $iconName) {
        if (str_contains($value, $needle)) {
            return $iconName;
        }
    }

    return 'check';
}

function accommodation_types(): array
{
    return [
        'apartamento' => 'Apartamento',
        'casa'        => 'Casa',
        'villa'       => 'Villa',
        'quinta'      => 'Quinta',
        'suite'       => 'Suite',
        'loft'        => 'Loft',
        'refúgio'     => 'Refúgio',
    ];
}

function type_label(string $type): string
{
    return accommodation_types()[$type] ?? ucfirst($type);
}

function reservation_statuses(): array
{
    return [
        'pendente'  => ['label' => 'Pendente', 'class' => 'warning'],
        'confirmada'=> ['label' => 'Confirmada', 'class' => 'success'],
        'cancelada' => ['label' => 'Cancelada', 'class' => 'danger'],
        'concluida' => ['label' => 'Concluída', 'class' => 'neutral'],
    ];
}

function status_badge(string $status): string
{
    $meta = reservation_statuses()[$status] ?? ['label' => ucfirst($status), 'class' => 'neutral'];
    return '<span class="badge badge-' . e($meta['class']) . '">' . e($meta['label']) . '</span>';
}

function is_active_nav(string $page, string $current): bool
{
    return $page === $current;
}

/**
 * Verifica a disponibilidade de um alojamento para um intervalo.
 * Reservas canceladas e concluídas não bloqueiam novas datas.
 */
function accommodation_is_available(int $accommodationId, string $checkIn, string $checkOut, ?int $excludeReservationId = null): bool
{
    $sql = "SELECT COUNT(*)
            FROM reservations
            WHERE accommodation_id = :accommodation_id
              AND status IN ('pendente', 'confirmada')
              AND check_in < :check_out
              AND check_out > :check_in";

    $params = [
        ':accommodation_id' => $accommodationId,
        ':check_in'         => $checkIn,
        ':check_out'        => $checkOut,
    ];

    if ($excludeReservationId !== null) {
        $sql .= ' AND id <> :exclude_id';
        $params[':exclude_id'] = $excludeReservationId;
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() === 0;
}

function find_accommodation(int $id, bool $includeInactive = false): array|false
{
    if ($id <= 0) {
        return false;
    }

    $sql = 'SELECT * FROM accommodations WHERE id = :id';
    if (!$includeInactive) {
        $sql .= ' AND active = 1';
    }
    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute([':id' => $id]);
    $accommodation = $stmt->fetch();

    return is_array($accommodation) ? $accommodation : false;
}

function generate_reservation_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $length = strlen($alphabet);

    for ($attempt = 0; $attempt < 12; $attempt++) {
        $code = 'LS-';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, $length - 1)];
        }

        $stmt = db()->prepare('SELECT COUNT(*) FROM reservations WHERE reservation_code = :code');
        $stmt->execute([':code' => $code]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $code;
        }
    }

    return 'LS-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Aplica uma transição de estado permitida a uma reserva.
 *
 * @return array{ok: bool, message: string}
 */
function perform_reservation_action(int $reservationId, string $action): array
{
    $allowedActions = ['confirm', 'cancel', 'complete'];
    if ($reservationId <= 0 || !in_array($action, $allowedActions, true)) {
        return ['ok' => false, 'message' => 'Ação inválida.'];
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'SELECT r.*, a.name AS accommodation_name
             FROM reservations r
             INNER JOIN accommodations a ON a.id = r.accommodation_id
             WHERE r.id = :id
             FOR UPDATE'
        );
        $stmt->execute([':id' => $reservationId]);
        $reservation = $stmt->fetch();

        if (!is_array($reservation)) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'A reserva indicada não existe.'];
        }

        $currentStatus = (string) $reservation['status'];
        $newStatus = null;
        $message = '';

        if ($action === 'confirm') {
            if ($currentStatus !== 'pendente') {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Apenas reservas pendentes podem ser confirmadas.'];
            }
            if (!accommodation_is_available(
                (int) $reservation['accommodation_id'],
                (string) $reservation['check_in'],
                (string) $reservation['check_out'],
                (int) $reservation['id']
            )) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Existe outra reserva ativa a sobrepor-se a estas datas. A confirmação foi bloqueada.'];
            }
            $newStatus = 'confirmada';
            $message = 'Reserva ' . $reservation['reservation_code'] . ' confirmada.';
        }

        if ($action === 'cancel') {
            if (!in_array($currentStatus, ['pendente', 'confirmada'], true)) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Só é possível cancelar reservas pendentes ou confirmadas.'];
            }
            $newStatus = 'cancelada';
            $message = 'Reserva ' . $reservation['reservation_code'] . ' cancelada. As datas foram libertadas.';
        }

        if ($action === 'complete') {
            if ($currentStatus !== 'confirmada') {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Apenas reservas confirmadas podem ser marcadas como concluídas.'];
            }
            if ((string) $reservation['check_out'] > today()) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'A reserva só pode ser concluída após a data de saída.'];
            }
            $newStatus = 'concluida';
            $message = 'Reserva ' . $reservation['reservation_code'] . ' marcada como concluída.';
        }

        $update = $pdo->prepare('UPDATE reservations SET status = :status WHERE id = :id AND status = :current_status');
        $update->execute([
            ':status'         => $newStatus,
            ':id'             => $reservationId,
            ':current_status' => $currentStatus,
        ]);

        if ($update->rowCount() !== 1) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'O estado da reserva foi alterado entretanto. Recarregue a página.'];
        }

        $pdo->commit();
        return ['ok' => true, 'message' => $message];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[LumaStay] Erro ao alterar reserva: ' . $exception->getMessage());
        return ['ok' => false, 'message' => 'Não foi possível atualizar a reserva. Tente novamente.'];
    }
}


function current_query(array $overrides = [], array $remove = []): string
{
    $params = array_merge($_GET, $overrides);
    foreach ($remove as $key) {
        unset($params[$key]);
    }
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($params[$key]);
        }
    }
    return http_build_query($params);
}

function selected(string|int|null $value, string|int|null $expected): string
{
    return (string) $value === (string) $expected ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

function rating_badge(float|string $rating, int $reviews = 0): string
{
    $html = '<span class="rating-badge">' . icon('star', 'rating-star') . '<strong>' . e(number_format((float) $rating, 1, ',', '')) . '</strong>';
    if ($reviews > 0) {
        $html .= '<span class="rating-count">(' . e($reviews) . ')</span>';
    }
    return $html . '</span>';
}

/**
 * Ícones SVG inline. Sem dependências externas e sem emojis.
 */
function icon(string $name, string $class = ''): string
{
    $paths = [
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.8-3.8"/>',
        'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>',
        'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'      => '<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
        'bed'       => '<path d="M2 4v16M2 9h18a2 2 0 0 1 2 2v9M2 17h20M6 9v8"/>',
        'bath'      => '<path d="M3 12h18v3a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-3Z"/><path d="M5 12V5a2 2 0 0 1 4 0v1M8 19v2M16 19v2"/>',
        'pin'       => '<path d="M21 10c0 7-9 12-9 12S3 17 3 10a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/>',
        'star'      => '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87L18.18 21 12 17.77 5.82 21 7 14.14 2 9.27l6.91-1.01L12 2Z" fill="currentColor" stroke="none"/>',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-left'  => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
        'chevron-left'=> '<path d="m15 18-6-6 6-6"/>',
        'chevron-right'=>'<path d="m9 18 6-6-6-6"/>',
        'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'     => '<path d="M6 6l12 12M18 6 6 18"/>',
        'check'     => '<path d="m4 12 5 5L20 6"/>',
        'shield'    => '<path d="M12 2 4 5v6c0 5 3.4 9.4 8 11 4.6-1.6 8-6 8-11V5l-8-3Z"/><path d="m9 12 2 2 4-4"/>',
        'sparkles'  => '<path d="m12 3 1.6 4.4L18 9l-4.4 1.6L12 15l-1.6-4.4L6 9l4.4-1.6L12 3ZM19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z"/>',
        'leaf'      => '<path d="M20 4C10 4 4 9 4 16c0 2 .7 3.3.7 3.3S8 12 17 9c-6 4-9 8-11 13M4 20c6 0 16-4 16-16Z"/>',
        'wifi'      => '<path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M2 9a15 15 0 0 1 20 0"/><circle cx="12" cy="19" r="1" fill="currentColor" stroke="none"/>',
        'pool'      => '<path d="M3 16c2 0 2-1.5 4-1.5S9 16 11 16s2-1.5 4-1.5S17 16 19 16s2-1.5 2-1.5M3 20c2 0 2-1.5 4-1.5S9 20 11 20s2-1.5 4-1.5S17 20 19 20s2-1.5 2-1.5M8 12V5a2 2 0 0 1 4 0M8 8h8"/>',
        'coffee'    => '<path d="M4 8h14v5a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5V8ZM18 9h1a3 3 0 0 1 0 6h-1M6 2v2M10 2v2M14 2v2"/>',
        'car'       => '<path d="m5 17-1-5 2-5h12l2 5-1 5M3 17h18v4H3v-4ZM7 17v-4h10v4"/>',
        'flame'     => '<path d="M12 22c4 0 7-3 7-7 0-4-2-6-4-9-1 3-3 4-4 6-1-2-2-4-2-6-2 3-4 6-4 9 0 4 3 7 7 7Z"/>',
        'snow'      => '<path d="M12 2v20M3.5 7l17 10M20.5 7l-17 10M9 4l3 2 3-2M9 20l3-2 3 2"/>',
        'beach'     => '<path d="M3 20h18M5 20c0-7 4-13 10-14M12 6c3-1 6 1 7 4M8 20l2-6"/>',
        'grill'     => '<path d="M5 4h14l-2 8H7L5 4ZM7 12v3a5 5 0 0 0 10 0v-3M12 20v2M4 2l1 2M20 2l-1 2"/>',
        'washer'    => '<rect x="3" y="2" width="18" height="20" rx="2"/><circle cx="12" cy="13" r="5"/><path d="M7 6h.01M11 6h.01"/>',
        'baby'      => '<circle cx="12" cy="8" r="5"/><path d="M9 8h.01M15 8h.01M9.5 11c1.5 1 3.5 1 5 0M7 22v-4a5 5 0 0 1 10 0v4"/>',
        'kitchen'   => '<path d="M4 3v7a3 3 0 0 0 6 0V3M7 10v11M17 3c-2 3-2 7 0 9v9M17 3v9"/>',
        'eye'       => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'key'       => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 3l3 3M14 6l3 3"/>',
        'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'home'      => '<path d="m3 11 9-8 9 8M5 9v12h5v-6h4v6h5V9"/>',
        'building'  => '<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16M16 9h3a2 2 0 0 1 2 2v10M8 7h4M8 11h4M8 15h4M2 21h20"/>',
        'chart'     => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 4V2h6v2M9 10h6M9 14h6M9 18h4"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.7 2.7-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-3.8v-.2a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1-2.7-2.7.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.6-1H3v-3.8h.2a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1 2.7-2.7.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.6V3h3.8v.2a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1 2.7 2.7-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v3.8h-.2a1.7 1.7 0 0 0-1.6 1Z"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'edit'      => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5Z"/>',
        'trash'     => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 15H6L5 6M10 11v6M14 11v6"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'power'     => '<path d="M12 2v10M18.4 5.6a9 9 0 1 1-12.8 0"/>',
        'alert'     => '<path d="M12 3 2 21h20L12 3Z"/><path d="M12 9v5M12 18h.01"/>',
        'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'phone'     => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8 10a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c1 .3 1.9.6 2.9.7a2 2 0 0 1 1.7 2Z"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'filter'    => '<path d="M3 5h18l-7 8v6l-4 2v-8L3 5Z"/>',
        'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'lock'      => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'tag'       => '<path d="m20.6 13.6-7 7a2 2 0 0 1-2.8 0L3 12.8V3h9.8l7.8 7.8a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.2" fill="currentColor" stroke="none"/>',
        'moon'      => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>',
    ];

    $allowed = ['search','calendar','users','user','bed','bath','pin','star','arrow-right','arrow-left','chevron-left','chevron-right','menu','close','check','shield','sparkles','leaf','wifi','pool','coffee','car','flame','snow','beach','grill','washer','baby','kitchen','eye','key','sun','home','building','chart','clipboard','settings','logout','edit','trash','plus','power','alert','info','mail','phone','clock','filter','grid','lock','tag','moon'];
    $key = in_array($name, $allowed, true) ? $name : 'info';
    $body = $paths[$key];
    $classAttribute = $class !== '' ? ' ' . e($class) : '';

    return '<svg class="icon' . $classAttribute . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $body . '</svg>';
}
