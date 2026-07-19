<form
class="asset-form"
method="POST"
action="<?= isset($procurement)
    ? 'edit_procurement.php?id=' . $id
    : 'save_procurement.php'; ?>">

    <div class="form-row">

        <label>Procurement ID</label>

        <input
            type="text"
            name="procurement_id"
            value="<?= $procurement['procurement_id'] ?? 'PRC-000001'; ?>"
            readonly>

    </div>

    <div class="form-row">

        <label>Item Name</label>

        <input
    type="text"
    name="item_name"
    value="<?= htmlspecialchars($procurement['item_name'] ?? '') ?>"
    required>

    </div>

    <div class="form-row">

        <label>Category</label>

        <select name="category">

<option <?= (($procurement['category'] ?? '') == 'Computer') ? 'selected' : '' ?>>
Computer
</option>

<option <?= (($procurement['category'] ?? '') == 'Furniture') ? 'selected' : '' ?>>
Furniture
</option>

<option <?= (($procurement['category'] ?? '') == 'Office Equipment') ? 'selected' : '' ?>>
Office Equipment
</option>

<option <?= (($procurement['category'] ?? '') == 'Electronics') ? 'selected' : '' ?>>
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
    value="<?= $procurement['quantity'] ?? 1 ?>"
    required>

    </div>

    <div class="form-row">

        <label>Supplier</label>

        <input
    type="text"
    name="supplier"
    value="<?= htmlspecialchars($procurement['supplier'] ?? '') ?>"
    required>

    </div>

    <div class="form-row">

        <label>Status</label>

        <select name="status">

<option <?= (($procurement['status'] ?? '') == 'Pending') ? 'selected' : '' ?>>
Pending
</option>

<option <?= (($procurement['status'] ?? '') == 'Approved') ? 'selected' : '' ?>>
Approved
</option>

<option <?= (($procurement['status'] ?? '') == 'Ordered') ? 'selected' : '' ?>>
Ordered
</option>

<option <?= (($procurement['status'] ?? '') == 'Delivered') ? 'selected' : '' ?>>
Delivered
</option>

</select>

    </div>

    <button
        type="submit"
        class="btn btn-primary">

        <?= isset($procurement) ? "Update Procurement" : "Save Procurement"; ?>

    </button>

</form>