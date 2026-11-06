<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();

if (is_post()) {
    verify_csrf('admin/alojamentos.php');
    $action = input_string('action', 'post');
    $id = input_int('id', 'post', 0) ?? 0;
    $accommodation = find_accommodation($id, true);

    if ($accommodation === false) {
        set_flash('error', 'O alojamento indicado não existe.');
        redirect('admin/alojamentos.php');
    }

    if ($action === 'toggle_active') {
        $stmt = $pdo->prepare('UPDATE accommodations SET active = :active WHERE id = :id');
        $stmt->execute([':active' => (int) $accommodation['active'] === 1 ? 0 : 1, ':id' => $id]);
        set_flash('success', (int) $accommodation['active'] === 1
            ? 'Alojamento desativado. Deixou de aparecer no site público.'
            : 'Alojamento ativado e novamente visível no site público.');
        redirect('admin/alojamentos.php');
    }

    if ($action === 'delete') {
        $reservationCountStmt = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE accommodation_id = :id');
        $reservationCountStmt->execute([':id' => $id]);
        $reservationCount = (int) $reservationCountStmt->fetchColumn();

        if ($reservationCount > 0) {
            set_flash('error', 'Não é possível eliminar este alojamento: existem ' . $reservationCount . ' reservas associadas. Desative-o para o retirar do catálogo sem perder o histórico.');
            redirect('admin/alojamentos.php');
        }

        $stmt = $pdo->prepare('DELETE FROM accommodations WHERE id = :id');
        $stmt->execute([':id' => $id]);
        set_flash('success', 'Alojamento eliminado. Não existiam reservas associadas.');
        redirect('admin/alojamentos.php');
    }

    set_flash('error', 'Ação não reconhecida.');
    redirect('admin/alojamentos.php');
}

$search = input_string('q', 'get');
$statusFilter = input_string('estado', 'get');
$typeFilter = input_string('tipo', 'get');
$sort = input_string('ordenar', 'get', 'name');
$page = max(1, input_int('pagina', 'get', 1) ?? 1);

$where = ['1 = 1'];
$params = [];

