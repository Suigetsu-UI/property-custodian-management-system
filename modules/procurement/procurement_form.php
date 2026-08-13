<?php require_once __DIR__ . "/../../includes/asset_functions.php"; $default_procurement_id = generateProcurementID(); ?>
<form class="asset-form" method="POST" action="save_procurement.php">

    <h2><?= isset($procurement) ? "Edit Procurement" : "Add Procurement" ?></h2>

    <input type="hidden" name="id" value="<?= htmlspecialchars((string) ($id ?? '')) ?>">

    <div class="form-row">

        <label>Procurement ID</label>

        <input
            type="text"
            id="procurementID"
            name="procurement_id"
            value="<?= htmlspecialchars($procurement['procurement_id'] ?? $default_procurement_id) ?>"
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

            <option <?= (($procurement['category'] ?? '') == 'Computer') ? 'selected' : '' ?>>Computer</option>
            <option <?= (($procurement['category'] ?? '') == 'Furniture') ? 'selected' : '' ?>>Furniture</option>
            <option <?= (($procurement['category'] ?? '') == 'Office Equipment') ? 'selected' : '' ?>>Office Equipment</option>
            <option <?= (($procurement['category'] ?? '') == 'Electronics') ? 'selected' : '' ?>>Electronics</option>

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

        <label>Requested By</label>

        <input
            type="text"
            name="requested_by"
            value="<?= htmlspecialchars($procurement['requested_by'] ?? '') ?>"
            required>

    </div>

    <div class="form-row">

        <label>Request Date</label>

        <input
            type="date"
            name="request_date"
            value="<?= htmlspecialchars($procurement['request_date'] ?? '') ?>"
            required>

    </div>

    <div class="form-row">

        <label>Status</label>

        <select name="status">

            <option <?= (($procurement['status'] ?? '') == 'Pending') ? 'selected' : '' ?>>Pending</option>
            <option <?= (($procurement['status'] ?? '') == 'Approved') ? 'selected' : '' ?>>Approved</option>
            <option <?= (($procurement['status'] ?? '') == 'Rejected') ? 'selected' : '' ?>>Rejected</option>
            <option <?= (($procurement['status'] ?? '') == 'Delivered') ? 'selected' : '' ?>>Delivered</option>

        </select>

    </div>

    <div class="form-row">

        <label>Approved By</label>

        <input
            type="text"
            name="approved_by"
            value="<?= htmlspecialchars($procurement['approved_by'] ?? '') ?>">

    </div>

    <div class="form-row">

        <label>Approval Date</label>

        <input
            type="date"
            name="approval_date"
            value="<?= htmlspecialchars($procurement['approval_date'] ?? '') ?>">

    </div>

    <div class="form-row">

        <label>Remarks</label>

        <textarea name="remarks"><?= htmlspecialchars($procurement['remarks'] ?? '') ?></textarea>

    </div>

    <button type="submit" class="btn btn-primary">
        <?= isset($procurement) ? "Update Procurement" : "Save Procurement"; ?>
    </button>

</form>