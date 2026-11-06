<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = db();
$formErrors = [];

$formValues = [
    'name'            => '',
    'slug'            => '',
    'description'     => '',
    'location'        => '',
    'address'         => '',
    'type'            => 'apartamento',
    'price_per_night' => '',
    'capacity'        => '2',
    'bedrooms'        => '1',
    'bathrooms'       => '1',
    'amenities'       => '',
    'image_url'       => '',
    'rating'          => '4.8',
    'review_count'    => '0',
    'active'          => '1',
    'featured'        => '0',
];

if (is_post()) {
    verify_csrf('admin/criar-alojamento.php');

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
    $validation = validate_accommodation_form($input);
    $formErrors = $validation['errors'];

    if ($formErrors === []) {
        $data = $validation['data'];

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO accommodations
                    (name, slug, description, location, address, type, price_per_night, capacity, bedrooms, bathrooms,
                     amenities, image_url, rating, review_count, active, featured)
                 VALUES
                    (:name, :slug, :description, :location, :address, :type, :price_per_night, :capacity, :bedrooms, :bathrooms,
                     :amenities, :image_url, :rating, :review_count, :active, :featured)'
            );
            $stmt->execute($data);

            set_flash('success', 'Alojamento “' . $data['name'] . '” criado com sucesso.');
            redirect('admin/alojamentos.php');
        } catch (PDOException $exception) {
            error_log('[LumaStay] Erro ao criar alojamento: ' . $exception->getMessage());
            $formErrors[] = 'Não foi possível guardar o alojamento. Verifique os dados e tente novamente.';
        }
    }
}

$adminPage = 'alojamentos';
$pageTitle = 'Novo alojamento';
$bodyClass = 'admin-body admin-accommodation-form-page';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-page-intro">
    <div>
        <p class="admin-eyebrow">Catálogo · Novo registo</p>
        <h2>Criar alojamento</h2>
        <p>Preencha os dados que serão apresentados aos hóspedes. Pode guardar como inativo e publicar mais tarde.</p>
    </div>
    <div class="admin-intro-actions">
        <a class="btn btn-ghost btn-small" href="<?= e(url('admin/alojamentos.php')) ?>"><?= icon('arrow-left') ?> Voltar ao catálogo</a>
    </div>
</div>

<?php
$formAction = url('admin/criar-alojamento.php');
$cancelUrl = url('admin/alojamentos.php');
$submitLabel = 'Criar alojamento';
$isEdit = false;
$accommodationId = null;
include __DIR__ . '/../includes/admin-accommodation-form.php';
?>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
