<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/procurement_gateway.php';

try {
    $newProcurementId = getProcurementServiceClient()->nextBusinessId(
        currentProcurementActor()
    );
    $_SESSION['pending_procurement_ids'] ??= [];
    $_SESSION['pending_procurement_ids'][$newProcurementId] = true;
} catch (Throwable $error) {
    header('Location: index.php?error=service_unavailable');
    exit;
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="layout">
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main-content">
<h1>Add Procurement</h1>
<hr>
<?php include __DIR__ . '/procurement_form.php'; ?>
</div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
