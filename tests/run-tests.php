<?php
declare(strict_types=1);

/**
 * LumaStay — suite de testes.
 *
 * Executa testes unitários às funções de domínio, testes de integridade
 * contra a base de dados MySQL e verificações HTTP opcionais.
 *
 * Uso (PowerShell, Laragon):
 *   $env:DB_HOST='127.0.0.1'; $env:DB_PORT='3306'; $env:DB_NAME='lumastay'
 *   $env:DB_USER='root'; $env:DB_PASSWORD=''
 *   php tests/run-tests.php
 *
 * Variável opcional:
 *   APP_TEST_URL=http://127.0.0.1:8088  (se ausente, os testes HTTP são ignorados)
 *
 * Código de saída: 0 = todos os testes passaram; 1 = pelo menos uma falha.
 */

// Valores por omissão apenas quando a variável não está definida.
$defaults = [
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_NAME' => 'lumastay',
    'DB_USER' => 'root',
];
foreach ($defaults as $key => $value) {
    if (getenv($key) === false) {
        putenv($key . '=' . $value);
    }
}

require_once __DIR__ . '/../includes/functions.php';

/* ------------------------------------------------------------------
 * Mini framework de testes
 * ------------------------------------------------------------------ */

final class TestFailure extends RuntimeException
{
}

$GLOBALS['lumastay_tests'] = [
    'passed'  => 0,
    'failed'  => 0,
    'skipped' => 0,
    'failures'=> [],
];

function test(string $name, callable $callback): void
{
    try {
        $callback();
        $GLOBALS['lumastay_tests']['passed']++;
        echo "  [PASS] {$name}\n";
    } catch (TestFailure $failure) {
        $GLOBALS['lumastay_tests']['failed']++;
        $GLOBALS['lumastay_tests']['failures'][] = $name . ' — ' . $failure->getMessage();
        echo "  [FAIL] {$name}\n         {$failure->getMessage()}\n";
    } catch (Throwable $error) {
        $GLOBALS['lumastay_tests']['failed']++;
        $GLOBALS['lumastay_tests']['failures'][] = $name . ' — ' . get_class($error) . ': ' . $error->getMessage();
        echo "  [FAIL] {$name}\n         " . get_class($error) . ': ' . $error->getMessage() . "\n";
    }
}

function skip(string $name, string $reason): void
{
    $GLOBALS['lumastay_tests']['skipped']++;
    echo "  [SKIP] {$name} ({$reason})\n";
}

function assert_true(bool $condition, string $message = 'esperava true'): void
{
    if (!$condition) {
        throw new TestFailure($message);
    }
}

function assert_false(bool $condition, string $message = 'esperava false'): void
{
    if ($condition) {
        throw new TestFailure($message);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $detail = $message !== '' ? $message . ': ' : '';
        throw new TestFailure($detail . 'esperado ' . var_export($expected, true) . ', obtido ' . var_export($actual, true));
    }
}

function assert_contains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        $detail = $message !== '' ? $message . ': ' : '';
        throw new TestFailure($detail . 'não encontrou "' . $needle . '"');
    }
}

function assert_not_contains(string $needle, string $haystack, string $message = ''): void
{
    if (str_contains($haystack, $needle)) {
        throw new TestFailure(($message !== '' ? $message . ': ' : '') . 'encontrou inesperadamente "' . $needle . '"');
    }
}

function assert_greater_or_equal(int|float $minimum, int|float $actual, string $message = ''): void
{
    if ($actual < $minimum) {
        throw new TestFailure(($message !== '' ? $message . ': ' : '') . 'esperado >= ' . $minimum . ', obtido ' . $actual);
    }
}

function section(string $title): void
{
    echo "\n" . $title . "\n" . str_repeat('-', mb_strlen($title)) . "\n";
}