if ($search !== '') {
    $where[] = '(a.name LIKE :search OR a.location LIKE :search OR a.address LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
if ($statusFilter === 'ativos') {
    $where[] = 'a.active = 1';
} elseif ($statusFilter === 'inativos') {
    $where[] = 'a.active = 0';
}
if ($typeFilter !== '' && array_key_exists($typeFilter, accommodation_types())) {
    $where[] = 'a.type = :type';
    $params[':type'] = $typeFilter;
}

$allowedSorts = [
    'name'       => 'a.name ASC',
    'price_asc'  => 'a.price_per_night ASC',
    'price_desc' => 'a.price_per_night DESC',
    'newest'     => 'a.created_at DESC',
];
$orderBy = $allowedSorts[$sort] ?? $allowedSorts['name'];

$whereSql = implode(' AND ', $where);
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM accommodations a WHERE $whereSql");
$countStmt->execute($params);
$totalRows = (int) $countStmt->fetchColumn();

$perPage = 10;
$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare(
    "SELECT a.*,
            (SELECT COUNT(*) FROM reservations r WHERE r.accommodation_id = a.id) AS reservation_count,
            (SELECT COUNT(*) FROM reservations r
                WHERE r.accommodation_id = a.id
                  AND r.status IN ('pendente', 'confirmada')
                  AND r.check_out >= CURDATE()) AS upcoming_count
     FROM accommodations a
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
$accommodations = $stmt->fetchAll();

$hasFilters = $search !== '' || $statusFilter !== '' || $typeFilter !== '';

$adminPage = 'alojamentos';
$pageTitle = 'Alojamentos';
$bodyClass = 'admin-body admin-accommodations';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-page-intro">
    <div>
        <p class="admin-eyebrow">Catálogo</p>
        <h2>Gerir alojamentos</h2>
        <p><?= e($totalRows) ?> <?= $totalRows === 1 ? 'alojamento encontrado' : 'alojamentos encontrados' ?>. Crie, edite, desative ou elimine registos sem reservas.</p>
    </div>
    <div class="admin-intro-actions">
        <a class="btn btn-primary btn-small" href="<?= e(url('admin/criar-alojamento.php')) ?>"><?= icon('plus') ?> Novo alojamento</a>
    </div>
</div>

<section class="admin-card filter-card">
    <form class="admin-filter-form" method="get" action="<?= e(url('admin/alojamentos.php')) ?>">
        <div class="form-field admin-search-field">
            <label for="admin-search">Pesquisar</label>
            <div class="input-with-icon">
                <?= icon('search') ?>
                <input type="search" id="admin-search" name="q" value="<?= e($search) ?>" placeholder="Nome, localização ou morada">
            </div>
        </div>
        <div class="form-field">
            <label for="admin-status">Estado</label>
            <select id="admin-status" name="estado">
                <option value="">Todos</option>
                <option value="ativos"<?= selected($statusFilter, 'ativos') ?>>Ativos</option>
                <option value="inativos"<?= selected($statusFilter, 'inativos') ?>>Inativos</option>
            </select>
        </div>
        <div class="form-field">
            <label for="admin-type">Tipo</label>
            <select id="admin-type" name="tipo">
                <option value="">Todos</option>
                <?php foreach (accommodation_types() as $typeKey => $typeLabel): ?>
                    <option value="<?= e($typeKey) ?>"<?= selected($typeFilter, $typeKey) ?>><?= e($typeLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-field">
            <label for="admin-sort">Ordenar</label>
            <select id="admin-sort" name="ordenar">
                <option value="name"<?= selected($sort, 'name') ?>>Nome</option>
                <option value="newest"<?= selected($sort, 'newest') ?>>Mais recentes</option>
                <option value="price_asc"<?= selected($sort, 'price_asc') ?>>Preço crescente</option>
                <option value="price_desc"<?= selected($sort, 'price_desc') ?>>Preço decrescente</option>
            </select>
        </div>
        <div class="admin-filter-actions">
            <button class="btn btn-primary btn-small" type="submit">Filtrar</button>
            <?php if ($hasFilters): ?>
                <a class="btn btn-ghost btn-small" href="<?= e(url('admin/alojamentos.php')) ?>">Limpar</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<section class="admin-card admin-table-card">
    <div class="admin-card-head">
        <div>
            <h2>Catálogo LumaStay</h2>
            <p>Estado atual dos alojamentos e reservas associadas.</p>
        </div>
        <span class="card-badge"><?= e($totalRows) ?> registos</span>
    </div>

    <?php if ($accommodations === []): ?>
        <div class="admin-empty admin-empty-large">
            <?= icon('home') ?>
            <h3>Nenhum alojamento encontrado</h3>
            <p>Altere os filtros ou crie o primeiro alojamento do catálogo.</p>
            <a class="btn btn-primary" href="<?= e(url('admin/criar-alojamento.php')) ?>">Criar alojamento</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="admin-table admin-table-rooms">
                <thead>
                    <tr>
                        <th>Alojamento</th>
                        <th>Tipo</th>
                        <th>Preço/noite</th>
                        <th>Capacidade</th>
                        <th>Reservas</th>
                        <th>Estado</th>
                        <th class="table-actions">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accommodations as $row): ?>
                        <tr>
                            <td>
                                <div class="table-property">
                                    <img src="<?= e(image_src((string) $row['image_url'])) ?>"
                                         alt=""
                                         loading="lazy"
                                         data-fallback="<?= e(asset('images/fallback.svg')) ?>">
                                    <div>
                                        <strong><?= e($row['name']) ?></strong>
                                        <span><?= icon('pin') ?> <?= e($row['location']) ?></span>
                                        <?php if ((int) $row['featured'] === 1): ?>
                                            <small class="table-tag"><?= icon('sparkles') ?> Destaque</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= e(type_label((string) $row['type'])) ?></td>
                            <td class="table-money"><?= e(money((float) $row['price_per_night'])) ?></td>
                            <td><?= e($row['capacity']) ?> hóspedes</td>
                            <td>
                                <span class="reservation-count"><?= e($row['reservation_count']) ?></span>
                                <?php if ((int) $row['upcoming_count'] > 0): ?>
                                    <small class="table-subnote"><?= e($row['upcoming_count']) ?> futuras</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int) $row['active'] === 1): ?>
                                    <span class="badge badge-success">Ativo</span>
                                <?php else: ?>
                                    <span class="badge badge-neutral">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="table-actions">
                                <div class="row-actions">
                                    <a class="icon-link" href="<?= e(url('admin/editar-alojamento.php') . '?id=' . (int) $row['id']) ?>" aria-label="Editar <?= e($row['name']) ?>" title="Editar">
                                        <?= icon('edit') ?>
                                    </a>

                                    <form method="post" action="<?= e(url('admin/alojamentos.php')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                        <button type="submit" class="icon-link icon-button<?= (int) $row['active'] === 1 ? ' icon-button-warning' : ' icon-button-success' ?>" title="<?= (int) $row['active'] === 1 ? 'Desativar' : 'Ativar' ?>" aria-label="<?= (int) $row['active'] === 1 ? 'Desativar' : 'Ativar' ?> <?= e($row['name']) ?>">
                                            <?= icon('power') ?>
                                        </button>
                                    </form>

                                    <?php if ((int) $row['reservation_count'] === 0): ?>
                                        <form method="post" action="<?= e(url('admin/alojamentos.php')) ?>" data-confirm="Eliminar definitivamente <?= e($row['name']) ?>? Esta ação não pode ser revertida.">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="icon-link icon-button icon-button-danger" title="Eliminar" aria-label="Eliminar <?= e($row['name']) ?>">
                                                <?= icon('trash') ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="icon-link is-disabled" title="Não é possível eliminar: existem reservas associadas" aria-label="Eliminação bloqueada por reservas associadas">
                                            <?= icon('trash') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination admin-pagination" aria-label="Paginação de alojamentos">
                <?php if ($page > 1): ?>
                    <a class="pagination-link pagination-arrow" href="<?= e(url('admin/alojamentos.php') . '?' . current_query(['pagina' => $page - 1])) ?>"><?= icon('chevron-left') ?></a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="pagination-link is-current" aria-current="page"><?= $i ?></span>
                    <?php else: ?>
                        <a class="pagination-link" href="<?= e(url('admin/alojamentos.php') . '?' . current_query(['pagina' => $i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="pagination-link pagination-arrow" href="<?= e(url('admin/alojamentos.php') . '?' . current_query(['pagina' => $page + 1])) ?>"><?= icon('chevron-right') ?></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
