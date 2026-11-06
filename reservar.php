<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$errors = [];
$accommodations = $pdo->query(
    "SELECT id, name, location, price_per_night, capacity, image_url
     FROM accommodations
     WHERE active = 1
     ORDER BY featured DESC, rating DESC, name ASC"
)->fetchAll();

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$accommodationId = $requestMethod === 'POST'
    ? (input_int('accommodation_id', 'post', 0) ?? 0)
    : (input_int('accommodation_id', 'get', 0) ?? 0);

$form = [
    'check_in'  => input_string('check_in', $requestMethod === 'POST' ? 'post' : 'get'),
    'check_out' => input_string('check_out', $requestMethod === 'POST' ? 'post' : 'get'),
    'guests'    => input_string('guests', $requestMethod === 'POST' ? 'post' : 'get'),
    'full_name' => input_string('full_name', 'post'),
    'email'     => input_string('email', 'post'),
    'phone'     => input_string('phone', 'post'),
];

$accommodation = $accommodationId > 0 ? find_accommodation($accommodationId) : false;
$nights = 0;
$estimatedTotal = 0.0;
$previewAvailable = null;
$confirmationError = '';

if ($requestMethod === 'POST') {
    verify_csrf('reservar.php' . ($accommodationId > 0 ? '?accommodation_id=' . $accommodationId : ''));

    if ($accommodation === false) {
        $errors[] = 'Escolha um alojamento válido antes de continuar.';
    }

    if ($form['check_in'] === '' || !validate_date_value($form['check_in'])) {
        $errors[] = 'Indique uma data de entrada válida.';
    } elseif ($form['check_in'] < today()) {
        $errors[] = 'A data de entrada não pode estar no passado.';
    }

    if ($form['check_out'] === '' || !validate_date_value($form['check_out'])) {
        $errors[] = 'Indique uma data de saída válida.';
    }

    if ($form['check_in'] !== '' && $form['check_out'] !== ''
        && validate_date_value($form['check_in']) && validate_date_value($form['check_out'])) {
        $nights = nights_between($form['check_in'], $form['check_out']);
        if ($nights === 0) {
            $errors[] = 'A data de saída tem de ser posterior à data de entrada.';
        } elseif ($nights > 60) {
            $errors[] = 'A estadia máxima é de 60 noites. Contacte-nos para estadias mais longas.';
        }
    }

    $guests = null;
    if (!preg_match('/^\d+$/', $form['guests'])) {
        $errors[] = 'Indique o número de hóspedes.';
    } else {
        $guests = (int) $form['guests'];
        if ($guests < 1) {
            $errors[] = 'O número de hóspedes deve ser pelo menos 1.';
        } elseif ($accommodation !== false && $guests > (int) $accommodation['capacity']) {
            $errors[] = 'Este alojamento acolhe no máximo ' . (int) $accommodation['capacity'] . ' hóspedes.';
        }
    }

    $fullName = preg_replace('/\s+/u', ' ', $form['full_name']) ?? '';
    if ($fullName === '' || !preg_match("/^[\p{L}\p{M} .'\-]{3,150}$/u", $fullName)) {
        $errors[] = 'Indique o nome completo do hóspede (apenas letras, espaços, apóstrofos, pontos e hífenes).';
    }

    $email = function_exists('mb_strtolower') ? mb_strtolower($form['email'], 'UTF-8') : strtolower($form['email']);
    if (!validate_email_value($email)) {
        $errors[] = 'Indique um endereço de email válido.';
    }

    if (!validate_phone_value($form['phone'])) {
        $errors[] = 'Indique um número de telefone válido (9 a 15 dígitos, com possível indicativo).';
    }

    if (!isset($_POST['terms']) || $_POST['terms'] !== '1') {
        $errors[] = 'Tem de aceitar as condições da reserva para continuar.';
    }

    if ($accommodation !== false && $nights > 0) {
        $estimatedTotal = $nights * (float) $accommodation['price_per_night'];
        $previewAvailable = accommodation_is_available((int) $accommodation['id'], $form['check_in'], $form['check_out']);
        if (!$previewAvailable) {
            $errors[] = 'As datas escolhidas deixaram de estar disponíveis. Escolha outro intervalo.';
        }
    }

    if ($errors === [] && $accommodation !== false && $guests !== null) {
        try {
            $pdo->beginTransaction();

            // Serializa pedidos concorrentes para o mesmo alojamento.
            $lockStmt = $pdo->prepare(
                'SELECT id, name, price_per_night, capacity
                 FROM accommodations
                 WHERE id = :id AND active = 1
                 FOR UPDATE'
            );
            $lockStmt->execute([':id' => (int) $accommodation['id']]);
            $lockedAccommodation = $lockStmt->fetch();

            if (!is_array($lockedAccommodation)) {
                throw new RuntimeException('O alojamento deixou de estar disponível.');
            }

            if ($guests > (int) $lockedAccommodation['capacity']) {
                throw new RuntimeException('O número de hóspedes excede a capacidade atual do alojamento.');
            }

            $overlapStmt = $pdo->prepare(
                "SELECT id FROM reservations
                 WHERE accommodation_id = :accommodation_id
                   AND status IN ('pendente', 'confirmada')
                   AND check_in < :check_out
                   AND check_out > :check_in
                 FOR UPDATE"
            );
            $overlapStmt->execute([
                ':accommodation_id' => (int) $lockedAccommodation['id'],
                ':check_in'         => $form['check_in'],
                ':check_out'        => $form['check_out'],
            ]);

            if ($overlapStmt->fetch() !== false) {
                throw new RuntimeException('As datas escolhidas já não estão disponíveis.');
            }

            $guestStmt = $pdo->prepare('SELECT id FROM guests WHERE email = :email LIMIT 1 FOR UPDATE');
            $guestStmt->execute([':email' => $email]);
            $guest = $guestStmt->fetch();

            if (is_array($guest)) {
                $guestId = (int) $guest['id'];
                $updateGuest = $pdo->prepare('UPDATE guests SET full_name = :name, phone = :phone WHERE id = :id');
                $updateGuest->execute([
                    ':name'  => $fullName,
                    ':phone' => $form['phone'],
                    ':id'    => $guestId,
                ]);
            } else {
                try {
                    $insertGuest = $pdo->prepare('INSERT INTO guests (full_name, email, phone) VALUES (:name, :email, :phone)');
                    $insertGuest->execute([
                        ':name'  => $fullName,
                        ':email' => $email,
                        ':phone' => $form['phone'],
                    ]);
                    $guestId = (int) $pdo->lastInsertId();
                } catch (PDOException $guestException) {
                    if ((string) $guestException->getCode() !== '23000') {
                        throw $guestException;
                    }
                    $guestStmt->execute([':email' => $email]);
                    $existingGuest = $guestStmt->fetch();
                    if (!is_array($existingGuest)) {
                        throw $guestException;
                    }
                    $guestId = (int) $existingGuest['id'];
                }
            }

            $pricePerNight = (float) $lockedAccommodation['price_per_night'];
            $totalPrice = round($nights * $pricePerNight, 2);
            $reservationCode = generate_reservation_code();

            $insertReservation = $pdo->prepare(
                "INSERT INTO reservations
                    (reservation_code, accommodation_id, guest_id, check_in, check_out, guests_count, price_per_night, total_price, status)
                 VALUES
                    (:code, :accommodation_id, :guest_id, :check_in, :check_out, :guests_count, :price_per_night, :total_price, 'pendente')"
            );
            $insertReservation->execute([
                ':code'             => $reservationCode,
                ':accommodation_id' => (int) $lockedAccommodation['id'],
                ':guest_id'         => $guestId,
                ':check_in'         => $form['check_in'],
                ':check_out'        => $form['check_out'],
                ':guests_count'     => $guests,
                ':price_per_night'  => $pricePerNight,
                ':total_price'      => $totalPrice,
            ]);

            $pdo->commit();

            set_flash('success', 'O seu pedido de reserva foi registado. Guarde o código para consultar mais tarde.');
            redirect('reserva-confirmada.php?codigo=' . rawurlencode($reservationCode) . '&email=' . rawurlencode($email));
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[LumaStay] Erro ao criar reserva: ' . $exception->getMessage());

            if ($exception instanceof RuntimeException) {
                $errors[] = $exception->getMessage();
            } else {
                $errors[] = 'Não foi possível concluir a reserva neste momento. Tente novamente.';
            }
        }
    }

    if ($accommodation !== false) {
        $estimatedTotal = $nights * (float) $accommodation['price_per_night'];
    }
} else {
    if ($accommodation !== false && $form['check_in'] !== '' && $form['check_out'] !== '') {
        if (validate_date_value($form['check_in']) && validate_date_value($form['check_out'])
            && $form['check_out'] > $form['check_in'] && $form['check_in'] >= today()) {
            $nights = nights_between($form['check_in'], $form['check_out']);
            $estimatedTotal = $nights * (float) $accommodation['price_per_night'];
            $previewAvailable = accommodation_is_available((int) $accommodation['id'], $form['check_in'], $form['check_out']);
        } else {
            $confirmationError = 'As datas recebidas não são válidas. Escolha novamente.';
        }
    }
}

