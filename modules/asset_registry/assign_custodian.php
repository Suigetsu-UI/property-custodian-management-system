<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";

$id = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

if ($id === false) {
    header("Location: index.php");
    exit;
}

$pdo = getDbConnection();

$stmt = $pdo->prepare(
    "SELECT *
     FROM assets
     WHERE id = :id"
);

$stmt->execute([
    'id' => $id
]);

$asset = $stmt->fetch();

if (!$asset) {
    header("Location: index.php");
    exit;
}

$maintenanceStmt = $pdo->prepare(
    "SELECT 1
     FROM maintenance
     WHERE asset_id = :asset_id
       AND status <> 'Completed'
     LIMIT 1"
);

$maintenanceStmt->execute([
    'asset_id' => $id
]);

if (
    $asset['status'] === 'Under Maintenance' ||
    $maintenanceStmt->fetch()
) {
    header("Location: index.php?error=maintenance");
    exit;
}

if ($asset['status'] === 'Lost') {
    header("Location: index.php?error=lost");
    exit;
}

/*
 * Assignment is Available -> Assigned.
 */
if ($asset['status'] !== 'Available') {
    header("Location: index.php");
    exit;
}

include "../../includes/header.php";
include "../../includes/sidebar.php";

?>

<div class="main-content">

<h1>Assign Custodian</h1>

<hr><br>

<form
    method="POST"
    action="save_assignment.php?id=<?= (int) $id ?>"
>

<label>Asset ID</label>

<input
    type="text"
    value="<?= htmlspecialchars($asset['asset_id']) ?>"
    readonly
>

<br><br>

<label>Asset Name</label>

<input
    type="text"
    value="<?= htmlspecialchars($asset['asset_name']) ?>"
    readonly
>

<br><br>

<label>Employee ID</label>

<input
    type="text"
    name="employee_id"
    placeholder="Enter Employee ID"
    required
>

<br><br>

<label>Custodian Name</label>

<input
    type="text"
    name="custodian"
    placeholder="Enter Custodian Name"
    required
>

<br><br>

<label>Department</label>

<select
    name="department"
    required
>

<option>ICT Office</option>
<option>Registrar</option>
<option>Accounting</option>
<option>Library</option>
<option>Guidance Office</option>

</select>

<br><br>

<label>Date Assigned</label>

<input
    type="date"
    name="date_assigned"
    required
>

<br><br>

<button
    class="btn btn-success"
    type="submit"
>
    Assign Asset
</button>

<a
    href="index.php"
    class="btn"
>
    Cancel
</a>

</form>

</div>

<?php include "../../includes/footer.php"; ?>