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

<h1>Edit Asset</h1>

<hr><br>

<form
method="POST"
action="update_asset.php?id=<?= $id ?>"
class="asset-form">

<div class="form-row">

<label>Asset ID</label>

<input
type="text"
value="<?= htmlspecialchars($asset['asset_id']) ?>"
readonly>

</div>

<div class="form-row">

<label>Asset Name</label>

<input
type="text"
name="asset_name"
value="<?= htmlspecialchars($asset['asset_name']) ?>"
required>

</div>

<div class="form-row">

<label>Category</label>

<select name="category">

<?php

$categories=[
"Computer",
"Furniture",
"Laboratory Equipment",
"Office Equipment"
];

foreach($categories as $category){

$selected =
($asset['category']==$category)
? "selected"
: "";

echo "<option $selected>$category</option>";

}

?>

</select>

</div>

<div class="form-row">

<label>Brand</label>

<input
type="text"
name="brand"
value="<?= htmlspecialchars($asset['brand']) ?>">

</div>

<div class="form-row">

<label>Model</label>

<input
type="text"
name="model"
value="<?= htmlspecialchars($asset['model']) ?>">

</div>

<div class="form-row">

<label>Serial Number</label>

<input
type="text"
name="serial_number"
value="<?= htmlspecialchars($asset['serial_number']) ?>">

</div>

<div class="form-row">

<label>Supplier</label>

<input
type="text"
name="supplier"
value="<?= htmlspecialchars($asset['supplier']) ?>">

</div>

<div class="form-row">

<label>Location</label>

<input
type="text"
name="location"
value="<?= htmlspecialchars($asset['location']) ?>">

</div>

<div class="form-row">

<label>Remarks</label>

<textarea
name="remarks"><?= htmlspecialchars($asset['remarks']) ?></textarea>

</div>

<br>

<button
class="btn"
type="submit">

Update Asset

</button>

<a
href="index.php"
class="btn">

Cancel

</a>

</form>

</div>

<?php include '../../includes/footer.php'; ?>