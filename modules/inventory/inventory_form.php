<form method="POST" action="">

<h2>Add Inventory</h2>

<div class="form-row">

<label>Inventory ID</label>

<input
    type="text"
    name="inventory_id"
    value="<?= $inventory['inventory_id'] ?? 'INV-000001'; ?>"
    readonly
>

</div>

<div class="form-row">

<label>Asset Name</label>

<input
    type="text"
    name="asset_name"
    value="<?= htmlspecialchars($inventory['asset_name'] ?? '') ?>"
    required
>

</div>

<div class="form-row">

<label>Category</label>

<select name="category" required>

<option value="">Select Category</option>

<option <?= (($inventory['category'] ?? '') == 'Computer') ? 'selected' : '' ?>>
Computer
</option>

<option <?= (($inventory['category'] ?? '') == 'Furniture') ? 'selected' : '' ?>>
Furniture
</option>

<option <?= (($inventory['category'] ?? '') == 'Office Equipment') ? 'selected' : '' ?>>
Office Equipment
</option>

<option <?= (($inventory['category'] ?? '') == 'Electronics') ? 'selected' : '' ?>>
Electronics
</option>

</select>

</div>

<div class="form-row">

<label>Quantity</label>

<input
    type="number"
    name="quantity"
    min="1"
    value="<?= $inventory['quantity'] ?? 1; ?>"
    required
>

</div>

<div class="form-row">

<label>Condition</label>

<select name="condition">

<option <?= (($inventory['condition'] ?? '') == 'Good') ? 'selected' : '' ?>>
Good
</option>

<option <?= (($inventory['condition'] ?? '') == 'Fair') ? 'selected' : '' ?>>
Fair
</option>

<option <?= (($inventory['condition'] ?? '') == 'Needs Repair') ? 'selected' : '' ?>>
Needs Repair
</option>

<option <?= (($inventory['condition'] ?? '') == 'Unserviceable') ? 'selected' : '' ?>>
Unserviceable
</option>

</select>

</div>


<button type="submit" class="btn btn-primary">

<?= isset($inventory) ? "Update Inventory" : "Save Inventory"; ?>

</button>


</form>