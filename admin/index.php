<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();

$stats = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM accommodations) AS total_accommodations,
        (SELECT COUNT(*) FROM accommodations WHERE active = 1) AS active_accommodations,
        (SELECT COUNT(*) FROM reservations) AS total_reservations,
        (SELECT COUNT(*) FROM reservations WHERE status = 'pendente') AS pending_reservations,
        (SELECT COUNT(*) FROM reservations WHERE status = 'confirmada') AS confirmed_reservations,
        (SELECT COUNT(*) FROM reservations WHERE status = 'cancelada') AS cancelled_reservations,
        (SELECT COUNT(*) FROM reservations WHERE status = 'concluida') AS completed_reservations,
        (SELECT COUNT(*) FROM guests) AS total_guests,
        (SELECT COALESCE(SUM(total_price), 0) FROM reservations
            WHERE status IN ('pendente', 'confirmada') AND check_out >= CURDATE()) AS expected_revenue"
)->fetch();

$activeAccommodations = (int) $stats['active_accommodations'];

$occupiedNightsStmt = $pdo->query(
    "SELECT COALESCE(SUM(
                DATEDIFF(
                    LEAST(r.check_out, DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
                    GREATEST(r.check_in, CURDATE())
                )
            ), 0) AS occupied_nights
     FROM reservations r
     INNER JOIN accommodations a ON a.id = r.accommodation_id
     WHERE a.active = 1
       AND r.status IN ('pendente', 'confirmada')
       AND r.check_in < DATE_ADD(CURDATE(), INTERVAL 30 DAY)
       AND r.check_out > CURDATE()"
);
$occupiedNights = (int) $occupiedNightsStmt->fetchColumn();
$availableNights = $activeAccommodations * 30;
$occupancyRate = $availableNights > 0 ? min(100, round(($occupiedNights / $availableNights) * 100, 1)) : 0.0;

$recentReservations = $pdo->query(
    "SELECT r.*, a.name AS accommodation_name, g.full_name AS guest_name
     FROM reservations r
     INNER JOIN accommodations a ON a.id = r.accommodation_id
     INNER JOIN guests g ON g.id = r.guest_id
     ORDER BY r.created_at DESC, r.id DESC
     LIMIT 6"
)->fetchAll();

$upcomingCheckIns = $pdo->query(
    "SELECT r.*, a.name AS accommodation_name, g.full_name AS guest_name
     FROM reservations r
     INNER JOIN accommodations a ON a.id = r.accommodation_id
     INNER JOIN guests g ON g.id = r.guest_id
     WHERE r.status IN ('pendente', 'confirmada')
       AND r.check_in >= CURDATE()
       AND r.check_in <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY r.check_in ASC, r.id ASC
     LIMIT 5"
)->fetchAll();

$upcomingCheckOuts = $pdo->query(
    "SELECT r.*, a.name AS accommodation_name, g.full_name AS guest_name
     FROM reservations r
     INNER JOIN accommodations a ON a.id = r.accommodation_id
     INNER JOIN guests g ON g.id = r.guest_id
     WHERE r.status IN ('pendente', 'confirmada')
       AND r.check_out >= CURDATE()
       AND r.check_out <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
     ORDER BY r.check_out ASC, r.id ASC
     LIMIT 5"
)->fetchAll();

$monthlyRows = $pdo->query(
    "SELECT DATE_FORMAT(check_in, '%Y-%m') AS month_key,
            COUNT(*) AS total,
            COALESCE(SUM(total_price), 0) AS revenue
     FROM reservations
     WHERE status IN ('pendente', 'confirmada', 'concluida')
       AND check_in >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH), '%Y-%m-01')
     GROUP BY DATE_FORMAT(check_in, '%Y-%m')
     ORDER BY month_key ASC"
)->fetchAll();

$monthlyMap = [];
foreach ($monthlyRows as $row) {
    $monthlyMap[(string) $row['month_key']] = $row;
}

$monthLabels = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
$monthlyChart = [];
for ($i = 5; $i >= 0; $i--) {
    $timestamp = strtotime("first day of -$i month");
    $key = date('Y-m', $timestamp);
    $monthlyChart[] = [
        'key'     => $key,
        'label'   => $monthLabels[(int) date('n', $timestamp) - 1],
        'total'   => (int) ($monthlyMap[$key]['total'] ?? 0),
        'revenue' => (float) ($monthlyMap[$key]['revenue'] ?? 0),
    ];
}
$maxMonthlyReservations = max(1, max(array_column($monthlyChart, 'total')));

$statusBreakdown = [
    'pendente'  => ['label' => 'Pendentes', 'count' => (int) $stats['pending_reservations'], 'class' => 'warning'],
    'confirmada'=> ['label' => 'Confirmadas', 'count' => (int) $stats['confirmed_reservations'], 'class' => 'success'],
    'concluida' => ['label' => 'Concluídas', 'count' => (int) $stats['completed_reservations'], 'class' => 'neutral'],
    'cancelada' => ['label' => 'Canceladas', 'count' => (int) $stats['cancelled_reservations'], 'class' => 'danger'],
];
$totalForBreakdown = max(1, (int) $stats['total_reservations']);

