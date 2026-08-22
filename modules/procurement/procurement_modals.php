<?php
$procurementCategories = ['Computer', 'Furniture', 'Office Equipment', 'Electronics'];
$procurementStatuses = ['Pending', 'Approved', 'Rejected', 'Delivered'];
?>

<div id="procurementModal" class="modal pcms-modal pcms-modal--lg" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="addProcurementTitle">
<div class="modal-content pcms-modal-dialog">
<form id="addProcurementForm" class="pcms-modal-form" method="POST" action="save_procurement.php">
<input type="hidden" name="id" value="">
<header class="pcms-modal-header"><div><span class="pcms-modal-eyebrow">Procurement</span><h2 id="addProcurementTitle">Add Procurement</h2><p>Create a request using the existing approval and Inventory delivery workflow.</p></div><button type="button" class="close-modal" data-modal-close aria-label="Close Add Procurement"><span aria-hidden="true">&times;</span></button></header>
<div class="pcms-modal-body"><div class="pcms-form-grid">
    <div class="form-row"><label for="procurementID">Procurement ID</label><input type="text" id="procurementID" name="procurement_id" readonly></div>
    <div class="form-row"><label for="procurementItemName">Item Name</label><input type="text" id="procurementItemName" name="item_name" required></div>
    <div class="form-row"><label for="procurementCategory">Category</label><select id="procurementCategory" name="category"><?php foreach ($procurementCategories as $value): ?><option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($value) ?></option><?php endforeach; ?></select></div>
    <div class="form-row"><label for="procurementQuantity">Quantity</label><input type="number" id="procurementQuantity" name="quantity" min="1" value="1" required></div>
    <div class="form-row"><label for="procurementSupplier">Supplier</label><input type="text" id="procurementSupplier" name="supplier" required></div>
    <div class="form-row"><label for="procurementRequestedBy">Requested By</label><input type="text" id="procurementRequestedBy" name="requested_by" required></div>
    <div class="form-row"><label for="procurementRequestDate">Request Date</label><input type="date" id="procurementRequestDate" name="request_date" required></div>
    <div class="form-row"><label for="procurementStatus">Status</label><select id="procurementStatus" name="status"><?php foreach ($procurementStatuses as $value): ?><option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($value) ?></option><?php endforeach; ?></select></div>
    <div class="form-row"><label for="procurementApprovedBy">Approved By</label><input type="text" id="procurementApprovedBy" name="approved_by"></div>
    <div class="form-row"><label for="procurementApprovalDate">Approval Date</label><input type="date" id="procurementApprovalDate" name="approval_date"></div>
    <div class="form-row"><label for="procurementDeliveryDate">Delivery Date</label><input type="date" id="procurementDeliveryDate" name="delivery_date"></div>
    <div class="form-row pcms-form-span-2"><label for="procurementRemarks">Remarks</label><textarea id="procurementRemarks" name="remarks" rows="3"></textarea></div>
</div></div>
<footer class="pcms-modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-primary">Save Procurement</button></footer>
</form></div></div>

<div id="viewProcurementModal" class="modal pcms-modal pcms-modal--lg" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="viewProcurementTitle">
<div class="modal-content pcms-modal-dialog">
<header class="pcms-modal-header"><div><span class="pcms-modal-eyebrow">Procurement</span><div class="pcms-modal-title-row"><h2 id="viewProcurementTitle">Procurement Details</h2><span id="viewProcurementStatus" class="pcms-status-badge">Pending</span></div><p id="viewProcurementSubtitle">Request information</p></div><button type="button" class="close-modal" data-modal-close aria-label="Close Procurement Details"><span aria-hidden="true">&times;</span></button></header>
<div class="pcms-modal-body">
<section class="pcms-detail-section"><h3>Request Information</h3><dl class="pcms-detail-grid">
    <div><dt>Procurement ID</dt><dd data-procurement-view="procurement_id">—</dd></div><div><dt>Item Name</dt><dd data-procurement-view="item_name">—</dd></div>
    <div><dt>Category</dt><dd data-procurement-view="category">—</dd></div><div><dt>Quantity</dt><dd data-procurement-view="quantity">—</dd></div>
    <div><dt>Supplier</dt><dd data-procurement-view="supplier">—</dd></div><div><dt>Requested By</dt><dd data-procurement-view="requested_by">—</dd></div>
    <div><dt>Request Date</dt><dd data-procurement-view="request_date">—</dd></div><div><dt>Status</dt><dd data-procurement-view="status">—</dd></div>
