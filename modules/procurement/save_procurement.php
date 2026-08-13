<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/asset_functions.php";

if (!isset($_SESSION['procurement'])) {
    $_SESSION['procurement'] = [];
}

$id = isset($_POST['id']) && $_POST['id'] !== ''
    ? (int) $_POST['id']
    : null;

$itemName = $_POST['item_name'];
$category = $_POST['category'];
$quantity = (int) $_POST['quantity'];
$status = $_POST['status'];

$existing = ($id !== null && isset($_SESSION['procurement'][$id]))
    ? $_SESSION['procurement'][$id]
    : null;

$previouslyDelivered = (int) ($existing['delivered_quantity'] ?? 0);

$record = [

    "procurement_id" => $existing['procurement_id'] ?? ($_POST["procurement_id"] ?? generateProcurementID()),

    "item_name" => $itemName,

    "category" => $category,

    "quantity" => $quantity,

    "supplier" => $_POST["supplier"],

    "requested_by" => $_POST["requested_by"] ?? ($existing['requested_by'] ?? ''),

    "request_date" => $_POST["request_date"] ?? ($existing['request_date'] ?? ''),

    "status" => $status,

    "approved_by" => $_POST["approved_by"] ?? ($existing['approved_by'] ?? ''),

    "approval_date" => $_POST["approval_date"] ?? ($existing['approval_date'] ?? ''),

    "remarks" => $_POST["remarks"] ?? ($existing['remarks'] ?? ''),

    "delivered_quantity" => $previouslyDelivered

];

// Only "Delivered" may ever touch Inventory. Pending, Approved, and
// Rejected never call adjustInventoryStock(). The delta below prevents
// double-counting when a Delivered record is later edited.
if ($status === 'Delivered') {

    $delta = $quantity - $previouslyDelivered;

    if ($delta !== 0) {
        adjustInventoryStock($itemName, $category, $delta);
    }

    $record['delivered_quantity'] = $quantity;

}

if ($id !== null && isset($_SESSION['procurement'][$id])) {

    $_SESSION['procurement'][$id] = $record;

} else {

    $_SESSION['procurement'][] = $record;

}

header("Location: index.php");
exit;