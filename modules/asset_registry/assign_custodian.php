<?php

session_start();

if (!isset($_GET['id'])) {
    header("Location:index.php");
    exit();
}

$id = (int)$_GET['id'];

if (!isset($_SESSION['assets'][$id])) {
    header("Location:index.php");
    exit();
}

$asset = $_SESSION['assets'][$id];

if (($asset['status'] ?? 'Available') === 'Under Maintenance') {
    header("Location:index.php?error=maintenance");
    exit();
}

include '../../includes/header.php';
include '../../includes/sidebar.php';

?>

<div class="main-content">

<h1>Assign Custodian</h1>

<hr><br>

<form
method="POST"
action="save_assignment.php?id=<?= $id ?>">

<label>Asset ID</label>

<input
type="text"
value="<?= htmlspecialchars($asset['asset_id']) ?>"
readonly>

<br><br>

<label>Asset Name</label>

<input
type="text"
value="<?= htmlspecialchars($asset['asset_name']) ?>"
readonly>

<br><br>

<label>Employee ID</label>

<input
type="text"
name="employee_id"
placeholder="Enter Employee ID"
required>

<br><br>

<label>Custodian Name</label>

<input
type="text"
name="custodian"
placeholder="Enter Custodian Name"
required>

<br><br>

<label>Department</label>

<select
name="department"
required>

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
required>

<br><br>

<button
class="btn btn-success"
type="submit">

Assign Asset

</button>

<a
href="index.php"
class="btn">

Cancel

</a>

</form>

</div>

<?php include '../../includes/footer.php'; ?>