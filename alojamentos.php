<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pdo = db();
$errors = [];

$locations = $pdo->query("SELECT DISTINCT location FROM accommodations WHERE active = 1 ORDER BY location ASC")->fetchAll(PDO::FETCH_COLUMN);
$types = accommodation_types();

$destination = input_string('destination', 'get');
$type = input_string('type', 'get');
$checkIn = input_string('check_in', 'get');
$checkOut = input_string('check_out', 'get');
$guestsRaw = input_string('guests', 'get');
$minPriceRaw = input_string('min_price', 'get');
$maxPriceRaw = input_string('max_price', 'get');
$sort = input_string('sort', 'get', 'recommended');
$page = input_int('page', 'get', 1) ?? 1;
$page = max(1, $page);

$guests = 0;
$minPrice = null;
$maxPrice = null;
$dateFilter = false;

if ($destination !== '' && !in_array($destination, $locations, true)) {
    $errors[] = 'O destino selecionado não está disponível.';
    $destination = '';
}

if ($type !== '' && !array_key_exists($type, $types)) {
    $errors[] = 'O tipo de alojamento selecionado não é válido.';
    $type = '';
}

if ($guestsRaw !== '') {
    if (!preg_match('/^\d+$/', $guestsRaw) || (int) $guestsRaw < 1 || (int) $guestsRaw > 30) {
        $errors[] = 'O número de hóspedes deve estar entre 1 e 30.';
    } else {
        $guests = (int) $guestsRaw;
    }
}

if ($minPriceRaw !== '') {
    if (!is_numeric($minPriceRaw) || (float) $minPriceRaw < 0) {
        $errors[] = 'O preço mínimo deve ser um valor positivo.';
    } else {
        $minPrice = round((float) $minPriceRaw, 2);
    }
}

if ($maxPriceRaw !== '') {
    if (!is_numeric($maxPriceRaw) || (float) $maxPriceRaw < 0) {
        $errors[] = 'O preço máximo deve ser um valor positivo.';
    } else {
        $maxPrice = round((float) $maxPriceRaw, 2);
    }
}

if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
    $errors[] = 'O preço mínimo não pode ser superior ao preço máximo.';
    $minPrice = null;
    $maxPrice = null;
}

if ($checkIn !== '' || $checkOut !== '') {
    if ($checkIn === '' || $checkOut === '') {
        $errors[] = 'Indique a data de entrada e a data de saída para pesquisar disponibilidade.';
    } elseif (!validate_date_value($checkIn) || !validate_date_value($checkOut)) {
        $errors[] = 'As datas indicadas não são válidas.';
    } elseif ($checkIn < today()) {
        $errors[] = 'A data de entrada não pode estar no passado.';
    } elseif ($checkOut <= $checkIn) {
        $errors[] = 'A data de saída tem de ser posterior à data de entrada.';
    } else {
        $dateFilter = true;
    }
}

$allowedSorts = ['recommended', 'price_asc', 'price_desc', 'rating_desc', 'newest'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'recommended';
}

$where = ['a.active = 1'];
$params = [];

if ($destination !== '') {
    $where[] = 'a.location = :destination';
    $params[':destination'] = $destination;
}
if ($type !== '') {
    $where[] = 'a.type = :type';
    $params[':type'] = $type;
}
if ($guests > 0) {
    $where[] = 'a.capacity >= :guests';
    $params[':guests'] = $guests;
}
if ($minPrice !== null) {
    $where[] = 'a.price_per_night >= :min_price';
    $params[':min_price'] = $minPrice;
}
if ($maxPrice !== null) {
    $where[] = 'a.price_per_night <= :max_price';
    $params[':max_price'] = $maxPrice;
}
if ($dateFilter) {
    $where[] = "NOT EXISTS (
        SELECT 1 FROM reservations r
        WHERE r.accommodation_id = a.id
          AND r.status IN ('pendente', 'confirmada')
          AND r.check_in < :availability_check_out
          AND r.check_out > :availability_check_in
    )";
    $params[':availability_check_out'] = $checkOut;
    $params[':availability_check_in'] = $checkIn;
}

$whereSql = implode(' AND ', $where);

$orderSql = match ($sort) {
    'price_asc'   => 'a.price_per_night ASC, a.rating DESC, a.name ASC',
    'price_desc'  => 'a.price_per_night DESC, a.rating DESC, a.name ASC',
    'rating_desc' => 'a.rating DESC, a.review_count DESC, a.name ASC',
    'newest'      => 'a.created_at DESC, a.id DESC',
    default       => 'a.featured DESC, a.rating DESC, a.review_count DESC, a.name ASC',
};

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM accommodations a WHERE $whereSql");
$countStmt->execute($params);
$totalResults = (int) $countStmt->fetchColumn();

