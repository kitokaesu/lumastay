<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$errors = [];
$code = strtoupper(input_string('codigo', 'get'));
$email = function_exists('mb_strtolower')
    ? mb_strtolower(input_string('email', 'get'), 'UTF-8')
    : strtolower(input_string('email', 'get'));
$searched = $code !== '' || $email !== '';
$reservations = [];

if ($code !== '' && !preg_match('/^LS-[A-Z0-9]{4,12}$/', $code)) {
    $errors[] = 'O código de reserva deve ter o formato LS-XXXXXX.';
}

if ($email !== '' && !validate_email_value($email)) {
    $errors[] = 'Indique um endereço de email válido.';
}

if ($searched && $errors === []) {
    $sql = "SELECT r.*, a.name AS accommodation_name, a.location AS accommodation_location,
                   a.image_url AS accommodation_image, a.slug AS accommodation_slug,
                   g.full_name AS guest_name, g.email AS guest_email, g.phone AS guest_phone
            FROM reservations r
            INNER JOIN accommodations a ON a.id = r.accommodation_id
            INNER JOIN guests g ON g.id = r.guest_id
            WHERE 1 = 1";
    $params = [];

    if ($code !== '') {
        $sql .= ' AND r.reservation_code = :code';
        $params[':code'] = $code;
    }
    if ($email !== '') {
        $sql .= ' AND g.email = :email';
        $params[':email'] = $email;
    }

    $sql .= ' ORDER BY r.check_in DESC, r.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll();
}

$pageTitle = 'Consultar reserva';
$pageDescription = 'Consulte o estado da sua reserva LumaStay através do código de reserva ou do email do hóspede.';
$currentPage = 'consultar';
$bodyClass = 'page-consult';

include __DIR__ . '/includes/header.php';
?>

<section class="page-banner page-banner-compact">
    <div class="container">
        <nav class="breadcrumb" aria-label="Trilho de navegação">
            <a href="<?= e(url('index.php')) ?>">Início</a>
            <span aria-hidden="true">/</span>
            <span>Consultar reserva</span>
        </nav>
        <div class="page-banner-content">
            <div>
                <p class="eyebrow"><span class="eyebrow-line"></span> A sua estadia</p>
                <h1>Consultar reserva</h1>
                <p>Introduza o código recebido após o pedido ou o email usado na reserva. Se tiver ambos, a pesquisa fica mais precisa.</p>
            </div>
            <div class="page-banner-icon"><?= icon('clipboard') ?></div>
        </div>
    </div>
</section>

