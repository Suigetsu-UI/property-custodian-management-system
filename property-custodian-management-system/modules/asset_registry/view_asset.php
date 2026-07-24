<?php

session_start();

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = (int) $_GET['id'];

if (!isset($_SESSION['assets'][$id])) {
    header("Location: index.php");
    exit();
}

$asset = $_SESSION['assets'][$id];

include '../../includes/header.php';
include '../../includes/sidebar.php';
?>

<div class="main-content">

<h1>View Asset</h1>

<hr><br>

<table class="asset-table">

<tr>
    <th>Asset ID</th>
    <td><?= htmlspecialchars($asset['asset_id']) ?></td>
</tr>

<tr>
    <th>Asset Name</th>
    <td><?= htmlspecialchars($asset['asset_name']) ?></td>
</tr>

<tr>
    <th>Category</th>
    <td><?= htmlspecialchars($asset['category']) ?></td>
</tr>

<tr>
    <th>Status</th>
    <td><?= htmlspecialchars($asset['status'] ?? 'Available') ?></td>
</tr>

<tr>
    <th>Brand</th>
    <td><?= htmlspecialchars($asset['brand']) ?></td>
</tr>

<tr>
    <th>Model</th>
    <td><?= htmlspecialchars($asset['model']) ?></td>
</tr>

<tr>
    <th>Serial Number</th>
    <td><?= htmlspecialchars($asset['serial_number']) ?></td>
</tr>

<tr>
    <th>Supplier</th>
    <td><?= htmlspecialchars($asset['supplier']) ?></td>
</tr>

<tr>
    <th>Location</th>
    <td><?= htmlspecialchars($asset['location']) ?></td>
</tr>

<tr>
    <th>Remarks</th>
    <td><?= htmlspecialchars($asset['remarks']) ?></td>
</tr>


<tr>
    <th>Employee ID</th>
    <td>
        <?= !empty($asset['employee_id'])
            ? htmlspecialchars($asset['employee_id'])
            : 'Not Assigned'; ?>
    </td>
</tr>

<tr>
    <th>Custodian</th>
    <td>
        <?= !empty($asset['custodian'])
            ? htmlspecialchars($asset['custodian'])
            : 'Not Assigned'; ?>
    </td>
</tr>

<tr>
    <th>Department</th>
    <td>
        <?= !empty($asset['department'])
            ? htmlspecialchars($asset['department'])
            : 'Not Assigned'; ?>
    </td>
</tr>

<tr>
    <th>Date Assigned</th>
    <td>
        <?= !empty($asset['date_assigned'])
            ? htmlspecialchars($asset['date_assigned'])
            : 'Not Assigned'; ?>
    </td>
</tr>


</table>

<br>

<a href="index.php" class="btn">
    ← Back to Asset Registry
</a>

</div>

<?php include '../../includes/footer.php'; ?>