$perPage = 9;
$totalPages = max(1, (int) ceil($totalResults / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$listStmt = $pdo->prepare(
    "SELECT a.* FROM accommodations a
     WHERE $whereSql
     ORDER BY $orderSql
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $key => $value) {
    $listStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$accommodations = $listStmt->fetchAll();

$hasFilters = $destination !== '' || $type !== '' || $guests > 0 || $minPrice !== null || $maxPrice !== null || $dateFilter;
$filterSummary = [];
if ($destination !== '') {
    $filterSummary[] = $destination;
}
if ($dateFilter) {
    $filterSummary[] = format_date($checkIn) . ' — ' . format_date($checkOut);
}
if ($guests > 0) {
    $filterSummary[] = $guests . ' hóspedes';
}

$pageTitle = 'Alojamentos';
$pageDescription = 'Pesquise alojamentos por destino, datas, capacidade, tipo e preço. Veja disponibilidade em tempo real na LumaStay.';
$currentPage = 'alojamentos';
$bodyClass = 'page-listings';

include __DIR__ . '/includes/header.php';
?>

<section class="page-banner">
    <div class="container">
        <nav class="breadcrumb" aria-label="Trilho de navegação">
            <a href="<?= e(url('index.php')) ?>">Início</a>
            <span aria-hidden="true">/</span>
            <span>Alojamentos</span>
        </nav>
        <div class="page-banner-content">
            <div>
                <p class="eyebrow"><span class="eyebrow-line"></span> Encontre o seu lugar</p>
                <h1>Alojamentos com curadoria</h1>
                <p>Pesquise por destino, datas e preferências. Mostramos apenas casas disponíveis e prontas para receber.</p>
            </div>
            <div class="page-banner-stat">
                <strong><?= e($totalResults) ?></strong>
                <span><?= $totalResults === 1 ? 'alojamento encontrado' : 'alojamentos encontrados' ?></span>
            </div>
        </div>
    </div>
</section>

<section class="section listings-section">
    <div class="container listings-layout">
        <aside class="filter-panel">
            <div class="filter-panel-head">
                <h2><?= icon('filter') ?> Filtrar</h2>
                <a href="<?= e(url('alojamentos.php')) ?>">Limpar</a>
            </div>

            <form method="get" action="<?= e(url('alojamentos.php')) ?>" class="filter-form">
                <input type="hidden" name="sort" value="<?= e($sort) ?>">

                <div class="form-field">
                    <label for="filter-destination">Destino</label>
                    <select id="filter-destination" name="destination">
                        <option value="">Todos os destinos</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= e($location) ?>"<?= selected($destination, $location) ?>><?= e($location) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="filter-check-in">Entrada</label>
                    <input type="date" id="filter-check-in" name="check_in" value="<?= e($checkIn) ?>" min="<?= e(today()) ?>" data-check-in>
                </div>

                <div class="form-field">
                    <label for="filter-check-out">Saída</label>
                    <input type="date" id="filter-check-out" name="check_out" value="<?= e($checkOut) ?>" min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" data-check-out>
                </div>

                <div class="form-field">
                    <label for="filter-guests">Hóspedes</label>
                    <select id="filter-guests" name="guests">
                        <option value="">Qualquer número</option>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?= $i ?>"<?= selected($guests, $i) ?>><?= $i ?> <?= $i === 1 ? 'hóspede' : 'hóspedes' ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-field">
                    <label for="filter-type">Tipo de alojamento</label>
                    <select id="filter-type" name="type">
                        <option value="">Todos os tipos</option>
                        <?php foreach ($types as $typeKey => $typeLabel): ?>
                            <option value="<?= e($typeKey) ?>"<?= selected($type, $typeKey) ?>><?= e($typeLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <fieldset class="price-fieldset">
                    <legend>Preço por noite</legend>
                    <div class="price-fields">
                        <div class="form-field">
                            <label for="filter-min-price">Mínimo</label>
                            <input type="number" id="filter-min-price" name="min_price" min="0" step="5" placeholder="0" value="<?= e($minPriceRaw) ?>">
                        </div>
                        <div class="form-field">
                            <label for="filter-max-price">Máximo</label>
                            <input type="number" id="filter-max-price" name="max_price" min="0" step="5" placeholder="1000" value="<?= e($maxPriceRaw) ?>">
                        </div>
                    </div>
                </fieldset>

                <button class="btn btn-primary btn-block" type="submit"><?= icon('search') ?> Aplicar filtros</button>
            </form>

            <div class="filter-note">
                <?= icon('info') ?>
                <p>As datas são verificadas contra as reservas ativas. O preço final é calculado antes da confirmação.</p>
            </div>
        </aside>

        <div class="listings-results">
            <?= render_errors($errors) ?>

            <div class="results-toolbar">
                <div>
                    <p class="results-count">
                        <strong><?= e($totalResults) ?></strong>
                        <?= $totalResults === 1 ? 'resultado' : 'resultados' ?>
                        <?php if ($filterSummary !== []): ?>
                            <span class="results-context">· <?= e(implode(' · ', $filterSummary)) ?></span>
                        <?php endif; ?>
                    </p>
                    <?php if ($hasFilters): ?>
                        <div class="active-filters">
                            <?php if ($destination !== ''): ?><span><?= icon('pin') ?> <?= e($destination) ?></span><?php endif; ?>
                            <?php if ($dateFilter): ?><span><?= icon('calendar') ?> <?= e(format_date($checkIn)) ?> — <?= e(format_date($checkOut)) ?></span><?php endif; ?>
                            <?php if ($guests > 0): ?><span><?= icon('users') ?> <?= e($guests) ?> hóspedes</span><?php endif; ?>
                            <?php if ($type !== ''): ?><span><?= icon('home') ?> <?= e(type_label($type)) ?></span><?php endif; ?>
                            <?php if ($minPrice !== null || $maxPrice !== null): ?><span><?= icon('tag') ?> <?= e($minPrice !== null ? money($minPrice) : '0 €') ?> — <?= e($maxPrice !== null ? money($maxPrice) : 'sem limite') ?></span><?php endif; ?>
                            <a class="active-filters-clear" href="<?= e(url('alojamentos.php')) ?>">Limpar tudo</a>
                        </div>
                    <?php endif; ?>
                </div>

                <form class="sort-form" method="get" action="<?= e(url('alojamentos.php')) ?>">
                    <?php foreach ($_GET as $queryKey => $queryValue): ?>
                        <?php if (is_string($queryValue) && $queryValue !== '' && !in_array($queryKey, ['sort', 'page'], true)): ?>
                            <input type="hidden" name="<?= e((string) $queryKey) ?>" value="<?= e($queryValue) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <label for="sort-select">Ordenar por</label>
                    <select id="sort-select" name="sort" data-auto-submit>
                        <option value="recommended"<?= selected($sort, 'recommended') ?>>Recomendados</option>
                        <option value="price_asc"<?= selected($sort, 'price_asc') ?>>Preço crescente</option>
                        <option value="price_desc"<?= selected($sort, 'price_desc') ?>>Preço decrescente</option>
                        <option value="rating_desc"<?= selected($sort, 'rating_desc') ?>>Melhor avaliação</option>
                        <option value="newest"<?= selected($sort, 'newest') ?>>Mais recentes</option>
                    </select>
                    <button type="submit" class="btn btn-outline btn-small">Aplicar</button>
                </form>
            </div>

            <?php if ($accommodations === []): ?>
                <div class="empty-state empty-state-large">
                    <span class="empty-state-icon"><?= icon('search') ?></span>
                    <h2>Nenhum alojamento corresponde à sua pesquisa</h2>
                    <p>Tente alargar o intervalo de datas, aumentar o preço máximo ou escolher outro destino.</p>
                    <div class="empty-state-actions">
                        <a class="btn btn-primary" href="<?= e(url('alojamentos.php')) ?>">Limpar filtros</a>
                        <a class="btn btn-ghost" href="<?= e(url('index.php')) ?>">Voltar ao início</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="stay-grid stay-grid-list">
                    <?php foreach ($accommodations as $cardAccommodation): ?>
                        <?php
                        $cardCheckIn = $dateFilter ? $checkIn : '';
                        $cardCheckOut = $dateFilter ? $checkOut : '';
                        $cardGuests = $guests > 0 ? $guests : '';
                        include __DIR__ . '/includes/accommodation-card.php';
                        ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="Paginação de alojamentos">
                        <?php if ($page > 1): ?>
                            <a class="pagination-link pagination-arrow" href="<?= e(url('alojamentos.php') . '?' . current_query(['page' => $page - 1])) ?>" aria-label="Página anterior">
                                <?= icon('chevron-left') ?>
                            </a>
                        <?php endif; ?>

                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <?php if ($i === $page): ?>
                                <span class="pagination-link is-current" aria-current="page"><?= $i ?></span>
                            <?php else: ?>
                                <a class="pagination-link" href="<?= e(url('alojamentos.php') . '?' . current_query(['page' => $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a class="pagination-link pagination-arrow" href="<?= e(url('alojamentos.php') . '?' . current_query(['page' => $page + 1])) ?>" aria-label="Página seguinte">
                                <?= icon('chevron-right') ?>
                            </a>
                        <?php endif; ?>
                    </nav>
                    <p class="pagination-summary">Página <?= e($page) ?> de <?= e($totalPages) ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
