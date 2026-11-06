<?php
/**
 * Card reutilizável de alojamento.
 * Espera: $cardAccommodation (array), opcionalmente $cardCheckIn, $cardCheckOut, $cardGuests.
 */
$cardAccommodation = $cardAccommodation ?? [];
$cardCheckIn = $cardCheckIn ?? '';
$cardCheckOut = $cardCheckOut ?? '';
$cardGuests = $cardGuests ?? '';

$cardParams = ['id' => (int) ($cardAccommodation['id'] ?? 0)];
if ($cardCheckIn !== '') {
    $cardParams['check_in'] = $cardCheckIn;
}
if ($cardCheckOut !== '') {
    $cardParams['check_out'] = $cardCheckOut;
}
if ($cardGuests !== '') {
    $cardParams['guests'] = $cardGuests;
}
$cardUrl = url('alojamento.php') . '?' . http_build_query($cardParams);
$cardAmenities = amenities_decode($cardAccommodation['amenities'] ?? '');
$cardImage = image_src($cardAccommodation['image_url'] ?? '');
$cardName = (string) ($cardAccommodation['name'] ?? 'Alojamento LumaStay');
?>
<article class="stay-card">
    <a class="stay-card-link" href="<?= e($cardUrl) ?>">
        <div class="stay-card-media">
            <img src="<?= e($cardImage) ?>"
                 alt="<?= e($cardName . ' em ' . ($cardAccommodation['location'] ?? 'Portugal')) ?>"
                 loading="lazy"
                 data-fallback="<?= e(asset('images/fallback.svg')) ?>">
            <span class="stay-card-type"><?= e(type_label((string) ($cardAccommodation['type'] ?? ''))) ?></span>
            <?= rating_badge((float) ($cardAccommodation['rating'] ?? 0), (int) ($cardAccommodation['review_count'] ?? 0)) ?>
        </div>
        <div class="stay-card-body">
            <p class="stay-card-location"><?= icon('pin') ?> <?= e($cardAccommodation['location'] ?? '') ?></p>
            <h3><?= e($cardName) ?></h3>
            <p class="stay-card-description"><?= e(excerpt((string) ($cardAccommodation['description'] ?? ''), 118)) ?></p>

            <?php if ($cardAmenities !== []): ?>
                <ul class="stay-card-amenities" aria-label="Comodidades principais">
                    <?php foreach (array_slice($cardAmenities, 0, 2) as $amenity): ?>
                        <li><?= icon(amenity_icon($amenity)) ?> <?= e($amenity) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="stay-card-meta">
                <span><?= icon('users') ?> <?= e($cardAccommodation['capacity'] ?? 0) ?> hóspedes</span>
                <span><?= icon('bed') ?> <?= e($cardAccommodation['bedrooms'] ?? 0) ?> quartos</span>
                <span><?= icon('bath') ?> <?= e($cardAccommodation['bathrooms'] ?? 0) ?> wc</span>
            </div>

            <div class="stay-card-footer">
                <p class="stay-card-price"><strong><?= e(money((float) ($cardAccommodation['price_per_night'] ?? 0))) ?></strong><span> / noite</span></p>
                <span class="stay-card-cta">Ver detalhes <?= icon('arrow-right') ?></span>
            </div>
        </div>
    </a>
</article>
