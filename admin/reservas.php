<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();

if (is_post()) {
    verify_csrf('admin/reservas.php');
    $action = input_string('action', 'post');
    $reservationId = input_int('id', 'post', 0) ?? 0;
    $result = perform_reservation_action($reservationId, $action);
    set_flash($result['ok'] ? 'success' : 'error', $result['message']);
    redirect('admin/reservas.php');
}

$statusCounts = [
    'pendente' => 0,
    'confirmada' => 0,
    'cancelada' => 0,
    'concluida' => 0,
];
$statusCountStmt = $pdo->query('SELECT status, COUNT(*) AS total FROM reservations GROUP BY status');
foreach ($statusCountStmt->fetchAll() as $row) {
    if (array_key_exists((string) $row['status'], $statusCounts)) {
        $statusCounts[(string) $row['status']] = (int) $row['total'];
    }
}

$search = input_string('q', 'get');
$statusFilter = input_string('estado', 'get');
$sort = input_string('ordenar', 'get', 'check_in_desc');
$page = max(1, input_int('pagina', 'get', 1) ?? 1);

if ($statusFilter !== '' && !array_key_exists($statusFilter, reservation_statuses())) {
    $statusFilter = '';
}

$where = ['1 = 1'];
$params = [];

if ($search !== '') {
    $where[] = '(r.reservation_code LIKE :search OR g.full_name LIKE :search OR g.email LIKE :search OR a.name LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
if ($statusFilter !== '') {
    $where[] = 'r.status = :status';
    $params[':status'] = $statusFilter;
}

$allowedSorts = [
    'check_in_desc' => 'r.check_in DESC, r.id DESC',
    'check_in_asc'  => 'r.check_in ASC, r.id ASC',
    'created_desc'  => 'r.created_at DESC, r.id DESC',
    'total_desc'    => 'r.total_price DESC, r.id DESC',
    'code_asc'      => 'r.reservation_code ASC',
];
$orderBy = $allowedSorts[$sort] ?? $allowedSorts['check_in_desc'];
$whereSql = implode(' AND ', $where);

$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM reservations r
     INNER JOIN guests g ON g.id = r.guest_id
     INNER JOIN accommodations a ON a.id = r.accommodation_id
     WHERE $whereSql"
);
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();

$perPage = 12;
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT r.*, g.full_name AS guest_name, g.email AS guest_email,
            a.name AS accommodation_name, a.location AS accommodation_location
     FROM reservations r
     INNER JOIN guests g ON g.id = r.guest_id
     INNER JOIN accommodations a ON a.id = r.accommodation_id
     WHERE $whereSql
     ORDER BY $orderBy
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$reservations = $stmt->fetchAll();

$hasFilters = $search !== '' || $statusFilter !== '';

$adminPage = 'reservas';
$pageTitle = 'Reservas';
$bodyClass = 'admin-body admin-reservations';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-page-intro">
    <div>
        <p class="admin-eyebrow">Operação</p>
        <h2>Gerir reservas</h2>
        <p>Pesquise, confirme, cancele ou conclua reservas. Cada alteração é guardada na base de dados.</p>
    </div>
    <div class="admin-intro-actions">
        <a class="btn btn-outline btn-small" href="<?= e(url('admin/disponibilidade.php')) ?>"><?= icon('chart') ?> Ver ocupação</a>
    </div>
</div>

<section class="status-summary-grid" aria-label="Resumo de reservas por estado">
    <a class="status-summary-card status-summary-warning<?= $statusFilter === 'pendente' ? ' is-selected' : '' ?>" href="<?= e(url('admin/reservas.php') . '?estado=pendente') ?>">
        <span><?= icon('clock') ?></span>
        <div><strong><?= e($statusCounts['pendente']) ?></strong><small>Pendentes</small></div>
    </a>
    <a class="status-summary-card status-summary-success<?= $statusFilter === 'confirmada' ? ' is-selected' : '' ?>" href="<?= e(url('admin/reservas.php') . '?estado=confirmada') ?>">
        <span><?= icon('check') ?></span>
        <div><strong><?= e($statusCounts['confirmada']) ?></strong><small>Confirmadas</small></div>
    </a>
    <a class="status-summary-card status-summary-neutral<?= $statusFilter === 'concluida' ? ' is-selected' : '' ?>" href="<?= e(url('admin/reservas.php') . '?estado=concluida') ?>">
        <span><?= icon('clipboard') ?></span>
        <div><strong><?= e($statusCounts['concluida']) ?></strong><small>Concluídas</small></div>
    </a>
    <a class="status-summary-card status-summary-danger<?= $statusFilter === 'cancelada' ? ' is-selected' : '' ?>" href="<?= e(url('admin/reservas.php') . '?estado=cancelada') ?>">
        <span><?= icon('close') ?></span>
        <div><strong><?= e($statusCounts['cancelada']) ?></strong><small>Canceladas</small></div>
    </a>
</section>

