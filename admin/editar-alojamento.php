<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();
$formErrors = [];
$accommodationId = is_post()
    ? (input_int('id', 'post', 0) ?? 0)
    : (input_int('id', 'get', 0) ?? 0);

$accommodation = find_accommodation($accommodationId, true);

if ($accommodation === false) {
    set_flash('error', 'O alojamento indicado não existe.');
    redirect('admin/alojamentos.php');
}

$formValues = [
    'name'            => (string) $accommodation['name'],
    'slug'            => (string) $accommodation['slug'],
    'description'     => (string) $accommodation['description'],
    'location'        => (string) $accommodation['location'],
    'address'         => (string) $accommodation['address'],
    'type'            => (string) $accommodation['type'],
    'price_per_night' => number_format((float) $accommodation['price_per_night'], 2, ',', ''),
    'capacity'        => (string) $accommodation['capacity'],
    'bedrooms'        => (string) $accommodation['bedrooms'],
    'bathrooms'       => (string) $accommodation['bathrooms'],
    'amenities'       => implode(', ', amenities_decode($accommodation['amenities'])),
    'image_url'       => (string) $accommodation['image_url'],
    'rating'          => number_format((float) $accommodation['rating'], 1, ',', ''),
    'review_count'    => (string) $accommodation['review_count'],
    'active'          => (string) $accommodation['active'],
    'featured'        => (string) $accommodation['featured'],
];

if (is_post()) {
    verify_csrf('admin/editar-alojamento.php?id=' . $accommodationId);

    $input = [
        'name'            => input_string('name'),
        'slug'            => input_string('slug'),
        'description'     => input_string('description'),
        'location'        => input_string('location'),
        'address'         => input_string('address'),
        'type'            => input_string('type'),
        'price_per_night' => input_string('price_per_night'),
        'capacity'        => input_string('capacity'),
        'bedrooms'        => input_string('bedrooms'),
        'bathrooms'       => input_string('bathrooms'),
        'amenities'       => input_string('amenities'),
        'image_url'       => input_string('image_url'),
        'rating'          => input_string('rating'),
        'review_count'    => input_string('review_count'),
        'active'          => isset($_POST['active']) ? '1' : '0',
        'featured'        => isset($_POST['featured']) ? '1' : '0',
    ];

    $formValues = $input;
    $validation = validate_accommodation_form($input, $accommodationId);
    $formErrors = $validation['errors'];

    if ($formErrors === []) {
        $data = $validation['data'];

        try {
            $stmt = $pdo->prepare(
                'UPDATE accommodations SET
                    name = :name,
                    slug = :slug,
                    description = :description,
                    location = :location,
                    address = :address,
                    type = :type,
                    price_per_night = :price_per_night,
                    capacity = :capacity,
                    bedrooms = :bedrooms,
                    bathrooms = :bathrooms,
                    amenities = :amenities,
                    image_url = :image_url,
                    rating = :rating,
                    review_count = :review_count,
                    active = :active,
                    featured = :featured
                 WHERE id = :id'
            );
            $data['id'] = $accommodationId;
            $stmt->execute($data);

            set_flash('success', 'Alojamento “' . $data['name'] . '” atualizado com sucesso.');
            redirect('admin/alojamentos.php');
        } catch (PDOException $exception) {
            error_log('[LumaStay] Erro ao atualizar alojamento: ' . $exception->getMessage());
            $formErrors[] = 'Não foi possível atualizar o alojamento. Verifique os dados e tente novamente.';
        }
    }
}

$adminPage = 'alojamentos';
$pageTitle = 'Editar alojamento';
$bodyClass = 'admin-body admin-accommodation-form-page';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-page-intro">
    <div>
        <p class="admin-eyebrow">Catálogo · Edição</p>
        <h2><?= e($accommodation['name']) ?></h2>
        <p>Atualize a informação pública e o estado do alojamento. As reservas existentes são preservadas.</p>
    </div>
    <div class="admin-intro-actions">
        <a class="btn btn-ghost btn-small" href="<?= e(url('alojamento.php') . '?id=' . $accommodationId) ?>" target="_blank" rel="noopener"><?= icon('eye') ?> Ver no site</a>
        <a class="btn btn-ghost btn-small" href="<?= e(url('admin/alojamentos.php')) ?>"><?= icon('arrow-left') ?> Voltar</a>
    </div>
</div>

<?php
$formAction = url('admin/editar-alojamento.php') . '?id=' . $accommodationId;
$cancelUrl = url('admin/alojamentos.php');
$submitLabel = 'Guardar alterações';
$isEdit = true;
include __DIR__ . '/../includes/admin-accommodation-form.php';
?>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
