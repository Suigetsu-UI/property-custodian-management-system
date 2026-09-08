<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

requireAdministrator();
requireValidAccessCsrfPost();

$category = trim((string) ($_POST['category'] ?? ''));
$years = filter_var($_POST['years'] ?? null, FILTER_VALIDATE_INT);
$months = filter_var($_POST['months'] ?? null, FILTER_VALIDATE_INT);
$remarks = trim((string) ($_POST['remarks'] ?? ''));

if (
    $category === '' ||
    strlen($category) > 100 ||
    $years === false ||
    $months === false ||
    $years < 0 ||
    $years > 100 ||
    $months < 0 ||
    $months > 11 ||
    (($years * 12) + $months) < 1 ||
    (($years * 12) + $months) > 1200 ||
    strlen($remarks) > 1000
) {
    header('Location: index.php?message=invalid_category');
    exit;
}

try {
    getPropertyCoreServiceClient()->saveCategoryUsefulLife([
        'category' => $category,
        'useful_life_months' => ($years * 12) + $months,
        'remarks' => $remarks,
    ], currentPropertyCoreActor());
    header('Location: index.php?message=category_saved');
} catch (Throwable $error) {
    header('Location: index.php?message=save_failed');
}
exit;
