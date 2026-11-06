<?php
/**
 * Formulário partilhado de criação/edição de alojamento.
 * Espera: $formValues, $formErrors, $formAction, $cancelUrl, $submitLabel, $isEdit, $accommodationId.
 */
$formValues = $formValues ?? [];
$formErrors = $formErrors ?? [];
$isEdit = $isEdit ?? false;
$accommodationId = $accommodationId ?? null;
$submitLabel = $submitLabel ?? 'Guardar alojamento';
$previewSource = image_src((string) ($formValues['image_url'] ?? ''));
?>
<?= render_errors($formErrors) ?>

<form class="admin-form" method="post" action="<?= e($formAction) ?>" data-admin-form>
    <?= csrf_field() ?>
    <?php if ($isEdit && $accommodationId !== null): ?>
        <input type="hidden" name="id" value="<?= (int) $accommodationId ?>">
    <?php endif; ?>

    <div class="admin-form-layout">
        <div class="admin-form-main">
            <section class="admin-card form-card">
                <div class="admin-card-head">
                    <div>
                        <h2>Informação principal</h2>
                        <p>Nome, descrição e localização apresentados no site.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-field">
                        <label for="acc-name">Nome do alojamento *</label>
                        <input type="text" id="acc-name" name="name" value="<?= e($formValues['name'] ?? '') ?>" maxlength="150" required>
                    </div>
                    <div class="form-field">
                        <label for="acc-slug">Slug</label>
                        <input type="text" id="acc-slug" name="slug" value="<?= e($formValues['slug'] ?? '') ?>" maxlength="170" placeholder="gerado-automaticamente">
                        <small class="field-help">Identificador amigável para URLs. Se ficar vazio, é gerado a partir do nome.</small>
                    </div>
                    <div class="form-field form-field-full">
                        <label for="acc-description">Descrição *</label>
                        <textarea id="acc-description" name="description" rows="6" maxlength="5000" required><?= e($formValues['description'] ?? '') ?></textarea>
                        <small class="field-help">Entre 30 e 5000 caracteres. Descreva a casa e a experiência, não apenas a localização.</small>
                    </div>
                    <div class="form-field">
                        <label for="acc-location">Localização *</label>
                        <input type="text" id="acc-location" name="location" value="<?= e($formValues['location'] ?? '') ?>" maxlength="120" placeholder="Ex.: Lisboa" required>
                    </div>
                    <div class="form-field">
                        <label for="acc-type">Tipo *</label>
                        <select id="acc-type" name="type" required>
                            <?php foreach (accommodation_types() as $typeKey => $typeLabel): ?>
                                <option value="<?= e($typeKey) ?>"<?= selected($formValues['type'] ?? '', $typeKey) ?>><?= e($typeLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field form-field-full">
                        <label for="acc-address">Morada *</label>
                        <input type="text" id="acc-address" name="address" value="<?= e($formValues['address'] ?? '') ?>" maxlength="255" placeholder="Rua, número, código postal e localidade" required>
                        <small class="field-help">A morada exata é enviada ao hóspede após a confirmação da reserva.</small>
                    </div>
                </div>
            </section>

            <section class="admin-card form-card">
                <div class="admin-card-head">
                    <div>
                        <h2>Preço e capacidade</h2>
                        <p>Valores usados no cálculo das reservas e nos filtros de pesquisa.</p>
                    </div>
                </div>

                <div class="form-grid form-grid-three">
                    <div class="form-field">
                        <label for="acc-price">Preço por noite (€) *</label>
                        <input type="text" inputmode="decimal" id="acc-price" name="price_per_night" value="<?= e($formValues['price_per_night'] ?? '') ?>" placeholder="185,00" required>
                    </div>
                    <div class="form-field">
                        <label for="acc-capacity">Capacidade *</label>
                        <input type="number" id="acc-capacity" name="capacity" value="<?= e($formValues['capacity'] ?? '2') ?>" min="1" max="30" required>
                        <small class="field-help">Máx. 30 hóspedes.</small>
                    </div>
                    <div class="form-field">
                        <label for="acc-bedrooms">Quartos *</label>
                        <input type="number" id="acc-bedrooms" name="bedrooms" value="<?= e($formValues['bedrooms'] ?? '1') ?>" min="0" max="20" required>
                    </div>
                    <div class="form-field">
                        <label for="acc-bathrooms">Casas de banho *</label>
                        <input type="number" id="acc-bathrooms" name="bathrooms" value="<?= e($formValues['bathrooms'] ?? '1') ?>" min="1" max="20" required>
                    </div>
                    <div class="form-field">
                        <label for="acc-rating">Avaliação (0–5)</label>
                        <input type="text" inputmode="decimal" id="acc-rating" name="rating" value="<?= e($formValues['rating'] ?? '4.8') ?>">
                    </div>
                    <div class="form-field">
                        <label for="acc-reviews">N.º de avaliações</label>
                        <input type="number" id="acc-reviews" name="review_count" value="<?= e($formValues['review_count'] ?? '0') ?>" min="0" max="100000">
                    </div>
                </div>
            </section>

            <section class="admin-card form-card">
                <div class="admin-card-head">
                    <div>
                        <h2>Comodidades e imagem</h2>
                        <p>Estes elementos ajudam o hóspede a perceber a experiência.</p>
                    </div>
                </div>

                <div class="form-field">
                    <label for="acc-amenities">Comodidades *</label>
                    <textarea id="acc-amenities" name="amenities" rows="3" placeholder="Wi-Fi, Piscina privada, Vista para o mar" required><?= e($formValues['amenities'] ?? '') ?></textarea>
                    <small class="field-help">Separe por vírgulas. Entre 1 e 20 comodidades. Ícones são associados automaticamente.</small>
                </div>

                <div class="form-field">
                    <label for="acc-image">Imagem (caminho ou URL) *</label>
                    <input type="text" id="acc-image" name="image_url" value="<?= e($formValues['image_url'] ?? '') ?>" maxlength="255" placeholder="assets/images/alojamentos/lisboa.svg" data-image-input required>
                    <small class="field-help">Caminhos locais começam por <code>assets/</code>. Também são aceites URLs http(s).</small>
                </div>
            </section>
        </div>

        <aside class="admin-form-side">
            <section class="admin-card form-card image-preview-card">
                <div class="admin-card-head">
                    <div>
                        <h2>Pré-visualização</h2>
                        <p>Imagem principal do card.</p>
                    </div>
                </div>
                <img class="image-preview" src="<?= e($previewSource) ?>" alt="Pré-visualização da imagem do alojamento" data-image-preview data-fallback="<?= e(asset('images/fallback.svg')) ?>">
                <p class="image-preview-note"><?= icon('info') ?> Se a imagem falhar, o site usa automaticamente uma imagem de reserva.</p>
            </section>

            <section class="admin-card form-card">
                <div class="admin-card-head">
                    <div>
                        <h2>Publicação</h2>
                        <p>Controle a visibilidade no site.</p>
                    </div>
                </div>

                <label class="toggle-field">
                    <input type="checkbox" name="active" value="1"<?= checked((string) ($formValues['active'] ?? '1') === '1') ?>>
                    <span>
                        <strong>Alojamento ativo</strong>
                        <small>Visível na pesquisa e na página inicial quando em destaque.</small>
                    </span>
                </label>

                <label class="toggle-field">
                    <input type="checkbox" name="featured" value="1"<?= checked((string) ($formValues['featured'] ?? '0') === '1') ?>>
                    <span>
                        <strong>Mostrar em destaque</strong>
                        <small>Aparece na seleção da página inicial. Requer alojamento ativo.</small>
                    </span>
                </label>

                <div class="form-actions-stacked">
                    <button class="btn btn-primary btn-block" type="submit"><?= icon('check') ?> <?= e($submitLabel) ?></button>
                    <a class="btn btn-ghost btn-block" href="<?= e($cancelUrl) ?>">Cancelar</a>
                </div>
            </section>

            <?php if ($isEdit): ?>
                <section class="admin-card form-card meta-card">
                    <h2>Informação do registo</h2>
                    <p>ID #<?= e((int) $accommodationId) ?></p>
                    <p>As alterações ficam disponíveis imediatamente no site.</p>
                </section>
            <?php endif; ?>
        </aside>
    </div>
</form>
