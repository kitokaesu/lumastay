<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$code = strtoupper(input_string('codigo', 'get'));
$email = function_exists('mb_strtolower')
    ? mb_strtolower(input_string('email', 'get'), 'UTF-8')
    : strtolower(input_string('email', 'get'));

$reservation = false;

if ($code !== '' && validate_email_value($email)) {
    $stmt = $pdo->prepare(
        "SELECT r.*, a.name AS accommodation_name, a.location AS accommodation_location,
                a.address AS accommodation_address, a.image_url AS accommodation_image,
                g.full_name AS guest_name, g.email AS guest_email, g.phone AS guest_phone
         FROM reservations r
         INNER JOIN accommodations a ON a.id = r.accommodation_id
         INNER JOIN guests g ON g.id = r.guest_id
         WHERE r.reservation_code = :code AND g.email = :email
         LIMIT 1"
    );
    $stmt->execute([':code' => $code, ':email' => $email]);
    $reservation = $stmt->fetch();
}

if (!is_array($reservation)) {
    http_response_code(404);
    $pageTitle = 'Reserva não encontrada';
    $pageDescription = 'Não foi possível encontrar a reserva indicada.';
    $currentPage = 'consultar';
    $bodyClass = 'page-confirmation';
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="section error-page">
        <div class="container error-page-inner">
            <span class="error-page-code">404</span>
            <h1>Não encontrámos esta reserva</h1>
            <p>O código e o email indicados não correspondem a nenhuma reserva. Confirme os dados ou faça uma nova consulta.</p>
            <div class="empty-state-actions">
                <a class="btn btn-primary" href="<?= e(url('consultar-reserva.php')) ?>">Consultar reserva</a>
                <a class="btn btn-ghost" href="<?= e(url('index.php')) ?>">Voltar ao início</a>
            </div>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$nights = nights_between((string) $reservation['check_in'], (string) $reservation['check_out']);
$status = (string) $reservation['status'];

$pageTitle = 'Reserva ' . $reservation['reservation_code'];
$pageDescription = 'Confirmação do pedido de reserva na LumaStay.';
$currentPage = 'consultar';
$bodyClass = 'page-confirmation';

include __DIR__ . '/includes/header.php';
?>

<section class="confirmation-section">
    <div class="container">
        <div class="confirmation-hero">
            <span class="confirmation-check"><?= icon('check') ?></span>
            <p class="eyebrow"><span class="eyebrow-line"></span> Pedido registado</p>
            <h1>Obrigado, <?= e(explode(' ', (string) $reservation['guest_name'])[0]) ?>.</h1>
            <p>O seu pedido de reserva foi guardado com sucesso. Guarde o código abaixo para consultar o estado a qualquer momento.</p>
            <div class="confirmation-code">
                <span>Código de reserva</span>
                <strong><?= e($reservation['reservation_code']) ?></strong>
                <?= status_badge($status) ?>
            </div>
        </div>

        <div class="confirmation-layout">
            <article class="confirmation-card">
                <div class="confirmation-card-head">
                    <img src="<?= e(image_src((string) $reservation['accommodation_image'])) ?>"
                         alt="<?= e($reservation['accommodation_name']) ?>"
                         data-fallback="<?= e(asset('images/fallback.svg')) ?>">
                    <div>
                        <p class="stay-card-location"><?= icon('pin') ?> <?= e($reservation['accommodation_location']) ?></p>
                        <h2><?= e($reservation['accommodation_name']) ?></h2>
                        <a class="text-link" href="<?= e(url('alojamento.php') . '?id=' . (int) $reservation['accommodation_id']) ?>">Ver página do alojamento <?= icon('arrow-right') ?></a>
                    </div>
                </div>

                <dl class="confirmation-details">
                    <div>
                        <dt><?= icon('calendar') ?> Entrada</dt>
                        <dd><?= e(format_date((string) $reservation['check_in'], true)) ?><small>a partir das 15:00</small></dd>
                    </div>
                    <div>
                        <dt><?= icon('calendar') ?> Saída</dt>
                        <dd><?= e(format_date((string) $reservation['check_out'], true)) ?><small>até às 11:00</small></dd>
                    </div>
                    <div>
                        <dt><?= icon('moon') ?> Duração</dt>
                        <dd><?= e($nights) ?> <?= $nights === 1 ? 'noite' : 'noites' ?></dd>
                    </div>
                    <div>
                        <dt><?= icon('users') ?> Hóspedes</dt>
                        <dd><?= e($reservation['guests_count']) ?></dd>
                    </div>
                    <div>
                        <dt><?= icon('user') ?> Titular</dt>
                        <dd><?= e($reservation['guest_name']) ?><small><?= e($reservation['guest_email']) ?></small></dd>
                    </div>
                    <div>
                        <dt><?= icon('phone') ?> Contacto</dt>
                        <dd><?= e($reservation['guest_phone']) ?></dd>
                    </div>
                </dl>

                <div class="confirmation-total">
                    <div>
                        <span><?= e(money((float) $reservation['price_per_night'])) ?> × <?= e($nights) ?> <?= $nights === 1 ? 'noite' : 'noites' ?></span>
                        <strong><?= e(money((float) $reservation['total_price'])) ?></strong>
                    </div>
                    <p>Valor total da estadia. Não foi efetuado qualquer pagamento online.</p>
                </div>

                <div class="confirmation-actions">
                    <a class="btn btn-primary" href="<?= e(url('consultar-reserva.php') . '?codigo=' . rawurlencode((string) $reservation['reservation_code']) . '&email=' . rawurlencode((string) $reservation['guest_email'])) ?>">Consultar esta reserva</a>
                    <a class="btn btn-ghost" href="<?= e(url('alojamentos.php')) ?>">Explorar mais alojamentos</a>
                </div>
            </article>

            <aside class="confirmation-aside">
                <?php if ($status === 'pendente'): ?>
                    <div class="confirmation-note">
                        <span class="confirmation-note-icon"><?= icon('clock') ?></span>
                        <h2>O que acontece agora?</h2>
                        <ol>
                            <li>A equipa LumaStay verifica os detalhes do pedido.</li>
                            <li>O estado passa de <strong>pendente</strong> para <strong>confirmada</strong> após validação.</li>
                            <li>Pode acompanhar a alteração em “Consultar reserva”, usando o código e o email.</li>
                        </ol>
                    </div>
                <?php elseif ($status === 'confirmada'): ?>
                    <div class="confirmation-note confirmation-note-success">
                        <span class="confirmation-note-icon"><?= icon('check') ?></span>
                        <h2>A sua reserva está confirmada</h2>
                        <p>Apresente o código <strong><?= e($reservation['reservation_code']) ?></strong> no check-in. A equipa está disponível para qualquer questão.</p>
                    </div>
                <?php elseif ($status === 'cancelada'): ?>
                    <div class="confirmation-note confirmation-note-danger">
                        <span class="confirmation-note-icon"><?= icon('alert') ?></span>
                        <h2>Esta reserva foi cancelada</h2>
                        <p>As datas foram libertadas. Se precisar de ajuda, contacte <a href="mailto:reservas@lumastay.pt">reservas@lumastay.pt</a>.</p>
                    </div>
                <?php else: ?>
                    <div class="confirmation-note">
                        <span class="confirmation-note-icon"><?= icon('info') ?></span>
                        <h2>Estadia concluída</h2>
                        <p>Esperamos que tenha tido uma boa experiência. O histórico desta reserva continua disponível para consulta.</p>
                    </div>
                <?php endif; ?>

                <div class="confirmation-contact">
                    <h2>Precisa de ajuda?</h2>
                    <p>Fale com a equipa de reservas.</p>
                    <a href="mailto:reservas@lumastay.pt"><?= icon('mail') ?> reservas@lumastay.pt</a>
                    <a href="tel:+351210000240"><?= icon('phone') ?> +351 210 000 240</a>
                </div>
            </aside>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
