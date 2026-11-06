<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();
$reservationId = is_post()
    ? (input_int('id', 'post', 0) ?? 0)
    : (input_int('id', 'get', 0) ?? 0);

if (is_post()) {
    verify_csrf('admin/detalhes-reserva.php?id=' . $reservationId);
    $action = input_string('action', 'post');
    $result = perform_reservation_action($reservationId, $action);
    set_flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('admin/detalhes-reserva.php?id=' . $reservationId);
}

$stmt = $pdo->prepare(
    "SELECT r.*, a.id AS accommodation_id, a.name AS accommodation_name, a.location AS accommodation_location,
            a.address AS accommodation_address, a.image_url AS accommodation_image, a.type AS accommodation_type,
            g.id AS guest_id, g.full_name AS guest_name, g.email AS guest_email, g.phone AS guest_phone,
            g.created_at AS guest_created_at
     FROM reservations r
     INNER JOIN accommodations a ON a.id = r.accommodation_id
     INNER JOIN guests g ON g.id = r.guest_id
     WHERE r.id = :id
     LIMIT 1"
);
$stmt->execute([':id' => $reservationId]);
$reservation = $stmt->fetch();

if (!is_array($reservation)) {
    set_flash('error', 'A reserva indicada não existe.');
    redirect('admin/reservas.php');
}

$nights = nights_between((string) $reservation['check_in'], (string) $reservation['check_out']);
$status = (string) $reservation['status'];
$canConfirm = $status === 'pendente';
$canCancel = in_array($status, ['pendente', 'confirmada'], true);
$canComplete = $status === 'confirmada' && (string) $reservation['check_out'] <= today();

$adminPage = 'reservas';
$pageTitle = 'Reserva ' . $reservation['reservation_code'];
$bodyClass = 'admin-body admin-reservation-detail';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-page-intro">
    <div>
        <p class="admin-eyebrow">Reserva · <?= e($reservation['reservation_code']) ?></p>
        <h2>Detalhes da reserva</h2>
        <p>Pedido efetuado em <?= e(format_date((string) $reservation['created_at'], true)) ?>.</p>
    </div>
    <div class="admin-intro-actions">
        <a class="btn btn-ghost btn-small" href="<?= e(url('admin/reservas.php')) ?>"><?= icon('arrow-left') ?> Voltar às reservas</a>
    </div>
</div>

