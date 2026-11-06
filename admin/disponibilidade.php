<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();

$monthInput = input_string('mes', 'get', date('Y-m'));
$monthDate = DateTimeImmutable::createFromFormat('!Y-m', $monthInput);
if (!$monthDate instanceof DateTimeImmutable || $monthDate->format('Y-m') !== $monthInput) {
    $monthDate = new DateTimeImmutable('first day of this month');
    $monthInput = $monthDate->format('Y-m');
}

$monthStart = $monthDate->format('Y-m-d');
$nextMonthStart = $monthDate->modify('+1 month')->format('Y-m-d');
$monthEnd = $monthDate->modify('last day of this month')->format('Y-m-d');
$daysInMonth = (int) $monthDate->format('t');
$firstWeekday = (int) $monthDate->format('N');
$previousMonth = $monthDate->modify('-1 month')->format('Y-m');
$nextMonth = $monthDate->modify('+1 month')->format('Y-m');
$today = today();

$monthNames = [
    1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
    5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
    9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
];
$monthLabel = $monthNames[(int) $monthDate->format('n')] . ' de ' . $monthDate->format('Y');

$accommodations = $pdo->query(
    "SELECT id, name, location, price_per_night
     FROM accommodations
     WHERE active = 1
     ORDER BY name ASC"
)->fetchAll();

$selectedAccommodationId = input_int('alojamento', 'get', 0) ?? 0;
if ($selectedAccommodationId > 0 && !in_array($selectedAccommodationId, array_map(static fn ($row) => (int) $row['id'], $accommodations), true)) {
    $selectedAccommodationId = 0;
}

$overviewStmt = $pdo->prepare(
    "SELECT a.id, a.name, a.location, a.price_per_night, a.capacity,
            COUNT(DISTINCT r.id) AS reservation_count,
            COALESCE(SUM(
                DATEDIFF(
                    LEAST(r.check_out, :month_end_exclusive),
                    GREATEST(r.check_in, :month_start)
                )
            ), 0) AS occupied_nights,
            COALESCE(SUM(r.total_price), 0) AS reservation_value
     FROM accommodations a
     LEFT JOIN reservations r ON r.accommodation_id = a.id
          AND r.status IN ('pendente', 'confirmada')
          AND r.check_in < :overlap_end
          AND r.check_out > :overlap_start
     WHERE a.active = 1
     GROUP BY a.id, a.name, a.location, a.price_per_night, a.capacity
     ORDER BY a.name ASC"
);
$overviewStmt->execute([
    ':month_end_exclusive' => $nextMonthStart,
    ':month_start'         => $monthStart,
    ':overlap_end'         => $nextMonthStart,
    ':overlap_start'       => $monthStart,
]);
$overview = $overviewStmt->fetchAll();

$overviewById = [];
foreach ($overview as $row) {
    $overviewById[(int) $row['id']] = $row;
}

$selectedAccommodation = $selectedAccommodationId > 0 ? ($overviewById[$selectedAccommodationId] ?? null) : null;
$selectedReservations = [];
$dayEvents = [];
$checkoutDays = [];

if ($selectedAccommodationId > 0) {
    $reservationsStmt = $pdo->prepare(
        "SELECT r.*, g.full_name AS guest_name
         FROM reservations r
         INNER JOIN guests g ON g.id = r.guest_id
         WHERE r.accommodation_id = :accommodation_id
           AND r.status IN ('pendente', 'confirmada')
           AND r.check_in < :next_month
           AND r.check_out > :month_start
         ORDER BY r.check_in ASC, r.id ASC"
    );
    $reservationsStmt->execute([
        ':accommodation_id' => $selectedAccommodationId,
        ':next_month'       => $nextMonthStart,
        ':month_start'      => $monthStart,
    ]);
    $selectedReservations = $reservationsStmt->fetchAll();

    foreach ($selectedReservations as $reservation) {
        $start = max((string) $reservation['check_in'], $monthStart);
        $endExclusive = min((string) $reservation['check_out'], $nextMonthStart);
        $cursor = new DateTimeImmutable($start);
        $end = new DateTimeImmutable($endExclusive);

        while ($cursor < $end) {
            $key = $cursor->format('Y-m-d');
            $dayEvents[$key][] = [
                'code'   => (string) $reservation['reservation_code'],
                'guest'  => (string) $reservation['guest_name'],
                'status' => (string) $reservation['status'],
                'check_in' => (string) $reservation['check_in'] === $key,
            ];
            $cursor = $cursor->modify('+1 day');
        }

        $checkoutKey = (string) $reservation['check_out'];
        if ($checkoutKey >= $monthStart && $checkoutKey <= $monthEnd) {
            $checkoutDays[$checkoutKey][] = [
                'code'  => (string) $reservation['reservation_code'],
                'guest' => (string) $reservation['guest_name'],
            ];
        }
    }
}