$adminPage = 'dashboard';
$pageTitle = 'Dashboard';
$bodyClass = 'admin-body admin-dashboard';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-page-intro">
    <div>
        <p class="admin-eyebrow"><?= e(format_date(today(), true)) ?></p>
        <h2>Bom dia, <?= e(explode(' ', (string) (current_admin()['name'] ?? 'equipa'))[0]) ?>.</h2>
        <p>Aqui está o resumo da operação da LumaStay.</p>
    </div>
    <div class="admin-intro-actions">
        <a class="btn btn-outline btn-small" href="<?= e(url('admin/reservas.php')) ?>"><?= icon('calendar') ?> Gerir reservas</a>
        <a class="btn btn-primary btn-small" href="<?= e(url('admin/criar-alojamento.php')) ?>"><?= icon('plus') ?> Novo alojamento</a>
    </div>
</div>

<section class="stat-grid" aria-label="Indicadores principais">
    <article class="stat-card stat-card-highlight">
        <div class="stat-card-head">
            <span class="stat-label">Receita prevista</span>
            <span class="stat-icon"><?= icon('chart') ?></span>
        </div>
        <p class="stat-value"><?= e(money((float) $stats['expected_revenue'])) ?></p>
        <p class="stat-note">Reservas pendentes e confirmadas com saída futura.</p>
    </article>

    <article class="stat-card">
        <div class="stat-card-head">
            <span class="stat-label">Reservas</span>
            <span class="stat-icon"><?= icon('calendar') ?></span>
        </div>
        <p class="stat-value"><?= e((int) $stats['total_reservations']) ?></p>
        <p class="stat-note"><?= e((int) $stats['pending_reservations']) ?> pendentes de confirmação</p>
    </article>

    <article class="stat-card">
        <div class="stat-card-head">
            <span class="stat-label">Hóspedes</span>
            <span class="stat-icon"><?= icon('users') ?></span>
        </div>
        <p class="stat-value"><?= e((int) $stats['total_guests']) ?></p>
        <p class="stat-note">Contactos registados na base de dados</p>
    </article>

    <article class="stat-card stat-card-occupancy">
        <div class="stat-card-head">
            <span class="stat-label">Ocupação · 30 dias</span>
            <span class="stat-icon"><?= icon('chart') ?></span>
        </div>
        <div class="occupancy-content">
            <div class="occupancy-ring" style="--occupancy: <?= e((string) $occupancyRate) ?>">
                <span><?= e(number_format($occupancyRate, 1, ',', '')) ?>%</span>
            </div>
            <p class="stat-note"><?= e($occupiedNights) ?> noites ocupadas de <?= e($availableNights) ?> disponíveis.</p>
        </div>
    </article>
</section>

<section class="stat-mini-grid" aria-label="Resumo operacional">
    <article class="mini-stat">
        <span><?= icon('home') ?></span>
        <div><strong><?= e((int) $stats['active_accommodations']) ?></strong><small>alojamentos ativos</small></div>
        <em><?= e((int) $stats['total_accommodations']) ?> no total</em>
    </article>
    <article class="mini-stat mini-stat-warning">
        <span><?= icon('clock') ?></span>
        <div><strong><?= e((int) $stats['pending_reservations']) ?></strong><small>reservas pendentes</small></div>
        <a href="<?= e(url('admin/reservas.php') . '?estado=pendente') ?>">Ver</a>
    </article>
    <article class="mini-stat mini-stat-success">
        <span><?= icon('check') ?></span>
        <div><strong><?= e((int) $stats['confirmed_reservations']) ?></strong><small>reservas confirmadas</small></div>
        <a href="<?= e(url('admin/reservas.php') . '?estado=confirmada') ?>">Ver</a>
    </article>
    <article class="mini-stat mini-stat-danger">
        <span><?= icon('close') ?></span>
        <div><strong><?= e((int) $stats['cancelled_reservations']) ?></strong><small>reservas canceladas</small></div>
        <a href="<?= e(url('admin/reservas.php') . '?estado=cancelada') ?>">Ver</a>
    </article>
</section>