<section class="section consult-section">
    <div class="container consult-layout">
        <aside class="consult-form-panel">
            <h2><?= icon('search') ?> Encontrar reserva</h2>
            <form method="get" action="<?= e(url('consultar-reserva.php')) ?>" class="consult-form">
                <div class="form-field">
                    <label for="consult-code">Código de reserva</label>
                    <input type="text" id="consult-code" name="codigo" value="<?= e($code) ?>" placeholder="LS-7K2M9A" maxlength="16" autocomplete="off" spellcheck="false">
                    <small class="field-help">Formato LS-XXXXXX.</small>
                </div>
                <div class="form-field">
                    <label for="consult-email">Email do hóspede</label>
                    <input type="email" id="consult-email" name="email" value="<?= e($email) ?>" placeholder="nome@exemplo.pt" maxlength="190" autocomplete="email">
                </div>
                <button class="btn btn-primary btn-block" type="submit"><?= icon('search') ?> Consultar</button>
                <?php if ($searched): ?>
                    <a class="btn btn-ghost btn-block" href="<?= e(url('consultar-reserva.php')) ?>">Nova consulta</a>
                <?php endif; ?>
            </form>

            <div class="filter-note">
                <?= icon('info') ?>
                <p>Por segurança, apresentamos apenas reservas que correspondam exatamente aos dados indicados. O código é sensível a maiúsculas?</p>
            </div>
        </aside>

        <div class="consult-results">
            <?= render_errors($errors) ?>

            <?php if (!$searched): ?>
                <div class="empty-state">
                    <span class="empty-state-icon"><?= icon('clipboard') ?></span>
                    <h2>A sua reserva, sempre à mão</h2>
                    <p>Comece por indicar o código de reserva ou o email do hóspede. Pode consultar o estado, as datas e o valor total.</p>
                    <div class="consult-hint-grid">
                        <div>
                            <strong>1.</strong>
                            <span>Introduza o código ou email</span>
                        </div>
                        <div>
                            <strong>2.</strong>
                            <span>Consulte o estado atual</span>
                        </div>
                        <div>
                            <strong>3.</strong>
                            <span>Fale connosco se precisar</span>
                        </div>
                    </div>
                </div>
            <?php elseif ($errors === [] && $reservations === []): ?>
                <div class="empty-state empty-state-large">
                    <span class="empty-state-icon"><?= icon('search') ?></span>
                    <h2>Não encontrámos reservas</h2>
                    <p>Confirme o código e o email utilizados. Se o problema persistir, contacte <a href="mailto:reservas@lumastay.pt">reservas@lumastay.pt</a>.</p>
                    <a class="btn btn-primary" href="<?= e(url('consultar-reserva.php')) ?>">Tentar novamente</a>
                </div>
            <?php elseif ($reservations !== []): ?>
                <div class="consult-results-head">
                    <h2><?= count($reservations) ?> <?= count($reservations) === 1 ? 'reserva encontrada' : 'reservas encontradas' ?></h2>
                    <?php if ($code !== ''): ?>
                        <span>Código: <strong><?= e($code) ?></strong></span>
                    <?php endif; ?>
                </div>

                <div class="reservation-list">
                    <?php foreach ($reservations as $reservation): ?>
                        <?php $reservationNights = nights_between((string) $reservation['check_in'], (string) $reservation['check_out']); ?>
                        <article class="reservation-card">
                            <div class="reservation-card-media">
                                <img src="<?= e(image_src((string) $reservation['accommodation_image'])) ?>"
                                     alt="<?= e($reservation['accommodation_name']) ?>"
                                     loading="lazy"
                                     data-fallback="<?= e(asset('images/fallback.svg')) ?>">
                            </div>
                            <div class="reservation-card-body">
                                <div class="reservation-card-top">
                                    <div>
                                        <p class="stay-card-location"><?= icon('pin') ?> <?= e($reservation['accommodation_location']) ?></p>
                                        <h3><?= e($reservation['accommodation_name']) ?></h3>
                                    </div>
                                    <?= status_badge((string) $reservation['status']) ?>
                                </div>

                                <div class="reservation-code-row">
                                    <span>Código de reserva</span>
                                    <code><?= e($reservation['reservation_code']) ?></code>
                                </div>

                                <dl class="reservation-details">
                                    <div>
                                        <dt><?= icon('calendar') ?> Entrada</dt>
                                        <dd><?= e(format_date((string) $reservation['check_in'])) ?></dd>
                                    </div>
                                    <div>
                                        <dt><?= icon('calendar') ?> Saída</dt>
                                        <dd><?= e(format_date((string) $reservation['check_out'])) ?></dd>
                                    </div>
                                    <div>
                                        <dt><?= icon('moon') ?> Noites</dt>
                                        <dd><?= e($reservationNights) ?></dd>
                                    </div>
                                    <div>
                                        <dt><?= icon('users') ?> Hóspedes</dt>
                                        <dd><?= e($reservation['guests_count']) ?></dd>
                                    </div>
                                    <div>
                                        <dt><?= icon('user') ?> Titular</dt>
                                        <dd><?= e($reservation['guest_name']) ?></dd>
                                    </div>
                                    <div>
                                        <dt><?= icon('tag') ?> Total</dt>
                                        <dd class="reservation-total"><?= e(money((float) $reservation['total_price'])) ?></dd>
                                    </div>
                                </dl>

                                <div class="reservation-card-footer">
                                    <span class="reservation-created">Pedido efetuado em <?= e(format_date((string) $reservation['created_at'])) ?></span>
                                    <a class="text-link" href="<?= e(url('alojamento.php') . '?id=' . (int) $reservation['accommodation_id']) ?>">Ver alojamento <?= icon('arrow-right') ?></a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
