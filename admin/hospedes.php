<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();

$stats = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM guests) AS total_guests,
        (SELECT COUNT(DISTINCT g.id) FROM guests g
            INNER JOIN reservations r ON r.guest_id = g.id
            WHERE r.status IN ('pendente', 'confirmada') AND r.check_out >= CURDATE()) AS active_guests,
        (SELECT COALESCE(SUM(total_price), 0) FROM reservations
            WHERE status IN ('confirmada', 'concluida')) AS guest_revenue"
)->fetch();

$search = input_string('q', 'get');
$sort = input_string('ordenar', 'get', 'name');
$page = max(1, input_int('pagina', 'get', 1) ?? 1);

$where = ['1 = 1'];
$params = [];
if ($search !== '') {
    $where[] = '(g.full_name LIKE :search OR g.email LIKE :search OR g.phone LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
$whereSql = implode(' AND ', $where);

$allowedSorts = [
    'name'         => 'g.full_name ASC',
    'recent'       => 'g.created_at DESC',
    'reservations' => 'reservation_count DESC, g.full_name ASC',
    'value'        => 'total_value DESC, g.full_name ASC',
];
$orderBy = $allowedSorts[$sort] ?? $allowedSorts['name'];

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM guests g WHERE $whereSql");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();

$perPage = 12;
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT g.*,
            (SELECT COUNT(*) FROM reservations r WHERE r.guest_id = g.id) AS reservation_count,
            (SELECT COUNT(*) FROM reservations r
                WHERE r.guest_id = g.id AND r.status IN ('pendente', 'confirmada') AND r.check_out >= CURDATE()) AS active_count,
            (SELECT COALESCE(SUM(r.total_price), 0) FROM reservations r
                WHERE r.guest_id = g.id AND r.status IN ('confirmada', 'concluida')) AS total_value,
            (SELECT MAX(r.check_in) FROM reservations r WHERE r.guest_id = g.id) AS last_stay
     FROM guests g
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
$guests = $stmt->fetchAll();

$hasFilters = $search !== '';

$adminPage = 'hospedes';
$pageTitle = 'Hóspedes';
$bodyClass = 'admin-body admin-guests';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-page-intro">
    <div>
        <p class="admin-eyebrow">Relação com o hóspede</p>
        <h2>Gerir hóspedes</h2>
        <p>Consulte contactos e o histórico de reservas de cada hóspede. Apenas são recolhidos os dados necessários à reserva.</p>
    </div>
    <div class="admin-intro-actions">
        <a class="btn btn-outline btn-small" href="<?= e(url('admin/reservas.php')) ?>"><?= icon('calendar') ?> Ver reservas</a>
    </div>
</div>

<section class="stat-grid stat-grid-compact">
    <article class="stat-card">
        <div class="stat-card-head">
            <span class="stat-label">Hóspedes registados</span>
            <span class="stat-icon"><?= icon('users') ?></span>
        </div>
        <p class="stat-value"><?= e((int) $stats['total_guests']) ?></p>
        <p class="stat-note">Contactos na base de dados</p>
    </article>
    <article class="stat-card">
        <div class="stat-card-head">
            <span class="stat-label">Com estadia ativa</span>
            <span class="stat-icon"><?= icon('calendar') ?></span>
        </div>
        <p class="stat-value"><?= e((int) $stats['active_guests']) ?></p>
        <p class="stat-note">Reservas pendentes ou confirmadas</p>
    </article>
    <article class="stat-card stat-card-highlight">
        <div class="stat-card-head">
            <span class="stat-label">Valor acumulado</span>
            <span class="stat-icon"><?= icon('chart') ?></span>
        </div>
        <p class="stat-value"><?= e(money((float) $stats['guest_revenue'])) ?></p>
        <p class="stat-note">Reservas confirmadas e concluídas</p>
    </article>
</section>

<section class="admin-card filter-card">
    <form class="admin-filter-form" method="get" action="<?= e(url('admin/hospedes.php')) ?>">
        <div class="form-field admin-search-field">
            <label for="guest-search">Pesquisar</label>
            <div class="input-with-icon">
                <?= icon('search') ?>
                <input type="search" id="guest-search" name="q" value="<?= e($search) ?>" placeholder="Nome, email ou telefone">
            </div>
        </div>
        <div class="form-field">
            <label for="guest-sort">Ordenar</label>
            <select id="guest-sort" name="ordenar">
                <option value="name"<?= selected($sort, 'name') ?>>Nome</option>
                <option value="recent"<?= selected($sort, 'recent') ?>>Registo mais recente</option>
                <option value="reservations"<?= selected($sort, 'reservations') ?>>Mais reservas</option>
                <option value="value"<?= selected($sort, 'value') ?>>Valor acumulado</option>
            </select>
        </div>
        <div class="admin-filter-actions">
            <button class="btn btn-primary btn-small" type="submit">Filtrar</button>
            <?php if ($hasFilters): ?>
                <a class="btn btn-ghost btn-small" href="<?= e(url('admin/hospedes.php')) ?>">Limpar</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="admin-card admin-table-card">
    <div class="admin-card-head">
        <div>
            <h2>Lista de hóspedes</h2>
            <p><?= e($totalRows) ?> <?= $totalRows === 1 ? 'hóspede encontrado' : 'hóspedes encontrados' ?>.</p>
        </div>
        <span class="card-badge"><?= e(count($guests)) ?> nesta página</span>
    </div>

    <?php if ($guests === []): ?>
        <div class="admin-empty admin-empty-large">
            <?= icon('users') ?>
            <h3>Nenhum hóspede encontrado</h3>
            <p>Altere o termo de pesquisa ou aguarde a primeira reserva.</p>
            <a class="btn btn-primary" href="<?= e(url('admin/hospedes.php')) ?>">Ver todos</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="admin-table admin-table-guests">
                <thead>
                    <tr>
                        <th>Hóspede</th>
                        <th>Contacto</th>
                        <th>Reservas</th>
                        <th>Ativas</th>
                        <th>Valor acumulado</th>
                        <th>Última entrada</th>
                        <th class="table-action">Ação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($guests as $guest): ?>
                        <?php
                        $nameParts = preg_split('/\s+/', trim((string) $guest['full_name'])) ?: [];
                        $initials = '';
                        foreach (array_slice($nameParts, 0, 2) as $part) {
                            $initials .= strtoupper(substr($part, 0, 1));
                        }
                        ?>
                        <tr>
                            <td>
                                <div class="guest-cell">
                                    <span class="guest-avatar"><?= e($initials !== '' ? $initials : '?') ?></span>
                                    <div>
                                        <strong><?= e($guest['full_name']) ?></strong>
                                        <small class="table-subnote">Hóspede desde <?= e(format_date((string) $guest['created_at'])) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a class="table-contact" href="mailto:<?= e($guest['email']) ?>"><?= icon('mail') ?> <?= e($guest['email']) ?></a>
                                <a class="table-contact" href="tel:<?= e(normalize_phone((string) $guest['phone'])) ?>"><?= icon('phone') ?> <?= e($guest['phone']) ?></a>
                            </td>
                            <td><span class="reservation-count"><?= e($guest['reservation_count']) ?></span></td>
                            <td>
                                <?php if ((int) $guest['active_count'] > 0): ?>
                                    <span class="badge badge-success"><?= e($guest['active_count']) ?> ativa(s)</span>
                                <?php else: ?>
                                    <span class="badge badge-neutral">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-money"><?= e(money((float) $guest['total_value'])) ?></td>
                            <td><?= $guest['last_stay'] !== null ? e(format_date((string) $guest['last_stay'])) : '—' ?></td>
                            <td class="table-action">
                                <a class="icon-link" href="<?= e(url('admin/hospede.php') . '?id=' . (int) $guest['id']) ?>" title="Ver ficha" aria-label="Ver ficha de <?= e($guest['full_name']) ?>"><?= icon('eye') ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination admin-pagination" aria-label="Paginação de hóspedes">
                <?php if ($page > 1): ?>
                    <a class="pagination-link pagination-arrow" href="<?= e(url('admin/hospedes.php') . '?' . current_query(['pagina' => $page - 1])) ?>"><?= icon('chevron-left') ?></a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="pagination-link is-current" aria-current="page"><?= $i ?></span>
                    <?php else: ?>
                        <a class="pagination-link" href="<?= e(url('admin/hospedes.php') . '?' . current_query(['pagina' => $i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="pagination-link pagination-arrow" href="<?= e(url('admin/hospedes.php') . '?' . current_query(['pagina' => $page + 1])) ?>"><?= icon('chevron-right') ?></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