$selectedOccupied = $selectedAccommodation !== null ? (int) $selectedAccommodation['occupied_nights'] : 0;
$selectedOccupancy = $daysInMonth > 0 ? min(100, round(($selectedOccupied / $daysInMonth) * 100, 1)) : 0.0;

$totalActiveAccommodations = count($accommodations);
$totalOccupiedNights = array_sum(array_map(static fn ($row) => (int) $row['occupied_nights'], $overview));
$totalPossibleNights = $totalActiveAccommodations * $daysInMonth;
$globalOccupancy = $totalPossibleNights > 0 ? min(100, round(($totalOccupiedNights / $totalPossibleNights) * 100, 1)) : 0.0;

$adminPage = 'disponibilidade';
$pageTitle = 'Disponibilidade';
$bodyClass = 'admin-body admin-availability';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-page-intro">
    <div>
        <p class="admin-eyebrow">Ocupação</p>
        <h2>Calendário de disponibilidade</h2>
        <p>Visualize as noites ocupadas por reservas ativas. Reservas canceladas e concluídas não bloqueiam datas.</p>
    </div>
</div>

<section class="admin-card filter-card">
    <form class="admin-filter-form availability-filter" method="get" action="<?= e(url('admin/disponibilidade.php')) ?>">
        <div class="form-field">
            <label for="availability-month">Mês</label>
            <input type="month" id="availability-month" name="mes" value="<?= e($monthInput) ?>">
        </div>
        <div class="form-field">
            <label for="availability-accommodation">Alojamento</label>
            <select id="availability-accommodation" name="alojamento">
                <option value="0">Todos os alojamentos</option>
                <?php foreach ($accommodations as $option): ?>
                    <option value="<?= (int) $option['id'] ?>"<?= selected($selectedAccommodationId, (int) $option['id']) ?>><?= e($option['name']) ?> — <?= e($option['location']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-filter-actions">
            <button class="btn btn-primary btn-small" type="submit">Ver disponibilidade</button>
            <a class="btn btn-ghost btn-small" href="<?= e(url('admin/disponibilidade.php') . '?mes=' . date('Y-m') . '&alojamento=' . $selectedAccommodationId) ?>">Mês atual</a>
        </div>
    </form>
</section>

<section class="availability-summary-grid">
    <article class="admin-card availability-summary-card">
        <span class="availability-summary-icon"><?= icon('calendar') ?></span>
        <div><strong><?= e($monthLabel) ?></strong><small>Período em análise</small></div>
    </article>
    <article class="admin-card availability-summary-card">
        <span class="availability-summary-icon"><?= icon('home') ?></span>
        <div><strong><?= e($selectedAccommodation !== null ? $selectedAccommodation['name'] : $totalActiveAccommodations . ' alojamentos') ?></strong><small><?= $selectedAccommodation !== null ? 'Alojamento selecionado' : 'Visão agregada' ?></small></div>
    </article>
    <article class="admin-card availability-summary-card">
        <span class="availability-summary-icon"><?= icon('chart') ?></span>
        <div>
            <strong><?= e(number_format($selectedAccommodation !== null ? $selectedOccupancy : $globalOccupancy, 1, ',', '')) ?>%</strong>
            <small>Taxa de ocupação no mês</small>
        </div>
    </article>
    <article class="admin-card availability-summary-card">
        <span class="availability-summary-icon"><?= icon('moon') ?></span>
        <div>
            <strong><?= e($selectedAccommodation !== null ? $selectedOccupied : $totalOccupiedNights) ?></strong>
            <small>Noites ocupadas</small>
        </div>
    </article>
</section>

<?php if ($selectedAccommodation !== null): ?>
    <div class="availability-detail-grid">
        <section class="admin-card calendar-card">
            <div class="calendar-head">
                <div>
                    <h2><?= e($selectedAccommodation['name']) ?></h2>
                    <p><?= e($selectedAccommodation['location']) ?> · <?= e($monthLabel) ?></p>
                </div>
                <div class="calendar-nav">
                    <a class="icon-link" href="<?= e(url('admin/disponibilidade.php') . '?mes=' . $previousMonth . '&alojamento=' . $selectedAccommodationId) ?>" aria-label="Mês anterior"><?= icon('chevron-left') ?></a>
                    <a class="btn btn-ghost btn-small" href="<?= e(url('admin/disponibilidade.php') . '?mes=' . date('Y-m') . '&alojamento=' . $selectedAccommodationId) ?>">Hoje</a>
                    <a class="icon-link" href="<?= e(url('admin/disponibilidade.php') . '?mes=' . $nextMonth . '&alojamento=' . $selectedAccommodationId) ?>" aria-label="Mês seguinte"><?= icon('chevron-right') ?></a>
                </div>
            </div>

            <div class="calendar-weekdays" aria-hidden="true">
                <span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sáb</span><span>Dom</span>
            </div>

            <div class="calendar-grid" role="grid" aria-label="Calendário de ocupação de <?= e($selectedAccommodation['name']) ?>">
                <?php for ($blank = 1; $blank < $firstWeekday; $blank++): ?>
                    <div class="calendar-day calendar-day-empty" aria-hidden="true"></div>
                <?php endfor; ?>

                <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                    <?php
                    $dateKey = $monthDate->format('Y-m') . '-' . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                    $events = $dayEvents[$dateKey] ?? [];
                    $checkouts = $checkoutDays[$dateKey] ?? [];
                    $hasPending = false;
                    foreach ($events as $event) {
                        if ($event['status'] === 'pendente') {
                            $hasPending = true;
                        }
                    }
                    $isToday = $dateKey === $today;
                    $isWeekend = (int) date('N', strtotime($dateKey)) >= 6;
                    $classes = ['calendar-day'];
                    if ($isWeekend) {
                        $classes[] = 'calendar-day-weekend';
                    }
                    if ($isToday) {
                        $classes[] = 'calendar-day-today';
                    }
                    if ($events !== []) {
                        $classes[] = $hasPending ? 'calendar-day-pending' : 'calendar-day-booked';
                    }
                    if ($events === [] && $checkouts !== []) {
                        $classes[] = 'calendar-day-checkout';
                    }
                    ?>
                    <div class="<?= e(implode(' ', $classes)) ?>" role="gridcell" aria-label="<?= e(format_date($dateKey, true)) ?><?= $events !== [] ? ', ocupado' : ', disponível' ?>">
                        <span class="calendar-day-number"><?= $day ?></span>
                        <?php if ($isToday): ?><span class="calendar-today-label">Hoje</span><?php endif; ?>
                        <?php foreach (array_slice($events, 0, 1) as $event): ?>
                            <span class="calendar-event" title="<?= e($event['guest'] . ' · ' . $event['code']) ?>">
                                <?= $event['check_in'] ? 'Entrada' : 'Ocupado' ?>
                            </span>
                        <?php endforeach; ?>
                        <?php if ($checkouts !== []): ?>
                            <span class="calendar-event calendar-event-checkout" title="Saída: <?= e(implode(', ', array_column($checkouts, 'guest'))) ?>">Saída</span>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="calendar-legend">
                <span><i class="legend-swatch legend-available"></i> Disponível</span>
                <span><i class="legend-swatch legend-booked"></i> Confirmada</span>
                <span><i class="legend-swatch legend-pending"></i> Pendente</span>
                <span><i class="legend-swatch legend-checkout"></i> Saída</span>
                <span><i class="legend-swatch legend-today"></i> Hoje</span>
            </div>
        </section>

        <aside class="availability-side">
            <section class="admin-card">
                <div class="admin-card-head">
                    <div>
                        <h2>Reservas no mês</h2>
                        <p><?= count($selectedReservations) ?> <?= count($selectedReservations) === 1 ? 'reserva ativa' : 'reservas ativas' ?>.</p>
                    </div>
                </div>

                <?php if ($selectedReservations === []): ?>
                    <div class="admin-empty"><?= icon('calendar') ?><p>Sem reservas ativas neste mês.</p></div>
                <?php else: ?>
                    <ul class="availability-reservation-list">
                        <?php foreach ($selectedReservations as $reservation): ?>
                            <li>
                                <div>
                                    <a class="table-code" href="<?= e(url('admin/detalhes-reserva.php') . '?id=' . (int) $reservation['id']) ?>"><?= e($reservation['reservation_code']) ?></a>
                                    <strong><?= e($reservation['guest_name']) ?></strong>
                                    <span><?= e(format_date((string) $reservation['check_in'])) ?> → <?= e(format_date((string) $reservation['check_out'])) ?></span>
                                </div>
                                <?= status_badge((string) $reservation['status']) ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <section class="admin-card">
                <div class="admin-card-head">
                    <div>
                        <h2>Resumo do mês</h2>
                    </div>
                </div>
                <dl class="guest-summary-list">
                    <div><dt><?= icon('calendar') ?> Dias do mês</dt><dd><?= e($daysInMonth) ?></dd></div>
                    <div><dt><?= icon('moon') ?> Noites ocupadas</dt><dd><?= e($selectedOccupied) ?></dd></div>
                    <div><dt><?= icon('home') ?> Noites livres</dt><dd><?= e(max(0, $daysInMonth - $selectedOccupied)) ?></dd></div>
                    <div><dt><?= icon('chart') ?> Ocupação</dt><dd><?= e(number_format($selectedOccupancy, 1, ',', '')) ?>%</dd></div>
                    <div class="guest-summary-total"><dt><?= icon('tag') ?> Valor das reservas</dt><dd><?= e(money((float) $selectedAccommodation['reservation_value'])) ?></dd></div>
                </dl>
                <a class="btn btn-outline btn-block" href="<?= e(url('admin/reservas.php') . '?estado=pendente') ?>"><?= icon('calendar') ?> Gerir reservas pendentes</a>
            </section>
        </aside>
    </div>
<?php else: ?>
    <section class="admin-card admin-table-card">
        <div class="admin-card-head">
            <div>
                <h2>Ocupação por alojamento</h2>
                <p>Resumo de <?= e($monthLabel) ?>. Selecione uma casa para ver o calendário diário.</p>
            </div>
            <span class="card-badge"><?= e(number_format($globalOccupancy, 1, ',', '')) ?>% global</span>
        </div>

        <?php if ($overview === []): ?>
            <div class="admin-empty admin-empty-large">
                <?= icon('home') ?>
                <h3>Sem alojamentos ativos</h3>
                <p>Ative pelo menos um alojamento para acompanhar a disponibilidade.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table availability-table">
                    <thead>
                        <tr>
                            <th>Alojamento</th>
                            <th>Noites ocupadas</th>
                            <th>Ocupação</th>
                            <th>Reservas</th>
                            <th>Valor das reservas</th>
                            <th class="table-action">Calendário</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overview as $row): ?>
                            <?php
                            $rowOccupied = (int) $row['occupied_nights'];
                            $rowRate = $daysInMonth > 0 ? min(100, round(($rowOccupied / $daysInMonth) * 100, 1)) : 0.0;
                            ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['name']) ?></strong>
                                    <small class="table-subnote"><?= icon('pin') ?> <?= e($row['location']) ?> · <?= e($row['capacity']) ?> hóspedes</small>
                                </td>
                                <td><?= e($rowOccupied) ?> de <?= e($daysInMonth) ?></td>
                                <td>
                                    <div class="inline-progress">
                                        <div class="progress-track"><span class="progress-fill progress-success" style="width: <?= e((string) $rowRate) ?>%"></span></div>
                                        <span><?= e(number_format($rowRate, 1, ',', '')) ?>%</span>
                                    </div>
                                </td>
                                <td><span class="reservation-count"><?= e($row['reservation_count']) ?></span></td>
                                <td class="table-money"><?= e(money((float) $row['reservation_value'])) ?></td>
                                <td class="table-action">
                                    <a class="btn btn-ghost btn-small" href="<?= e(url('admin/disponibilidade.php') . '?mes=' . $monthInput . '&alojamento=' . (int) $row['id']) ?>"><?= icon('grid') ?> Ver calendário</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