<div class="detail-admin-grid">
    <div class="detail-admin-main">
        <section class="admin-card reservation-hero-card">
            <div class="reservation-hero-head">
                <div>
                    <span class="reservation-hero-label">Código de reserva</span>
                    <code class="reservation-hero-code"><?= e($reservation['reservation_code']) ?></code>
                </div>
                <?= status_badge($status) ?>
            </div>

            <div class="reservation-status-track">
                <?php
                $trackStatuses = [
                    'pendente' => 'Pedido',
                    'confirmada' => 'Confirmada',
                    'concluida' => 'Concluída',
                ];
                $statusOrder = array_keys($trackStatuses);
                $currentIndex = array_search($status, $statusOrder, true);
                if ($currentIndex === false) {
                    $currentIndex = -1;
                }
                foreach ($trackStatuses as $trackKey => $trackLabel):
                    $trackIndex = array_search($trackKey, $statusOrder, true);
                ?>
                    <div class="status-track-step<?= $trackIndex <= $currentIndex ? ' is-complete' : '' ?><?= $trackKey === $status ? ' is-current' : '' ?>">
                        <span><?= icon($trackIndex <= $currentIndex ? 'check' : 'clock') ?></span>
                        <small><?= e($trackLabel) ?></small>
                    </div>
                <?php endforeach; ?>

                <?php if ($status === 'cancelada'): ?>
                    <div class="status-track-cancelled"><?= icon('close') ?> Reserva cancelada — as datas foram libertadas.</div>
                <?php endif; ?>
            </div>
        </section>

        <section class="admin-card">
            <div class="admin-card-head">
                <div>
                    <h2>Estadia</h2>
                    <p>Datas, hóspedes e alojamento associado.</p>
                </div>
            </div>

            <div class="reservation-stay-grid">
                <div class="reservation-date-box">
                    <span>Entrada</span>
                    <strong><?= e(format_date((string) $reservation['check_in'], true)) ?></strong>
                    <small>A partir das 15:00</small>
                </div>
                <span class="reservation-date-arrow"><?= icon('arrow-right') ?></span>
                <div class="reservation-date-box">
                    <span>Saída</span>
                    <strong><?= e(format_date((string) $reservation['check_out'], true)) ?></strong>
                    <small>Até às 11:00</small>
                </div>
                <div class="reservation-night-box">
                    <strong><?= e($nights) ?></strong>
                    <span><?= $nights === 1 ? 'noite' : 'noites' ?></span>
                </div>
            </div>

            <div class="reservation-accommodation">
                <img src="<?= e(image_src((string) $reservation['accommodation_image'])) ?>"
                     alt="<?= e($reservation['accommodation_name']) ?>"
                     data-fallback="<?= e(asset('images/fallback.svg')) ?>">
                <div>
                    <p class="stay-card-location"><?= icon('pin') ?> <?= e($reservation['accommodation_location']) ?></p>
                    <h3><?= e($reservation['accommodation_name']) ?></h3>
                    <p><?= e(type_label((string) $reservation['accommodation_type'])) ?> · <?= e($reservation['accommodation_address']) ?></p>
                    <div class="inline-actions">
                        <a class="text-link" href="<?= e(url('alojamento.php') . '?id=' . (int) $reservation['accommodation_id']) ?>" target="_blank" rel="noopener">Ver no site <?= icon('arrow-right') ?></a>
                        <a class="text-link" href="<?= e(url('admin/editar-alojamento.php') . '?id=' . (int) $reservation['accommodation_id']) ?>">Editar alojamento</a>
                    </div>
                </div>
            </div>
        </section>

        <section class="admin-card">
            <div class="admin-card-head">
                <div>
                    <h2>Hóspede</h2>
                    <p>Dados de contacto associados a esta reserva.</p>
                </div>
                <a class="text-link" href="<?= e(url('admin/hospede.php') . '?id=' . (int) $reservation['guest_id']) ?>">Ver ficha do hóspede <?= icon('arrow-right') ?></a>
            </div>

            <div class="guest-detail-grid">
                <div>
                    <span class="detail-label">Nome completo</span>
                    <strong><?= e($reservation['guest_name']) ?></strong>
                </div>
                <div>
                    <span class="detail-label">Email</span>
                    <a href="mailto:<?= e($reservation['guest_email']) ?>"><?= e($reservation['guest_email']) ?></a>
                </div>
                <div>
                    <span class="detail-label">Telefone</span>
                    <a href="tel:<?= e(normalize_phone((string) $reservation['guest_phone'])) ?>"><?= e($reservation['guest_phone']) ?></a>
                </div>
                <div>
                    <span class="detail-label">Hóspedes nesta reserva</span>
                    <strong><?= e($reservation['guests_count']) ?></strong>
                </div>
            </div>
        </section>
    </div>

    <aside class="detail-admin-side">
        <section class="admin-card">
            <div class="admin-card-head">
                <div>
                    <h2>Valores</h2>
                    <p>Preço calculado no momento da reserva.</p>
                </div>
            </div>
            <dl class="price-breakdown">
                <div>
                    <dt>Preço por noite</dt>
                    <dd><?= e(money((float) $reservation['price_per_night'])) ?></dd>
                </div>
                <div>
                    <dt>Número de noites</dt>
                    <dd><?= e($nights) ?></dd>
                </div>
                <div class="price-breakdown-total">
                    <dt>Total da reserva</dt>
                    <dd><?= e(money((float) $reservation['total_price'])) ?></dd>
                </div>
            </dl>
            <p class="admin-form-note"><?= icon('info') ?> O preço por noite fica registado na reserva, mesmo que o alojamento seja atualizado depois.</p>
        </section>

        <section class="admin-card">
            <div class="admin-card-head">
                <div>
                    <h2>Ações</h2>
                    <p>Transições permitidas para o estado atual.</p>
                </div>
            </div>

            <div class="admin-action-stack">
                <?php if ($canConfirm): ?>
                    <form method="post" action="<?= e(url('admin/detalhes-reserva.php') . '?id=' . $reservationId) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $reservationId ?>">
                        <input type="hidden" name="action" value="confirm">
                        <button class="btn btn-primary btn-block" type="submit"><?= icon('check') ?> Confirmar reserva</button>
                    </form>
                <?php endif; ?>

                <?php if ($canComplete): ?>
                    <form method="post" action="<?= e(url('admin/detalhes-reserva.php') . '?id=' . $reservationId) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $reservationId ?>">
                        <input type="hidden" name="action" value="complete">
                        <button class="btn btn-outline btn-block" type="submit"><?= icon('clipboard') ?> Marcar como concluída</button>
                    </form>
                <?php endif; ?>

                <?php if ($canCancel): ?>
                    <form method="post" action="<?= e(url('admin/detalhes-reserva.php') . '?id=' . $reservationId) ?>" data-confirm="Cancelar a reserva <?= e($reservation['reservation_code']) ?>? As datas ficarão novamente disponíveis.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $reservationId ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button class="btn btn-danger-ghost btn-block" type="submit"><?= icon('close') ?> Cancelar reserva</button>
                    </form>
                <?php endif; ?>

                <?php if (!$canConfirm && !$canComplete && !$canCancel): ?>
                    <div class="admin-empty"><?= icon('lock') ?><p>Não existem ações disponíveis para o estado atual.</p></div>
                <?php endif; ?>
            </div>

            <div class="admin-action-note">
                <?php if ($status === 'pendente'): ?>
                    <p><?= icon('info') ?> A confirmação valida novamente a ausência de sobreposições.</p>
                <?php elseif ($status === 'confirmada'): ?>
                    <p><?= icon('info') ?> A conclusão só é permitida após a data de saída.</p>
                <?php elseif ($status === 'cancelada'): ?>
                    <p><?= icon('info') ?> O cancelamento libertou as datas para novas reservas.</p>
                <?php else: ?>
                    <p><?= icon('info') ?> Esta estadia está concluída e o histórico é preservado.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="admin-card meta-card">
            <h2>Registo</h2>
            <div class="meta-row"><span>Criada</span><strong><?= e(format_date((string) $reservation['created_at'])) ?></strong></div>
            <div class="meta-row"><span>Atualizada</span><strong><?= e(format_date((string) $reservation['updated_at'])) ?></strong></div>
            <div class="meta-row"><span>ID interno</span><strong>#<?= e($reservation['id']) ?></strong></div>
        </section>
    </aside>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
