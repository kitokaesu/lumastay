<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$pdo = db();

$featured = $pdo->query(
    "SELECT * FROM accommodations
     WHERE active = 1
     ORDER BY featured DESC, rating DESC, review_count DESC
     LIMIT 3"
)->fetchAll();

if ($featured === []) {
    $featured = $pdo->query("SELECT * FROM accommodations WHERE active = 1 ORDER BY rating DESC LIMIT 3")->fetchAll();
}

$destinations = $pdo->query(
    "SELECT location,
            COUNT(*) AS total,
            MIN(price_per_night) AS min_price,
            SUBSTRING_INDEX(GROUP_CONCAT(image_url ORDER BY featured DESC, rating DESC SEPARATOR '|'), '|', 1) AS image_url
     FROM accommodations
     WHERE active = 1
     GROUP BY location
     ORDER BY total DESC, location ASC
     LIMIT 6"
)->fetchAll();

$stats = $pdo->query(
    "SELECT COUNT(*) AS total,
            COUNT(DISTINCT location) AS locations,
            COALESCE(AVG(rating), 0) AS avg_rating,
            COALESCE(SUM(review_count), 0) AS reviews
     FROM accommodations
     WHERE active = 1"
)->fetch();

$locations = $pdo->query(
    "SELECT DISTINCT location FROM accommodations WHERE active = 1 ORDER BY location ASC"
)->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Alojamentos extraordinários em Portugal';
$pageDescription = 'Descubra alojamentos com curadoria em Lisboa, Porto, Sintra, Algarve, Douro, Madeira e muito mais. Reserve diretamente com a LumaStay.';
$currentPage = 'home';
$bodyClass = 'page-home';

include __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <div class="hero-media">
        <img src="<?= e(asset('images/hero.svg')) ?>" alt="Ilustração de uma casa portuguesa entre colinas, ao entardecer" width="1600" height="1000">
        <div class="hero-overlay"></div>
    </div>

    <div class="container hero-content">
        <p class="eyebrow eyebrow-light"><span class="eyebrow-line"></span> Curadoria de alojamentos em Portugal</p>
        <h1>Fique em lugares<br><em>extraordinários.</em></h1>
        <p class="hero-lead">Casas com alma, vistas que ficam e detalhes pensados para si. A LumaStay reúne alojamentos escolhidos um a um, em alguns dos lugares mais bonitos do país.</p>
        <div class="hero-actions">
            <a class="btn btn-light" href="<?= e(url('alojamentos.php')) ?>">Explorar alojamentos <?= icon('arrow-right') ?></a>
            <a class="hero-secondary-link" href="#sobre">Conhecer a curadoria</a>
        </div>

        <dl class="hero-stats">
            <div>
                <dt><?= e((int) $stats['total']) ?></dt>
                <dd>alojamentos selecionados</dd>
            </div>
            <div>
                <dt><?= e((int) $stats['locations']) ?></dt>
                <dd>destinos em Portugal</dd>
            </div>
            <div>
                <dt><?= e(number_format((float) $stats['avg_rating'], 1, ',', '')) ?></dt>
                <dd>avaliação média</dd>
            </div>
        </dl>
    </div>

    <div class="container search-panel-wrap">
        <form class="search-panel" action="<?= e(url('alojamentos.php')) ?>" method="get" role="search" aria-label="Pesquisar alojamentos">
            <div class="search-field search-field-destination">
                <label for="hero-destination"><?= icon('pin') ?> Destino</label>
                <select id="hero-destination" name="destination">
                    <option value="">Todos os destinos</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= e($location) ?>"><?= e($location) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="search-field">
                <label for="hero-check-in"><?= icon('calendar') ?> Entrada</label>
                <input type="date" id="hero-check-in" name="check_in" min="<?= e(today()) ?>" data-check-in>
            </div>
            <div class="search-field">
                <label for="hero-check-out"><?= icon('calendar') ?> Saída</label>
                <input type="date" id="hero-check-out" name="check_out" min="<?= e(date('Y-m-d', strtotime('+1 day'))) ?>" data-check-out>
            </div>
            <div class="search-field">
                <label for="hero-guests"><?= icon('users') ?> Hóspedes</label>
                <select id="hero-guests" name="guests">
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <option value="<?= $i ?>"<?= $i === 2 ? ' selected' : '' ?>><?= $i ?> <?= $i === 1 ? 'hóspede' : 'hóspedes' ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <button class="btn btn-primary search-submit" type="submit"><?= icon('search') ?> Pesquisar</button>
        </form>
    </div>
</section>