<section class="admin-card filter-card">
    <form class="admin-filter-form" method="get" action="<?= e(url('admin/reservas.php')) ?>">
        <div class="form-field admin-search-field">
            <label for="reservation-search">Pesquisar</label>
            <div class="input-with-icon">
                <?= icon('search') ?>
                <input type="search" id="reservation-search" name="q" value="<?= e($search) ?>" placeholder="Código, hóspede, email ou alojamento">
            </div>
        </div>
        <div class="form-field">
            <label for="reservation-status">Estado</label>
            <select id="reservation-status" name="estado">
                <option value="">Todos os estados</option>
                <?php foreach (reservation_statuses() as $statusKey => $meta): ?>
                    <option value="<?= e($statusKey) ?>"<?= selected($statusFilter, $statusKey) ?>><?= e($meta['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label for="reservation-sort">Ordenar</label>
            <select id="reservation-sort" name="ordenar">
                <option value="check_in_desc"<?= selected($sort, 'check_in_desc') ?>>Entrada mais recente</option>
                <option value="check_in_asc"<?= selected($sort, 'check_in_asc') ?>>Entrada mais antiga</option>
                <option value="created_desc"<?= selected($sort, 'created_desc') ?>>Pedido mais recente</option>
                <option value="total_desc"<?= selected($sort, 'total_desc') ?>>Valor mais alto</option>
                <option value="code_asc"<?= selected($sort, 'code_asc') ?>>Código</option>
            </select>
        </div>
        <div class="admin-filter-actions">
            <button class="btn btn-primary btn-small" type="submit">Filtrar</button>
            <?php if ($hasFilters): ?>
                <a class="btn btn-ghost btn-small" href="<?= e(url('admin/reservas.php')) ?>">Limpar</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="admin-card admin-table-card">
    <div class="admin-card-head">
        <div>
            <h2>Lista de reservas</h2>
            <p><?= e($totalRows) ?> <?= $totalRows === 1 ? 'reserva encontrada' : 'reservas encontradas' ?>.</p>
        </div>
        <span class="card-badge"><?= e(count($reservations)) ?> nesta página</span>
    </div>

    <?php if ($reservations === []): ?>
        <div class="admin-empty admin-empty-large">
            <?= icon('calendar') ?>
            <h3>Nenhuma reserva encontrada</h3>
            <p>Altere a pesquisa ou os filtros de estado.</p>
            <a class="btn btn-primary" href="<?= e(url('admin/reservas.php')) ?>">Ver todas as reservas</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="admin-table admin-table-reservations">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Hóspede</th>
                        <th>Alojamento</th>
                        <th>Entrada</th>
                        <th>Saída</th>
                        <th>Hósp.</th>
                        <th>Valor</th>
                        <th>Estado</th>
                        <th class="table-actions">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $reservation): ?>
                        <tr>
                            <td>
                                <a class="table-code" href="<?= e(url('admin/detalhes-reserva.php') . '?id=' . (int) $reservation['id']) ?>"><?= e($reservation['reservation_code']) ?></a>
                            </td>
                            <td>
                                <strong class="table-guest"><?= e($reservation['guest_name']) ?></strong>
                                <small class="table-subnote"><?= e($reservation['guest_email']) ?></small>
                            </td>
                            <td>
                                <span><?= e($reservation['accommodation_name']) ?></span>
                                <small class="table-subnote"><?= icon('pin') ?> <?= e($reservation['accommodation_location']) ?></small>
                            </td>
                            <td><?= e(format_date((string) $reservation['check_in'])) ?></td>
                            <td><?= e(format_date((string) $reservation['check_out'])) ?></td>
                            <td><?= e($reservation['guests_count']) ?></td>
                            <td class="table-money"><?= e(money((float) $reservation['total_price'])) ?></td>
                            <td><?= status_badge((string) $reservation['status']) ?></td>
                            <td class="table-actions">
                                <div class="row-actions">
                                    <a class="icon-link" href="<?= e(url('admin/detalhes-reserva.php') . '?id=' . (int) $reservation['id']) ?>" title="Ver detalhes" aria-label="Ver detalhes da reserva <?= e($reservation['reservation_code']) ?>">
                                        <?= icon('eye') ?>
                                    </a>

                                    <?php if ($reservation['status'] === 'pendente'): ?>
                                        <form method="post" action="<?= e(url('admin/reservas.php')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="confirm">
                                            <input type="hidden" name="id" value="<?= (int) $reservation['id'] ?>">
                                            <button type="submit" class="icon-link icon-button icon-button-success" title="Confirmar reserva" aria-label="Confirmar reserva <?= e($reservation['reservation_code']) ?>">
                                                <?= icon('check') ?>
                                            </button>
                                        </form>
                                    <?php elseif ($reservation['status'] === 'confirmada' && (string) $reservation['check_out'] <= today()): ?>
                                        <form method="post" action="<?= e(url('admin/reservas.php')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="complete">
                                            <input type="hidden" name="id" value="<?= (int) $reservation['id'] ?>">
                                            <button type="submit" class="icon-link icon-button" title="Marcar como concluída" aria-label="Concluir reserva <?= e($reservation['reservation_code']) ?>">
                                                <?= icon('clipboard') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (in_array($reservation['status'], ['pendente', 'confirmada'], true)): ?>
                                        <form method="post" action="<?= e(url('admin/reservas.php')) ?>" data-confirm="Cancelar a reserva <?= e($reservation['reservation_code']) ?>? As datas ficarão novamente disponíveis.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="id" value="<?= (int) $reservation['id'] ?>">
                                            <button type="submit" class="icon-link icon-button icon-button-danger" title="Cancelar reserva" aria-label="Cancelar reserva <?= e($reservation['reservation_code']) ?>">
                                                <?= icon('close') ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination admin-pagination" aria-label="Paginação de reservas">
                <?php if ($page > 1): ?>
                    <a class="pagination-link pagination-arrow" href="<?= e(url('admin/reservas.php') . '?' . current_query(['pagina' => $page - 1])) ?>"><?= icon('chevron-left') ?></a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="pagination-link is-current" aria-current="page"><?= $i ?></span>
                    <?php else: ?>
                        <a class="pagination-link" href="<?= e(url('admin/reservas.php') . '?' . current_query(['pagina' => $i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="pagination-link pagination-arrow" href="<?= e(url('admin/reservas.php') . '?' . current_query(['pagina' => $page + 1])) ?>"><?= icon('chevron-right') ?></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