function http_status(string $url): ?int
{
    $context = stream_context_create([
        'http' => [
            'method'          => 'GET',
            'ignore_errors'   => true,
            'follow_location' => 0,
            'timeout'         => 6,
            'header'          => "User-Agent: LumaStayTest/1.0\r\n",
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false || !isset($http_response_header[0])) {
        return null;
    }

    if (preg_match('#HTTP/\S+\s+(\d{3})#', (string) $http_response_header[0], $matches)) {
        return (int) $matches[1];
    }

    return null;
}

/* ------------------------------------------------------------------
 * 1. Funções utilitárias
 * ------------------------------------------------------------------ */

section('1. Funções utilitárias');

test('e() escapa HTML perigoso', function (): void {
    assert_same('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', e('<script>alert("x")</script>'));
});

test('money() usa formato português', function (): void {
    assert_same('555,00 €', money(555));
    assert_same('1 234,50 €', money(1234.5));
    assert_same('0,00 €', money(0));
});

test('format_date() curto e longo', function (): void {
    assert_same('01/06/2027', format_date('2027-06-01'));
    assert_same('1 de junho de 2027', format_date('2027-06-01', true));
    assert_same('—', format_date(''));
    assert_same('—', format_date('data-invalida'));
});

test('validate_date_value() aceita e rejeita corretamente', function (): void {
    assert_true(validate_date_value('2027-06-01'));
    assert_true(validate_date_value('2024-02-29'), '2024 é bissexto');
    assert_false(validate_date_value('2023-02-29'), '2023 não é bissexto');
    assert_false(validate_date_value('2027-13-01'));
    assert_false(validate_date_value('01/06/2027'));
    assert_false(validate_date_value(''));
});

test('nights_between() calcula noites', function (): void {
    assert_same(3, nights_between('2027-06-01', '2027-06-04'));
    assert_same(1, nights_between('2027-06-01', '2027-06-02'));
    assert_same(0, nights_between('2027-06-04', '2027-06-01'));
    assert_same(0, nights_between('2027-06-01', '2027-06-01'));
    assert_same(0, nights_between('invalida', '2027-06-01'));
});

test('validação de email', function (): void {
    assert_true(validate_email_value('mariana.costa@example.pt'));
    assert_true(validate_email_value('a@b.co'));
    assert_false(validate_email_value('sem-arroba.pt'));
    assert_false(validate_email_value('a@b'));
    assert_false(validate_email_value(''));
});

test('normalização e validação de telefone', function (): void {
    assert_same('+351912345678', normalize_phone('+351 912 345 678'));
    assert_same('912345678', normalize_phone('912-345-678'));
    assert_true(validate_phone_value('+351 912 345 678'));
    assert_true(validate_phone_value('912345678'));
    assert_false(validate_phone_value('12345'));
    assert_false(validate_phone_value('telefone'));
});

test('slugify() remove acentos e símbolos', function (): void {
    assert_same('casa-da-luz', slugify('Casa da Luz'));
    assert_same('refugio-do-douro', slugify('Refúgio do Douro'));
    assert_same('quinta-acao-2026', slugify('Quinta — Ação 2026'));
    assert_same('', slugify('!!!'));
});

test('excerpt() limita e limpa texto', function (): void {
    $result = excerpt('<p>' . str_repeat('palavra ', 40) . '</p>', 40);
    assert_true(str_ends_with($result, '…'), 'deve terminar em reticências');
    assert_true(mb_strlen($result) <= 45, 'deve respeitar o limite aproximado');
    assert_same('Texto curto', excerpt('<strong>Texto curto</strong>'));
});

test('amenities_decode() aceita JSON e CSV', function (): void {
    assert_same(['Wi-Fi', 'Piscina'], amenities_decode('["Wi-Fi","Piscina"]'));
    assert_same(['Wi-Fi', 'Piscina'], amenities_decode('Wi-Fi, Piscina'));
    assert_same([], amenities_decode(''));
    assert_same(['Wi-Fi'], amenities_decode(['Wi-Fi']));
});

test('image_src() resolve caminhos locais, URLs e fallback', function (): void {
    assert_contains('assets/images/fallback.svg', image_src(''));
    assert_contains('assets/images/alojamentos/lisboa.svg', image_src('assets/images/alojamentos/lisboa.svg'));
    assert_same('https://example.pt/foto.jpg', image_src('https://example.pt/foto.jpg'));
    assert_contains('assets/images/lisboa.svg', image_src('lisboa.svg'));
});

test('type_label() e estado de reserva', function (): void {
    assert_same('Quinta', type_label('quinta'));
    assert_same('Inexistente', type_label('inexistente'));
    foreach (['pendente', 'confirmada', 'cancelada', 'concluida'] as $status) {
        assert_true(isset(reservation_statuses()[$status]), 'estado em falta: ' . $status);
    }
});

test('icon() devolve SVG e usa fallback para nomes desconhecidos', function (): void {
    assert_contains('<svg', icon('star'));
    assert_contains('<svg', icon('nome-que-nao-existe'));
    assert_contains('class="icon extra"', icon('star', 'extra'));
});

/* ------------------------------------------------------------------
 * 2. Validação do formulário de alojamento
 * ------------------------------------------------------------------ */

section('2. Validação do formulário de alojamento');

test('formulário válido não produz erros', function (): void {
    $result = validate_accommodation_form([
        'name'            => 'Casa de Teste Unitário',
        'slug'            => 'casa-de-teste-unitario',
        'description'     => 'Descrição suficientemente longa para validar o formulário de teste unitário.',
        'location'        => 'Porto',
        'address'         => 'Rua de Teste 10, 4000-000 Porto',
        'type'            => 'casa',
        'price_per_night' => '150,50',
        'capacity'        => '4',
        'bedrooms'        => '2',
        'bathrooms'       => '1',
        'amenities'       => 'Wi-Fi, Cozinha',
        'image_url'       => 'assets/images/alojamentos/porto.svg',
        'rating'          => '4,8',
        'review_count'    => '10',
        'active'          => '1',
        'featured'        => '1',
    ]);

    assert_same([], $result['errors']);
    assert_same(150.50, $result['data']['price_per_night']);
    assert_same(4.8, $result['data']['rating']);
    assert_same('["Wi-Fi","Cozinha"]', $result['data']['amenities']);
});

test('formulário inválido acumula erros', function (): void {
    $result = validate_accommodation_form([
        'name'            => 'ab',
        'slug'            => 'slug-invalido',
        'description'     => 'curta',
        'location'        => '',
        'address'         => '',
        'type'            => 'inexistente',
        'price_per_night' => 'abc',
        'capacity'        => '0',
        'bedrooms'        => '-1',
        'bathrooms'       => '0',
        'amenities'       => '',
        'image_url'       => 'javascript:alert(1)',
        'rating'          => '9',
        'review_count'    => '-5',
        'active'          => '0',
        'featured'        => '1',
    ]);

    assert_greater_or_equal(8, count($result['errors']), 'deve acumular vários erros');
    $joined = implode(' | ', $result['errors']);
    assert_contains('nome', $joined);
    assert_contains('descrição', $joined);
    assert_contains('preço', $joined);
    assert_contains('capacidade', $joined);
    assert_contains('avaliação', $joined);
    assert_contains('destaque', $joined);
});

test('slug duplicado é rejeitado', function (): void {
    $result = validate_accommodation_form([
        'name'            => 'Casa da Luz Duplicada',
        'slug'            => 'casa-da-luz',
        'description'     => 'Descrição suficientemente longa para validar o slug duplicado.',
        'location'        => 'Lisboa',
        'address'         => 'Rua 1, Lisboa',
        'type'            => 'apartamento',
        'price_per_night' => '100',
        'capacity'        => '2',
        'bedrooms'        => '1',
        'bathrooms'       => '1',
        'amenities'       => 'Wi-Fi',
        'image_url'       => 'assets/images/alojamentos/lisboa.svg',
        'rating'          => '4.5',
        'review_count'    => '0',
        'active'          => '1',
        'featured'        => '0',
    ]);

    assert_true(in_array('Já existe um alojamento com este slug. Escolha outro identificador.', $result['errors'], true));
});

/* ------------------------------------------------------------------
 * 3. Integridade da base de dados
 * ------------------------------------------------------------------ */

section('3. Integridade da base de dados');

test('ligação PDO está disponível', function (): void {
    assert_true(db() instanceof PDO);
    assert_same('mysql', db()->getAttribute(PDO::ATTR_DRIVER_NAME));
});

test('dados mínimos de demonstração presentes', function (): void {
    assert_greater_or_equal(8, (int) db()->query('SELECT COUNT(*) FROM accommodations')->fetchColumn(), 'alojamentos');
    assert_greater_or_equal(10, (int) db()->query('SELECT COUNT(*) FROM guests')->fetchColumn(), 'hóspedes');
    assert_greater_or_equal(12, (int) db()->query('SELECT COUNT(*) FROM reservations')->fetchColumn(), 'reservas');
    assert_greater_or_equal(1, (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn(), 'administradores');
});

test('existem reservas nos quatro estados', function (): void {
    $rows = db()->query('SELECT status, COUNT(*) AS total FROM reservations GROUP BY status')->fetchAll();
    $statuses = [];
    foreach ($rows as $row) {
        $statuses[(string) $row['status']] = (int) $row['total'];
    }
    foreach (['pendente', 'confirmada', 'cancelada', 'concluida'] as $status) {
        assert_true(($statuses[$status] ?? 0) > 0, 'sem reservas no estado ' . $status);
    }
});

test('preço total guardado é coerente com noites x preço/noite', function (): void {
    $rows = db()->query('SELECT id, reservation_code, check_in, check_out, price_per_night, total_price FROM reservations')->fetchAll();
    assert_true($rows !== [], 'sem reservas para validar');
    foreach ($rows as $row) {
        $nights = nights_between((string) $row['check_in'], (string) $row['check_out']);
        $expected = round($nights * (float) $row['price_per_night'], 2);
        $actual = round((float) $row['total_price'], 2);
        if (abs($expected - $actual) > 0.01) {
            throw new TestFailure('reserva ' . $row['reservation_code'] . ': esperado ' . $expected . ', obtido ' . $actual);
        }
    }
});

test('disponibilidade: reservas canceladas e concluídas não bloqueiam', function (): void {
    assert_true(accommodation_is_available(3, '2026-11-08', '2026-11-11'), 'cancelada não deve bloquear');
    assert_true(accommodation_is_available(6, '2026-08-10', '2026-08-15'), 'concluída não deve bloquear');
});

test('disponibilidade: reservas pendentes e confirmadas bloqueiam', function (): void {
    assert_false(accommodation_is_available(2, '2026-11-20', '2026-11-23'), 'pendente deve bloquear');
    assert_false(accommodation_is_available(4, '2026-11-12', '2026-11-16'), 'confirmada deve bloquear');
});

test('disponibilidade: exclusão da própria reserva permite confirmar', function (): void {
    assert_true(accommodation_is_available(4, '2026-11-12', '2026-11-16', 4));
});

test('find_accommodation() distingue ativos e inexistentes', function (): void {
    assert_true(find_accommodation(1) !== false, 'alojamento 1 deve existir');
    assert_false(find_accommodation(999999), 'id inexistente');
    assert_false(find_accommodation(0), 'id zero');
});

test('generate_reservation_code() gera código único com formato LS-XXXXXX', function (): void {
    $codes = [];
    for ($i = 0; $i < 5; $i++) {
        $code = generate_reservation_code();
        assert_true((bool) preg_match('/^LS-[A-Z2-9]{6}$/', $code), 'formato inválido: ' . $code);
        assert_false(isset($codes[$code]), 'código repetido: ' . $code);
        $codes[$code] = true;

        $stmt = db()->prepare('SELECT COUNT(*) FROM reservations WHERE reservation_code = :code');
        $stmt->execute([':code' => $code]);
        assert_same(0, (int) $stmt->fetchColumn(), 'código já existente na base de dados');
    }
});

test('perform_reservation_action() rejeita transições inválidas sem alterar dados', function (): void {
    $before = db()->query('SELECT status FROM reservations WHERE id = 1')->fetchColumn();

    $missing = perform_reservation_action(999999, 'confirm');
    assert_false($missing['ok'], 'reserva inexistente');

    $invalidAction = perform_reservation_action(1, 'invalida');
    assert_false($invalidAction['ok'], 'ação inválida');

    $wrongTransition = perform_reservation_action(1, 'confirm');
    assert_false($wrongTransition['ok'], 'concluída não pode ser confirmada');

    $after = db()->query('SELECT status FROM reservations WHERE id = 1')->fetchColumn();
    assert_same($before, $after, 'o estado não deve mudar');
});

test('administrador de demonstração existe com hash válido', function (): void {
    $stmt = db()->prepare('SELECT password_hash FROM admin_users WHERE email = :email');
    $stmt->execute([':email' => 'admin@lumastay.pt']);
    $hash = $stmt->fetchColumn();
    assert_true(is_string($hash) && $hash !== '', 'hash ausente');
    assert_true(password_verify('LumaStay2026!', $hash), 'a palavra-passe de demonstração deve validar');
    assert_false(password_verify('palavra-passe-errada', $hash));
});

/* ------------------------------------------------------------------
 * 4. Verificações HTTP (opcionais)
 * ------------------------------------------------------------------ */

section('4. Verificações HTTP');

$baseUrl = rtrim((string) getenv('APP_TEST_URL'), '/');
if ($baseUrl === '') {
    skip('Páginas públicas', 'APP_TEST_URL não definido');
    skip('Área administrativa protegida', 'APP_TEST_URL não definido');
} else {
    test('páginas públicas respondem 200', function () use ($baseUrl): void {
        $paths = [
            '/index.php',
            '/alojamentos.php',
            '/alojamento.php?id=1',
            '/reservar.php',
            '/consultar-reserva.php',
            '/login.php',
        ];
        foreach ($paths as $path) {
            $status = http_status($baseUrl . $path);
            if ($status === null) {
                throw new TestFailure('sem resposta para ' . $path);
            }
            assert_same(200, $status, 'HTTP em ' . $path);
        }
    });

    test('páginas administrativas redirecionam visitantes para o login', function () use ($baseUrl): void {
        $paths = [
            '/admin/index.php',
            '/admin/alojamentos.php',
            '/admin/reservas.php',
            '/admin/hospedes.php',
            '/admin/disponibilidade.php',
        ];
        foreach ($paths as $path) {
            $status = http_status($baseUrl . $path);
            if ($status === null) {
                throw new TestFailure('sem resposta para ' . $path);
            }
            assert_same(302, $status, 'HTTP em ' . $path);
        }
    });
}

/* ------------------------------------------------------------------
 * Resumo
 * ------------------------------------------------------------------ */

$results = $GLOBALS['lumastay_tests'];
$total = $results['passed'] + $results['failed'];

echo "\n" . str_repeat('=', 62) . "\n";
echo sprintf("Resultado: %d passaram, %d falharam, %d ignorados (total %d)\n",
    $results['passed'], $results['failed'], $results['skipped'], $total);

if ($results['failed'] > 0) {
    echo "\nFalhas:\n";
    foreach ($results['failures'] as $failure) {
        echo '  - ' . $failure . "\n";
    }
    echo str_repeat('=', 62) . "\n";
    exit(1);
}

echo "Todos os testes executados passaram.\n";
echo str_repeat('=', 62) . "\n";
exit(0);
