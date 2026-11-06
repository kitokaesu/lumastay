<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();
$guestId = input_int('id', 'get', 0) ?? 0;

$stmt = $pdo->prepare('SELECT * FROM guests WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $guestId]);
$guest = $stmt->fetch();

if (!is_array($guest)) {
    set_flash('error', 'O hóspede indicado não existe.');
    redirect('admin/hospedes.php');
}

$summaryStmt = $pdo->prepare(
    "SELECT
        COUNT(*) AS total_reservations,
        SUM(CASE WHEN status IN ('pendente', 'confirmada') AND check_out >= CURDATE() THEN 1 ELSE 0 END) AS active_reservations,
        SUM(CASE WHEN status = 'concluida' THEN 1 ELSE 0 END) AS completed_reservations,
        SUM(CASE WHEN status = 'cancelada' THEN 1 ELSE 0 END) AS cancelled_reservations,
        COALESCE(SUM(CASE WHEN status IN ('confirmada', 'concluida') THEN total_price ELSE 0 END), 0) AS total_value
     FROM reservations
     WHERE guest_id = :guest_id"
);
$summaryStmt->execute([':guest_id' => $guestId]);
$summary = $summaryStmt->fetch();

$reservationsStmt = $pdo->prepare(
    "SELECT r.*, a.name AS accommodation_name, a.location AS accommodation_location, a.image_url AS accommodation_image
     FROM reservations r
     INNER JOIN accommodations a ON a.id = r.accommodation_id
     WHERE r.guest_id = :guest_id
     ORDER BY r.check_in DESC, r.id DESC"
);
$reservationsStmt->execute([':guest_id' => $guestId]);
$reservations = $reservationsStmt->fetchAll();

$nameParts = preg_split('/\s+/', trim((string) $guest['full_name'])) ?: [];
$initials = '';
foreach (array_slice($nameParts, 0, 2) as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}

$adminPage = 'hospedes';
$pageTitle = 'Ficha do hóspede';
$bodyClass = 'admin-body admin-guest-detail';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-page-intro">
    <div>
        <p class="admin-eyebrow">Hóspede · #<?= e($guestId) ?></p>
        <h2><?= e($guest['full_name']) ?></h2>
        <p>Registo criado em <?= e(format_date((string) $guest['created_at'], true)) ?>.</p>
    </div>
    <div class="admin-intro-actions">
        <a class="btn btn-ghost btn-small" href="<?= e(url('admin/hospedes.php')) ?>"><?= icon('arrow-left') ?> Voltar aos hóspedes</a>
    </div>
</div>

<div class="detail-admin-grid">
    <div class="detail-admin-main">
        <section class="admin-card guest-profile-card">
            <span class="guest-profile-avatar"><?= e($initials !== '' ? $initials : '?') ?></span>
            <div>
                <h2><?= e($guest['full_name']) ?></h2>
                <p>Hóspede LumaStay desde <?= e(format_date((string) $guest['created_at'], true)) ?>.</p>
                <div class="guest-profile-contacts">
                    <a href="mailto:<?= e($guest['email']) ?>"><?= icon('mail') ?> <?= e($guest['email']) ?></a>
                    <a href="tel:<?= e(normalize_phone((string) $guest['phone'])) ?>"><?= icon('phone') ?> <?= e($guest['phone']) ?></a>
                </div>
            </div>
        </section>

        <section class="admin-card admin-table-card">
            <div class="admin-card-head">
                <div>
                    <h2>Histórico de reservas</h2>
                    <p><?= e($summary['total_reservations']) ?> <?= (int) $summary['total_reservations'] === 1 ? 'reserva associada' : 'reservas associadas' ?>.</p>
                </div>
            </div>

            <?php if ($reservations === []): ?>
                <div class="admin-empty admin-empty-large">
                    <?= icon('calendar') ?>
                    <h3>Sem reservas</h3>
                    <p>Este hóspede ainda não tem reservas registadas.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Reserva</th>
                                <th>Alojamento</th>
                                <th>Entrada</th>
                                <th>Saída</th>
                                <th>Valor</th>
                                <th>Estado</th>
                                <th class="table-action">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td><a class="table-code" href="<?= e(url('admin/detalhes-reserva.php') . '?id=' . (int) $reservation['id']) ?>"><?= e($reservation['reservation_code']) ?></a></td>
                                    <td>
                                        <strong><?= e($reservation['accommodation_name']) ?></strong>
                                        <small class="table-subnote"><?= icon('pin') ?> <?= e($reservation['accommodation_location']) ?></small>
                                    </td>
                                    <td><?= e(format_date((string) $reservation['check_in'])) ?></td>
                                    <td><?= e(format_date((string) $reservation['check_out'])) ?></td>
                                    <td class="table-money"><?= e(money((float) $reservation['total_price'])) ?></td>
                                    <td><?= status_badge((string) $reservation['status']) ?></td>
                                    <td class="table-action">
                                        <a class="icon-link" href="<?= e(url('admin/detalhes-reserva.php') . '?id=' . (int) $reservation['id']) ?>" aria-label="Ver reserva <?= e($reservation['reservation_code']) ?>"><?= icon('eye') ?></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <aside class="detail-admin-side">
        <section class="admin-card">
            <div class="admin-card-head">
                <div>
                    <h2>Resumo</h2>
                    <p>Indicadores do histórico deste hóspede.</p>
                </div>
            </div>

            <dl class="guest-summary-list">
                <div><dt><?= icon('calendar') ?> Total de reservas</dt><dd><?= e($summary['total_reservations']) ?></dd></div>
                <div><dt><?= icon('clock') ?> Ativas ou futuras</dt><dd><?= e($summary['active_reservations'] ?? 0) ?></dd></div>
                <div><dt><?= icon('check') ?> Concluídas</dt><dd><?= e($summary['completed_reservations'] ?? 0) ?></dd></div>
                <div><dt><?= icon('close') ?> Canceladas</dt><dd><?= e($summary['cancelled_reservations'] ?? 0) ?></dd></div>
                <div class="guest-summary-total"><dt><?= icon('chart') ?> Valor acumulado</dt><dd><?= e(money((float) $summary['total_value'])) ?></dd></div>
            </dl>
            <p class="admin-form-note"><?= icon('info') ?> O valor acumulado considera reservas confirmadas e concluídas.</p>
        </section>

        <section class="admin-card meta-card">
            <h2>Dados pessoais</h2>
            <div class="meta-row"><span>Nome</span><strong><?= e($guest['full_name']) ?></strong></div>
            <div class="meta-row"><span>Email</span><strong><?= e($guest['email']) ?></strong></div>
            <div class="meta-row"><span>Telefone</span><strong><?= e($guest['phone']) ?></strong></div>
            <div class="meta-row"><span>Registo</span><strong><?= e(format_date((string) $guest['created_at'])) ?></strong></div>
        </section>
    </aside>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