<div class="dashboard-grid">
    <section class="admin-card chart-card">
        <div class="admin-card-head">
            <div>
                <h2>Reservas por mês</h2>
                <p>Entradas registadas nos últimos 6 meses.</p>
            </div>
            <span class="card-badge"><?= e(array_sum(array_column($monthlyChart, 'total'))) ?> reservas</span>
        </div>

        <div class="bar-chart" role="img" aria-label="Gráfico de reservas por mês">
            <?php foreach ($monthlyChart as $month): ?>
                <?php $height = max(4, (int) round(($month['total'] / $maxMonthlyReservations) * 100)); ?>
                <div class="bar-chart-column">
                    <span class="bar-chart-value"><?= e($month['total']) ?></span>
                    <div class="bar-chart-bar" style="height: <?= $height ?>%" title="<?= e($month['label']) ?>: <?= e($month['total']) ?> reservas · <?= e(money($month['revenue'])) ?>"></div>
                    <span class="bar-chart-label"><?= e($month['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="admin-card status-card">
        <div class="admin-card-head">
            <div>
                <h2>Estado das reservas</h2>
                <p>Distribuição atual na base de dados.</p>
            </div>
        </div>
        <div class="status-breakdown">
            <?php foreach ($statusBreakdown as $status): ?>
                <?php $percentage = round(($status['count'] / $totalForBreakdown) * 100, 1); ?>
                <div class="status-breakdown-row">
                    <div class="status-breakdown-label">
                        <span class="status-dot status-dot-<?= e($status['class']) ?>"></span>
                        <span><?= e($status['label']) ?></span>
                        <strong><?= e($status['count']) ?></strong>
                    </div>
                    <div class="progress-track">
                        <span class="progress-fill progress-<?= e($status['class']) ?>" style="width: <?= e((string) $percentage) ?>%"></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="status-card-footer">
            <span><?= e((int) $stats['completed_reservations']) ?> estadias concluídas</span>
            <a href="<?= e(url('admin/reservas.php')) ?>">Ver todas <?= icon('arrow-right') ?></a>
        </div>
    </section>
</div>

<div class="dashboard-grid dashboard-grid-lists">
    <section class="admin-card">
        <div class="admin-card-head">
            <div>
                <h2>Próximas entradas</h2>
                <p>Chegadas nos próximos 30 dias.</p>
            </div>
            <a class="text-link" href="<?= e(url('admin/reservas.php')) ?>">Ver reservas</a>
        </div>

        <?php if ($upcomingCheckIns === []): ?>
            <div class="admin-empty"><?= icon('calendar') ?><p>Sem entradas agendadas para os próximos 30 dias.</p></div>
        <?php else: ?>
            <ul class="timeline-list">
                <?php foreach ($upcomingCheckIns as $item): ?>
                    <li>
                        <span class="timeline-date"><?= e(date('d', strtotime((string) $item['check_in']))) ?><small><?= e($monthLabels[(int) date('n', strtotime((string) $item['check_in'])) - 1]) ?></small></span>
                        <div class="timeline-content">
                            <strong><?= e($item['guest_name']) ?></strong>
                            <span><?= e($item['accommodation_name']) ?> · <?= e($item['guests_count']) ?> hóspedes</span>
                        </div>
                        <?= status_badge((string) $item['status']) ?>
                        <a class="icon-link" href="<?= e(url('admin/detalhes-reserva.php') . '?id=' . (int) $item['id']) ?>" aria-label="Ver reserva de <?= e($item['guest_name']) ?>"><?= icon('chevron-right') ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="admin-card">
        <div class="admin-card-head">
            <div>
                <h2>Próximas saídas</h2>
                <p>Partidas nos próximos 30 dias.</p>
            </div>
        </div>

        <?php if ($upcomingCheckOuts === []): ?>
            <div class="admin-empty"><?= icon('calendar') ?><p>Sem saídas agendadas para os próximos 30 dias.</p></div>
        <?php else: ?>
            <ul class="timeline-list">
                <?php foreach ($upcomingCheckOuts as $item): ?>
                    <li>
                        <span class="timeline-date timeline-date-muted"><?= e(date('d', strtotime((string) $item['check_out']))) ?><small><?= e($monthLabels[(int) date('n', strtotime((string) $item['check_out'])) - 1]) ?></small></span>
                        <div class="timeline-content">
                            <strong><?= e($item['guest_name']) ?></strong>
                            <span><?= e($item['accommodation_name']) ?></span>
                        </div>
                        <?= status_badge((string) $item['status']) ?>
                        <a class="icon-link" href="<?= e(url('admin/detalhes-reserva.php') . '?id=' . (int) $item['id']) ?>" aria-label="Ver reserva de <?= e($item['guest_name']) ?>"><?= icon('chevron-right') ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<section class="admin-card admin-table-card">
    <div class="admin-card-head">
        <div>
            <h2>Reservas recentes</h2>
            <p>Últimos pedidos registados na plataforma.</p>
        </div>
        <a class="text-link" href="<?= e(url('admin/reservas.php')) ?>">Ver todas <?= icon('arrow-right') ?></a>
    </div>

    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Hóspede</th>
                    <th>Alojamento</th>
                    <th>Entrada</th>
                    <th>Saída</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th class="table-action">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentReservations as $reservation): ?>
                    <tr>
                        <td><code class="table-code"><?= e($reservation['reservation_code']) ?></code></td>
                        <td><?= e($reservation['guest_name']) ?></td>
                        <td><?= e($reservation['accommodation_name']) ?></td>
                        <td><?= e(format_date((string) $reservation['check_in'])) ?></td>
                        <td><?= e(format_date((string) $reservation['check_out'])) ?></td>
                        <td class="table-money"><?= e(money((float) $reservation['total_price'])) ?></td>
                        <td><?= status_badge((string) $reservation['status']) ?></td>
                        <td class="table-action"><a class="icon-link" href="<?= e(url('admin/detalhes-reserva.php') . '?id=' . (int) $reservation['id']) ?>" aria-label="Ver detalhes"><?= icon('eye') ?></a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
