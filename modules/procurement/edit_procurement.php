<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/procurement_gateway.php';

$id = trim((string) ($_GET['id'] ?? ''));
$procurement = null;

if (preg_match('/^PRC-\d{6}$/', $id) === 1) {
    try {
        $procurement = getProcurementServiceClient()->find($id);
    } catch (ProcurementServiceUnavailableException $error) {
        header('Location: index.php?error=service_unavailable');
        exit;
    } catch (ProcurementServiceException $error) {
        $procurement = null;
    }
}

if (!$procurement) {
    header('Location: index.php');
    exit;
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="layout">
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main-content">
<h1>Edit Procurement</h1>
<hr>
<?php include __DIR__ . '/procurement_form.php'; ?>
</div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
