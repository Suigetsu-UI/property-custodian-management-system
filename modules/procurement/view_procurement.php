<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/procurement_gateway.php';

$businessId = trim((string) ($_GET['id'] ?? ''));
$procurement = null;
$serviceUnavailable = false;

if (preg_match('/^PRC-\d{6}$/', $businessId) === 1) {
    try {
        $procurement = getProcurementServiceClient()->find($businessId);
    } catch (ProcurementServiceUnavailableException $error) {
        $serviceUnavailable = true;
    } catch (ProcurementServiceException $error) {
        $procurement = null;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="layout">
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>
<div class="main-content">
<h1>View Procurement</h1>
<hr>

<?php if ($serviceUnavailable): ?>
<div class="error-message" role="alert">Procurement service is temporarily unavailable.</div>
<?php elseif ($procurement): ?>
<table class="asset-table">
<tr><th style="width:220px;">Procurement ID</th><td><?= htmlspecialchars($procurement['procurement_id']) ?></td></tr>
<tr><th>Item Name</th><td><?= htmlspecialchars($procurement['item_name']) ?></td></tr>
<tr><th>Category</th><td><?= htmlspecialchars($procurement['category']) ?></td></tr>
<tr><th>Quantity</th><td><?= (int) $procurement['quantity'] ?></td></tr>
<tr><th>Supplier</th><td><?= htmlspecialchars($procurement['supplier']) ?></td></tr>
<tr><th>Requested By</th><td><?= htmlspecialchars($procurement['requested_by'] ?? '') ?></td></tr>
<tr><th>Request Date</th><td><?= htmlspecialchars($procurement['request_date'] ?? '') ?></td></tr>
<tr><th>Status</th><td><?= htmlspecialchars($procurement['status']) ?></td></tr>
<tr><th>Approved By</th><td><?= !empty($procurement['approved_by']) ? htmlspecialchars($procurement['approved_by']) : 'Not Yet Approved' ?></td></tr>
<tr><th>Approval Date</th><td><?= !empty($procurement['approval_date']) ? htmlspecialchars($procurement['approval_date']) : 'Not Yet Approved' ?></td></tr>
<tr><th>Delivery Date</th><td><?= !empty($procurement['delivery_date']) ? htmlspecialchars($procurement['delivery_date']) : 'Not Yet Delivered' ?></td></tr>
<tr><th>Delivered Quantity</th><td><?= (int) ($procurement['delivered_quantity'] ?? 0) ?></td></tr>
<tr><th>Remarks</th><td><?= htmlspecialchars($procurement['remarks'] ?? '') ?></td></tr>
</table>
<?php else: ?>
<p>Procurement record not found.</p>
<?php endif; ?>

<br>
<a href="index.php" class="btn btn-primary">Back to Procurement</a>
</div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