</dl></section>
<section class="pcms-detail-section"><h3>Delivery Information</h3><dl class="pcms-detail-grid">
    <div><dt>Delivery Date</dt><dd data-procurement-view="delivery_date">Not Yet Delivered</dd></div><div><dt>Delivered Quantity</dt><dd data-procurement-view="delivered_quantity">0</dd></div>
</dl></section>
<section class="pcms-detail-section"><h3>Approval Information</h3><dl class="pcms-detail-grid">
    <div><dt>Approved By</dt><dd data-procurement-view="approved_by">Not Yet Approved</dd></div><div><dt>Approval Date</dt><dd data-procurement-view="approval_date">Not Yet Approved</dd></div>
    <div class="pcms-detail-span-2"><dt>Remarks</dt><dd data-procurement-view="remarks">—</dd></div>
</dl></section>
</div>
<footer class="pcms-modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Close</button><button type="button" class="btn btn-warning" id="editProcurementFromView">Edit Procurement</button></footer>
</div></div>

<div id="editProcurementModal" class="modal pcms-modal pcms-modal--lg" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="editProcurementTitle">
<div class="modal-content pcms-modal-dialog">
<form id="editProcurementForm" class="pcms-modal-form" method="POST" action="save_procurement.php">
<input type="hidden" id="editProcurementRowID" name="id">
<header class="pcms-modal-header"><div><span class="pcms-modal-eyebrow">Procurement</span><h2 id="editProcurementTitle">Edit Procurement</h2><p>Changes continue through the existing status transition and Inventory transaction rules.</p></div><button type="button" class="close-modal" data-modal-close aria-label="Close Edit Procurement"><span aria-hidden="true">&times;</span></button></header>
<div class="pcms-modal-body"><div class="pcms-form-grid">
    <div class="form-row"><label for="editProcurementID">Procurement ID</label><input type="text" id="editProcurementID" readonly></div>
    <div class="form-row"><label for="editProcurementItem">Item Name</label><input type="text" id="editProcurementItem" name="item_name" required></div>
    <div class="form-row"><label for="editProcurementCategory">Category</label><select id="editProcurementCategory" name="category"><?php foreach ($procurementCategories as $value): ?><option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($value) ?></option><?php endforeach; ?></select></div>
    <div class="form-row"><label for="editProcurementQuantity">Quantity</label><input type="number" id="editProcurementQuantity" name="quantity" min="1" required></div>
    <div class="form-row"><label for="editProcurementSupplier">Supplier</label><input type="text" id="editProcurementSupplier" name="supplier" required></div>
    <div class="form-row"><label for="editProcurementRequestedBy">Requested By</label><input type="text" id="editProcurementRequestedBy" name="requested_by" required></div>
    <div class="form-row"><label for="editProcurementRequestDate">Request Date</label><input type="date" id="editProcurementRequestDate" name="request_date" required></div>
    <div class="form-row"><label for="editProcurementStatus">Status</label><select id="editProcurementStatus" name="status"><?php foreach ($procurementStatuses as $value): ?><option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($value) ?></option><?php endforeach; ?></select></div>
    <div class="form-row"><label for="editProcurementApprovedBy">Approved By</label><input type="text" id="editProcurementApprovedBy" name="approved_by"></div>
    <div class="form-row"><label for="editProcurementApprovalDate">Approval Date</label><input type="date" id="editProcurementApprovalDate" name="approval_date"></div>
    <div class="form-row"><label for="editProcurementDeliveryDate">Delivery Date</label><input type="date" id="editProcurementDeliveryDate" name="delivery_date"></div>
    <div class="form-row pcms-form-span-2"><label for="editProcurementRemarks">Remarks</label><textarea id="editProcurementRemarks" name="remarks" rows="3"></textarea></div>
</div></div>
<footer class="pcms-modal-footer"><button type="button" class="btn btn-outline" data-modal-close>Cancel</button><button type="submit" class="btn btn-warning">Update Procurement</button></footer>
</form></div></div>