if ($form['guests'] === '' && $accommodation !== false) {
    $form['guests'] = (string) min(2, (int) $accommodation['capacity']);
}

$pageTitle = 'Reservar alojamento';
$pageDescription = 'Conclua o seu pedido de reserva na LumaStay com confirmação de disponibilidade e preço calculado no servidor.';
$currentPage = 'alojamentos';
$bodyClass = 'page-booking';

include __DIR__ . '/includes/header.php';
?>

<section class="page-banner page-banner-compact">
    <div class="container">
        <nav class="breadcrumb" aria-label="Trilho de navegação">
            <a href="<?= e(url('index.php')) ?>">Início</a>
            <span aria-hidden="true">/</span>
            <a href="<?= e(url('alojamentos.php')) ?>">Alojamentos</a>
            <span aria-hidden="true">/</span>
            <span>Reserva</span>
        </nav>
        <div class="page-banner-content">
            <div>
                <p class="eyebrow"><span class="eyebrow-line"></span> Pedido de reserva</p>
                <h1><?= $accommodation !== false ? 'Reserve a sua estadia' : 'Escolha o seu alojamento' ?></h1>
                <p><?= $accommodation !== false ? 'Preencha os dados do hóspede. O preço e a disponibilidade são validados no servidor antes de guardar a reserva.' : 'Selecione uma casa para começar. Pode alterar as datas e o número de hóspedes no passo seguinte.' ?></p>
            </div>
            <div class="booking-progress" aria-label="Progresso da reserva">
                <span class="is-complete"><strong>1</strong> Escolher</span>
                <span class="<?= $accommodation !== false ? 'is-current' : '' ?>"><strong>2</strong> Dados</span>
                <span><strong>3</strong> Confirmação</span>
            </div>
        </div>
    </div>