<section class="section section-featured">
    <div class="container">
        <div class="section-heading">
            <div>
                <p class="eyebrow"><span class="eyebrow-line"></span> Seleção da casa</p>
                <h2>Alojamentos em destaque</h2>
                <p>Uma pequena seleção do que há de mais especial na LumaStay.</p>
            </div>
            <a class="text-link" href="<?= e(url('alojamentos.php')) ?>">Ver todos os alojamentos <?= icon('arrow-right') ?></a>
        </div>

        <?php if ($featured === []): ?>
            <div class="empty-state">
                <?= icon('home') ?>
                <h3>Ainda não existem alojamentos publicados</h3>
                <p>Assim que a equipa publicar alojamentos, eles aparecerão aqui.</p>
            </div>
        <?php else: ?>
            <div class="stay-grid">
                <?php foreach ($featured as $cardAccommodation): ?>
                    <?php include __DIR__ . '/includes/accommodation-card.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-destinations" id="destinos">
    <div class="container">
        <div class="section-heading">
            <div>
                <p class="eyebrow"><span class="eyebrow-line"></span> Onde ficar</p>
                <h2>Destinos para descobrir</h2>
                <p>Do Atlântico ao Douro, lugares que merecem mais do que uma passagem.</p>
            </div>
        </div>

        <div class="destination-grid">
            <?php foreach ($destinations as $index => $destination): ?>
                <a class="destination-card<?= $index === 0 ? ' destination-card-large' : '' ?>" href="<?= e(url('alojamentos.php') . '?destination=' . urlencode((string) $destination['location'])) ?>">
                    <img src="<?= e(image_src((string) $destination['image_url'])) ?>"
                         alt="Alojamento em <?= e($destination['location']) ?>"
                         loading="lazy"
                         data-fallback="<?= e(asset('images/fallback.svg')) ?>">
                    <div class="destination-overlay"></div>
                    <div class="destination-content">
                        <span><?= e((int) $destination['total']) ?> <?= (int) $destination['total'] === 1 ? 'alojamento' : 'alojamentos' ?></span>
                        <h3><?= e($destination['location']) ?></h3>
                        <p>A partir de <?= e(money((float) $destination['min_price'])) ?> / noite</p>
                    </div>
                    <span class="destination-arrow"><?= icon('arrow-right') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-about" id="sobre">
    <div class="container about-grid">
        <div class="about-visual">
            <?php if (isset($featured[0])): ?>
                <img class="about-image about-image-main"
                     src="<?= e(image_src((string) $featured[0]['image_url'])) ?>"
                     alt="<?= e((string) $featured[0]['name']) ?>"
                     loading="lazy"
                     data-fallback="<?= e(asset('images/fallback.svg')) ?>">
            <?php endif; ?>
            <?php if (isset($featured[1])): ?>
                <img class="about-image about-image-small"
                     src="<?= e(image_src((string) $featured[1]['image_url'])) ?>"
                     alt="<?= e((string) $featured[1]['name']) ?>"
                     loading="lazy"
                     data-fallback="<?= e(asset('images/fallback.svg')) ?>">
            <?php endif; ?>
            <div class="about-stamp" aria-hidden="true">
                <span>LS</span>
                <small>Curadoria<br>desde 2019</small>
            </div>
        </div>

        <div class="about-copy">
            <p class="eyebrow"><span class="eyebrow-line"></span> A nossa forma de escolher</p>
            <h2>Menos opções.<br>Muito mais certeza.</h2>
            <p class="about-lead">Não listamos tudo. Escolhemos pouco, com tempo e critério, para que cada alojamento tenha uma razão clara para estar aqui.</p>
            <p>Visitamos, fotografamos e ouvimos quem lá vive. Avaliamos a luz, o silêncio, o conforto da cama e a forma como a casa nos recebe. Só depois entra no catálogo LumaStay.</p>

            <ol class="principles">
                <li>
                    <span class="principle-number">01</span>
                    <div>
                        <h3>Seleção criteriosa</h3>
                        <p>Cada casa é avaliada pela localização, carácter e qualidade da estadia.</p>
                    </div>
                </li>
                <li>
                    <span class="principle-number">02</span>
                    <div>
                        <h3>Reserva sem ruído</h3>
                        <p>Preços claros, disponibilidade em tempo real e confirmação direta.</p>
                    </div>
                </li>
                <li>
                    <span class="principle-number">03</span>
                    <div>
                        <h3>Atenção ao detalhe</h3>
                        <p>Do primeiro email ao último dia, cuidamos dos pormenores que contam.</p>
                    </div>
                </li>
            </ol>
        </div>
    </div>
</section>

<section class="section section-advantages" id="vantagens">
    <div class="container">
        <div class="section-heading section-heading-centered">
            <div>
                <p class="eyebrow"><span class="eyebrow-line"></span> A diferença LumaStay</p>
                <h2>Uma estadia pensada de início ao fim</h2>
                <p>O essencial, sem complicações. É assim que acreditamos que uma boa viagem deve começar.</p>
            </div>
        </div>

        <div class="advantage-grid">
            <article class="advantage-card">
                <span class="advantage-icon"><?= icon('shield') ?></span>
                <h3>Confiança desde o primeiro clique</h3>
                <p>Alojamentos verificados, informação transparente e reservas tratadas com o cuidado que merece.</p>
            </article>
            <article class="advantage-card">
                <span class="advantage-icon"><?= icon('key') ?></span>
                <h3>Reserva direta e simples</h3>
                <p>Escolha as datas, confirme os hóspedes e receba o código da reserva de imediato.</p>
            </article>
            <article class="advantage-card">
                <span class="advantage-icon"><?= icon('sparkles') ?></span>
                <h3>Detalhes que fazem a diferença</h3>
                <p>Casas com identidade, comodidades bem descritas e sugestões locais para aproveitar o destino.</p>
            </article>
            <article class="advantage-card">
                <span class="advantage-icon"><?= icon('coffee') ?></span>
                <h3>Apoio próximo</h3>
                <p>Uma equipa portuguesa disponível para acompanhar a sua reserva antes, durante e depois da estadia.</p>
            </article>
        </div>
    </div>
</section>

<section class="cta-band">
    <div class="container cta-band-inner">
        <div>
            <p class="eyebrow eyebrow-gold"><span class="eyebrow-line"></span> O próximo capítulo</p>
            <h2>A sua próxima estadia começa com uma escolha.</h2>
        </div>
        <a class="btn btn-light" href="<?= e(url('alojamentos.php')) ?>">Encontrar o meu alojamento <?= icon('arrow-right') ?></a>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
