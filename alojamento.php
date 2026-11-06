<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$accommodationId = input_int('id', 'get', 0) ?? 0;
$accommodation = find_accommodation($accommodationId);

if ($accommodation === false) {
    http_response_code(404);
    $pageTitle = 'Alojamento não encontrado';
    $pageDescription = 'O alojamento que procura não existe ou já não está disponível.';
    $currentPage = 'alojamentos';
    $bodyClass = 'page-detail';
    include __DIR__ . '/includes/header.php';
    ?>
    <section class="section error-page">
        <div class="container error-page-inner">
            <span class="error-page-code">404</span>
            <h1>Este alojamento não está disponível</h1>
            <p>O link pode estar incompleto ou o alojamento pode ter sido retirado do catálogo. Conheça outras casas selecionadas pela LumaStay.</p>
            <div class="empty-state-actions">
                <a class="btn btn-primary" href="<?= e(url('alojamentos.php')) ?>">Ver todos os alojamentos</a>
                <a class="btn btn-ghost" href="<?= e(url('index.php')) ?>">Voltar ao início</a>
            </div>
        </div>
    </section>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

$amenities = amenities_decode($accommodation['amenities']);
$checkIn = input_string('check_in', 'get');
$checkOut = input_string('check_out', 'get');
$guests = input_int('guests', 'get', 0) ?? 0;
$dateError = '';
$isAvailable = null;
$nights = 0;
$estimatedTotal = 0.0;

if ($checkIn !== '' || $checkOut !== '') {
    if (!validate_date_value($checkIn) || !validate_date_value($checkOut)) {
        $dateError = 'Indique datas válidas para verificar a disponibilidade.';
    } elseif ($checkIn < today()) {
        $dateError = 'A data de entrada não pode estar no passado.';
    } elseif ($checkOut <= $checkIn) {
        $dateError = 'A data de saída tem de ser posterior à entrada.';
    } else {
        $nights = nights_between($checkIn, $checkOut);
        $estimatedTotal = $nights * (float) $accommodation['price_per_night'];
        $isAvailable = accommodation_is_available((int) $accommodation['id'], $checkIn, $checkOut);
    }
}

if ($guests < 1) {
    $guests = min(2, (int) $accommodation['capacity']);
}
$guests = min($guests, (int) $accommodation['capacity']);

$similarStmt = $pdo->prepare(
    "SELECT * FROM accommodations
     WHERE active = 1 AND id <> :id AND location = :location
     ORDER BY rating DESC, review_count DESC
     LIMIT 3"
);
$similarStmt->execute([':id' => (int) $accommodation['id'], ':location' => $accommodation['location']]);
$similar = $similarStmt->fetchAll();

if ($similar === []) {
    $fallbackStmt = $pdo->prepare(
        "SELECT * FROM accommodations
         WHERE active = 1 AND id <> :id
         ORDER BY rating DESC, review_count DESC
         LIMIT 3"
    );
    $fallbackStmt->execute([':id' => (int) $accommodation['id']]);
    $similar = $fallbackStmt->fetchAll();
}

$pageTitle = $accommodation['name'] . ' — ' . $accommodation['location'];
$pageDescription = excerpt((string) $accommodation['description'], 155);
$currentPage = 'alojamentos';
$bodyClass = 'page-detail';
$reservationUrl = url('reservar.php') . '?' . http_build_query([
    'accommodation_id' => (int) $accommodation['id'],
    'check_in' => $checkIn,
    'check_out' => $checkOut,
    'guests' => $guests,
]);

include __DIR__ . '/includes/header.php';
?>

<section class="detail-hero">
    <div class="container">
        <nav class="breadcrumb" aria-label="Trilho de navegação">
            <a href="<?= e(url('index.php')) ?>">Início</a>
            <span aria-hidden="true">/</span>
            <a href="<?= e(url('alojamentos.php')) ?>">Alojamentos</a>
            <span aria-hidden="true">/</span>
            <span><?= e($accommodation['location']) ?></span>
        </nav>

        <div class="detail-heading">
            <div>
                <p class="eyebrow"><span class="eyebrow-line"></span> <?= e(type_label((string) $accommodation['type'])) ?> em <?= e($accommodation['location']) ?></p>
                <h1><?= e($accommodation['name']) ?></h1>
                <div class="detail-heading-meta">
                    <?= rating_badge((float) $accommodation['rating'], (int) $accommodation['review_count']) ?>
                    <span class="detail-separator" aria-hidden="true"></span>
                    <span><?= icon('pin') ?> <?= e($accommodation['address']) ?></span>
                    <span class="detail-separator" aria-hidden="true"></span>
                    <span><?= icon('users') ?> Até <?= e($accommodation['capacity']) ?> hóspedes</span>
                </div>
            </div>
            <div class="detail-heading-price">
                <span>A partir de</span>
                <strong><?= e(money((float) $accommodation['price_per_night'])) ?></strong>
                <small>por noite</small>
            </div>
        </div>

        <figure class="detail-gallery">
            <img src="<?= e(image_src((string) $accommodation['image_url'])) ?>"
                 alt="<?= e($accommodation['name'] . ' — ' . $accommodation['location']) ?>"
                 data-fallback="<?= e(asset('images/fallback.svg')) ?>">
            <figcaption><?= icon('sparkles') ?> Imagem ilustrativa do alojamento</figcaption>
            <span class="detail-gallery-type"><?= e(type_label((string) $accommodation['type'])) ?></span>
        </figure>
    </div>
</section>

<section class="section detail-section">
    <div class="container detail-layout">
        <div class="detail-main">
            <div class="detail-block detail-intro">
                <p class="eyebrow"><span class="eyebrow-line"></span> Sobre este alojamento</p>
                <h2>A casa, contada por nós</h2>
                <p><?= nl2br(e((string) $accommodation['description'])) ?></p>
            </div>

            <dl class="detail-facts">
                <div>
                    <dt><?= icon('home') ?> Tipo</dt>
                    <dd><?= e(type_label((string) $accommodation['type'])) ?></dd>
                </div>
                <div>
                    <dt><?= icon('users') ?> Capacidade</dt>
                    <dd><?= e($accommodation['capacity']) ?> hóspedes</dd>
                </div>
                <div>
                    <dt><?= icon('bed') ?> Quartos</dt>
                    <dd><?= e($accommodation['bedrooms']) ?></dd>
                </div>
                <div>
                    <dt><?= icon('bath') ?> Casas de banho</dt>
                    <dd><?= e($accommodation['bathrooms']) ?></dd>
                </div>
            </dl>

            <div class="detail-block">
                <h2>O que esta casa oferece</h2>
                <?php if ($amenities === []): ?>
                    <p class="muted">As comodidades serão confirmadas pela equipa LumaStay.</p>
                <?php else: ?>
                    <ul class="amenities-grid">
                        <?php foreach ($amenities as $amenity): ?>
                            <li><?= icon(amenity_icon($amenity)) ?> <span><?= e($amenity) ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="detail-block detail-location">
                <h2>Localização</h2>
                <div class="location-card">
                    <span class="location-card-icon"><?= icon('pin') ?></span>
                    <div>
                        <h3><?= e($accommodation['location']) ?></h3>
                        <p><?= e($accommodation['address']) ?></p>
                        <p class="muted">A morada exata e as indicações de acesso são enviadas após a confirmação da reserva.</p>
                    </div>
                </div>
            </div>

            <div class="detail-block">
                <h2>Informações úteis</h2>
                <div class="info-columns">
                    <div>
                        <h3><?= icon('clock') ?> Check-in e check-out</h3>
                        <p>Entrada a partir das 15:00 e saída até às 11:00. A equipa LumaStay confirma os horários antes da chegada.</p>
                    </div>
                    <div>
                        <h3><?= icon('shield') ?> Reserva tranquila</h3>
                        <p>Sem pagamentos no site. A reserva fica pendente até validação da equipa e recebe um código de consulta.</p>
                    </div>
                    <div>
                        <h3><?= icon('leaf') ?> Convivência</h3>
                        <p>Alojamento para não fumadores. Não são permitidos eventos ou festas sem autorização prévia.</p>
                    </div>
                </div>
            </div>
        </div>

        <aside class="booking-card" id="reserva" aria-label="Reservar este alojamento">
            <div class="booking-card-head">
                <div>
                    <span class="booking-price"><?= e(money((float) $accommodation['price_per_night'])) ?></span>
                    <span class="booking-price-unit">/ noite</span>
                </div>
                <?= rating_badge((float) $accommodation['rating'], (int) $accommodation['review_count']) ?>
            </div>

            <form class="booking-form" action="<?= e(url('alojamento.php')) ?>" method="get">
                <input type="hidden" name="id" value="<?= (int) $accommodation['id'] ?>">

                <?php if ($dateError !== ''): ?>
                    <div class="inline-message inline-message-error"><?= icon('alert') ?> <?= e($dateError) ?></div>
                <?php endif; ?>

                <div class="booking-dates">
                    <div class="form-field">
                        <label for="detail-check-in">Entrada</label>
                        <input type="date" id="detail-check-in" name="check_in" value="<?= e($checkIn) ?>" min="<?= e(today()) ?>" data-check-in required>
                    </div>
                    <div class="form-field">
                        <label for="detail-check-out">Saída</label>
                        <input type="date" id="detail-check-out" name="check_out" value="<?= e($checkOut) ?>" min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" data-check-out required>
                    </div>
                </div>

                <div class="form-field">
                    <label for="detail-guests">Hóspedes</label>
                    <select id="detail-guests" name="guests">
                        <?php for ($i = 1; $i <= (int) $accommodation['capacity']; $i++): ?>
                            <option value="<?= $i ?>"<?= selected($guests, $i) ?>><?= $i ?> <?= $i === 1 ? 'hóspede' : 'hóspedes' ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <button class="btn btn-outline btn-block" type="submit"><?= icon('calendar') ?> Verificar disponibilidade</button>
            </form>

            <?php if ($isAvailable === true): ?>
                <div class="availability-box availability-available" role="status">
                    <?= icon('check') ?>
                    <div>
                        <strong>Disponível para as datas escolhidas</strong>
                        <p><?= e($nights) ?> <?= $nights === 1 ? 'noite' : 'noites' ?> · <?= e(format_date($checkIn)) ?> a <?= e(format_date($checkOut)) ?></p>
                    </div>
                </div>
                <div class="booking-total">
                    <span>Total estimado</span>
                    <strong><?= e(money($estimatedTotal)) ?></strong>
                </div>
                <a class="btn btn-primary btn-block btn-large" href="<?= e($reservationUrl) ?>">Continuar para reserva <?= icon('arrow-right') ?></a>
                <p class="booking-security"><?= icon('shield') ?> Sem pagamento imediato. A disponibilidade é confirmada novamente no servidor.</p>
            <?php elseif ($isAvailable === false): ?>
                <div class="availability-box availability-unavailable" role="alert">
                    <?= icon('alert') ?>
                    <div>
                        <strong>Estas datas já não estão disponíveis</strong>
                        <p>Escolha outro intervalo. As reservas canceladas libertam automaticamente as datas.</p>
                    </div>
                </div>
                <a class="btn btn-outline btn-block" href="<?= e(url('alojamento.php') . '?id=' . (int) $accommodation['id'] . '#reserva') ?>">Escolher outras datas</a>
            <?php else: ?>
                <p class="booking-hint"><?= icon('info') ?> Escolha as datas para verificar a disponibilidade e o preço total.</p>
                <a class="btn btn-primary btn-block btn-large" href="<?= e($reservationUrl) ?>">Reservar agora <?= icon('arrow-right') ?></a>
                <p class="booking-security"><?= icon('shield') ?> A disponibilidade e o preço são validados pelo servidor antes de guardar a reserva.</p>
            <?php endif; ?>

            <ul class="booking-perks">
                <li><?= icon('check') ?> Confirmação com código único</li>
                <li><?= icon('check') ?> Preço por noite transparente</li>
                <li><?= icon('check') ?> Apoio da equipa LumaStay</li>
            </ul>
        </aside>
    </div>
</section>

<?php if ($similar !== []): ?>
<section class="section section-similar">
    <div class="container">
        <div class="section-heading">
            <div>
                <p class="eyebrow"><span class="eyebrow-line"></span> Também pode gostar</p>
                <h2>Outros lugares, a mesma atenção</h2>
            </div>
            <a class="text-link" href="<?= e(url('alojamentos.php')) ?>">Ver todos <?= icon('arrow-right') ?></a>
        </div>
        <div class="stay-grid">
            <?php foreach ($similar as $cardAccommodation): ?>
                <?php include __DIR__ . '/includes/accommodation-card.php'; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