</section>

<section class="section booking-section">
    <div class="container">
        <?= render_errors($errors) ?>

        <?php if ($confirmationError !== ''): ?>
            <div class="alert alert-warning"><?= icon('alert', 'alert-icon') ?><p><?= e($confirmationError) ?></p></div>
        <?php endif; ?>

        <?php if ($accommodation === false): ?>
            <div class="booking-chooser-grid">
                <div class="booking-chooser-panel">
                    <h2>Selecionar alojamento</h2>
                    <p>Comece por escolher a casa que quer reservar. Pode filtrar a lista completa por destino e preço.</p>

                    <form class="booking-chooser-form" method="get" action="<?= e(url('reservar.php')) ?>">
                        <div class="form-field">
                            <label for="choose-accommodation">Alojamento</label>
                            <select id="choose-accommodation" name="accommodation_id" required>
                                <option value="">Escolha uma casa</option>
                                <?php foreach ($accommodations as $option): ?>
                                    <option value="<?= (int) $option['id'] ?>"<?= selected($accommodationId, (int) $option['id']) ?>>
                                        <?= e($option['name']) ?> — <?= e($option['location']) ?> · <?= e(money((float) $option['price_per_night'])) ?>/noite
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="booking-dates">
                            <div class="form-field">
                                <label for="choose-check-in">Entrada <span class="label-optional">(opcional)</span></label>
                                <input type="date" id="choose-check-in" name="check_in" value="<?= e($form['check_in']) ?>" min="<?= e(today()) ?>" data-check-in>
                            </div>
                            <div class="form-field">
                                <label for="choose-check-out">Saída <span class="label-optional">(opcional)</span></label>
                                <input type="date" id="choose-check-out" name="check_out" value="<?= e($form['check_out']) ?>" min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" data-check-out>
                            </div>
                        </div>
                        <div class="form-field">
                            <label for="choose-guests">Hóspedes</label>
                            <select id="choose-guests" name="guests">
                                <?php for ($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?= $i ?>"<?= selected($form['guests'], $i) ?>><?= $i ?> <?= $i === 1 ? 'hóspede' : 'hóspedes' ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <button class="btn btn-primary btn-block" type="submit">Continuar <?= icon('arrow-right') ?></button>
                    </form>

                    <div class="filter-note">
                        <?= icon('shield') ?>
                        <p>Não é efectuado qualquer pagamento neste site. O pedido fica pendente de confirmação pela equipa.</p>
                    </div>
                </div>

                <aside class="booking-help-panel">
                    <img src="<?= e(asset('images/hero.svg')) ?>" alt="" loading="lazy">
                    <h2>Uma reserva simples, sem surpresas</h2>
                    <ul>
                        <li><?= icon('check') ?> Escolha o alojamento e as datas.</li>
                        <li><?= icon('check') ?> Indique os dados de contacto do hóspede.</li>
                        <li><?= icon('check') ?> Receba um código único de reserva.</li>
                        <li><?= icon('check') ?> Consulte o estado a qualquer momento.</li>
                    </ul>
                </aside>
            </div>
        <?php else: ?>
            <div class="booking-layout">
                <div class="booking-form-panel">
                    <div class="booking-form-head">
                        <div>
                            <p class="eyebrow"><span class="eyebrow-line"></span> Passo 2 de 3</p>
                            <h2>Dados da reserva</h2>
                            <p>Os campos marcados com * são obrigatórios.</p>
                        </div>
                        <a class="text-link" href="<?= e(url('reservar.php')) ?>">Alterar alojamento</a>
                    </div>

                    <?php if ($previewAvailable === false): ?>
                        <div class="inline-message inline-message-error"><?= icon('alert') ?> As datas atuais não estão disponíveis. Escolha outro intervalo antes de enviar.</div>
                    <?php elseif ($previewAvailable === true): ?>
                        <div class="inline-message inline-message-success"><?= icon('check') ?> Datas disponíveis. Pode concluir o pedido.</div>
                    <?php endif; ?>

                    <form class="booking-form-full" method="post" action="<?= e(url('reservar.php')) ?>" data-booking-form data-price="<?= e((string) $accommodation['price_per_night']) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="accommodation_id" value="<?= (int) $accommodation['id'] ?>">

                        <fieldset class="form-section">
                            <legend><?= icon('calendar') ?> Datas e hóspedes</legend>
                            <div class="booking-dates">
                                <div class="form-field">
                                    <label for="booking-check-in">Entrada *</label>
                                    <input type="date" id="booking-check-in" name="check_in" value="<?= e($form['check_in']) ?>" min="<?= e(today()) ?>" data-check-in required>
                                </div>
                                <div class="form-field">
                                    <label for="booking-check-out">Saída *</label>
                                    <input type="date" id="booking-check-out" name="check_out" value="<?= e($form['check_out']) ?>" min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" data-check-out required>
                                </div>
                            </div>
                            <div class="form-field">
                                <label for="booking-guests">Número de hóspedes *</label>
                                <select id="booking-guests" name="guests" required>
                                    <?php for ($i = 1; $i <= (int) $accommodation['capacity']; $i++): ?>
                                        <option value="<?= $i ?>"<?= selected($form['guests'], $i) ?>><?= $i ?> <?= $i === 1 ? 'hóspede' : 'hóspedes' ?></option>
                                    <?php endfor; ?>
                                </select>
                                <small class="field-help">Capacidade máxima: <?= (int) $accommodation['capacity'] ?> hóspedes.</small>
                            </div>
                        </fieldset>

                        <fieldset class="form-section">
                            <legend><?= icon('user') ?> Dados do hóspede</legend>
                            <div class="form-field">
                                <label for="booking-name">Nome completo *</label>
                                <input type="text" id="booking-name" name="full_name" value="<?= e($form['full_name']) ?>" maxlength="150" autocomplete="name" placeholder="Ex.: Mariana Costa" required>
                            </div>
                            <div class="form-row">
                                <div class="form-field">
                                    <label for="booking-email">Email *</label>
                                    <input type="email" id="booking-email" name="email" value="<?= e($form['email']) ?>" maxlength="190" autocomplete="email" placeholder="nome@exemplo.pt" required>
                                    <small class="field-help">Usado para consultar a reserva.</small>
                                </div>
                                <div class="form-field">
                                    <label for="booking-phone">Telefone *</label>
                                    <input type="tel" id="booking-phone" name="phone" value="<?= e($form['phone']) ?>" maxlength="30" autocomplete="tel" placeholder="+351 912 345 678" required>
                                </div>
                            </div>
                        </fieldset>

                        <label class="checkbox-field">
                            <input type="checkbox" name="terms" value="1"<?= isset($_POST['terms']) && $_POST['terms'] === '1' ? ' checked' : '' ?> required>
                            <span>Confirmo que li e aceito as condições da reserva e a política de tratamento de dados desta demonstração.</span>
                        </label>

                        <button class="btn btn-primary btn-large btn-block" type="submit">Enviar pedido de reserva <?= icon('arrow-right') ?></button>
                        <p class="form-note"><?= icon('shield') ?> O pedido fica com estado <strong>pendente</strong> até confirmação. Não há cobrança automática.</p>
                    </form>
                </div>

                <aside class="booking-summary">
                    <div class="booking-summary-media">
                        <img src="<?= e(image_src((string) $accommodation['image_url'])) ?>"
                             alt="<?= e($accommodation['name']) ?>"
                             data-fallback="<?= e(asset('images/fallback.svg')) ?>">
                        <span class="stay-card-type"><?= e(type_label((string) $accommodation['type'])) ?></span>
                    </div>
                    <div class="booking-summary-body">
                        <p class="stay-card-location"><?= icon('pin') ?> <?= e($accommodation['location']) ?></p>
                        <h2><?= e($accommodation['name']) ?></h2>
                        <?= rating_badge((float) $accommodation['rating'], (int) $accommodation['review_count']) ?>

                        <dl class="summary-list">
                            <div>
                                <dt>Entrada</dt>
                                <dd data-summary-check-in><?= $form['check_in'] !== '' ? e(format_date($form['check_in'])) : 'Por escolher' ?></dd>
                            </div>
                            <div>
                                <dt>Saída</dt>
                                <dd data-summary-check-out><?= $form['check_out'] !== '' ? e(format_date($form['check_out'])) : 'Por escolher' ?></dd>
                            </div>
                            <div>
                                <dt>Noites</dt>
                                <dd data-summary-nights><?= $nights > 0 ? e($nights) : '—' ?></dd>
                            </div>
                            <div>
                                <dt>Hóspedes</dt>
                                <dd data-summary-guests><?= e($form['guests']) ?></dd>
                            </div>
                        </dl>

                        <div class="summary-price">
                            <div>
                                <span><?= e(money((float) $accommodation['price_per_night'])) ?> × <span data-summary-nights-inline><?= $nights > 0 ? e($nights) : '—' ?></span> noites</span>
                                <strong data-summary-total><?= $nights > 0 ? e(money($estimatedTotal)) : '—' ?></strong>
                            </div>
                            <p>Preço total calculado pelo servidor no momento da confirmação.</p>
                        </div>

                        <ul class="booking-perks">
                            <li><?= icon('check') ?> Código único de reserva</li>
                            <li><?= icon('check') ?> Disponibilidade verificada</li>
                            <li><?= icon('check') ?> Sem pagamento online</li>
                        </ul>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
