<?php

require_once __DIR__ . "/../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";

$pdo = getDbConnection();

$keyword =
    trim($_GET['search'] ?? '');

$statusParam =
    trim($_GET['status'] ?? '');

$resultParam =
    trim($_GET['result'] ?? '');

$sql =
    "SELECT
        au.*,
        a.asset_id AS asset_business_id,
        a.asset_name AS current_asset_name,
        a.category AS current_category,
        a.custodian AS current_custodian,
        a.status AS current_asset_status
     FROM audits au
     JOIN assets a
       ON a.id = au.asset_id
     WHERE 1=1";

$params = [];

if ($keyword !== '') {

    $searchValue =
        '%' . strtolower($keyword) . '%';

    $sql .=
        " AND (
            lower(au.audit_id)
                LIKE :kw_audit_id
            OR lower(a.asset_id)
                LIKE :kw_asset_id
            OR lower(au.asset_name_snap)
                LIKE :kw_asset_name
            OR lower(au.auditor)
                LIKE :kw_auditor
            OR lower(au.status)
                LIKE :kw_status
            OR lower(au.result)
                LIKE :kw_result
          )";

    $params['kw_audit_id'] =
        $searchValue;

    $params['kw_asset_id'] =
        $searchValue;

    $params['kw_asset_name'] =
        $searchValue;

    $params['kw_auditor'] =
        $searchValue;

    $params['kw_status'] =
        $searchValue;

    $params['kw_result'] =
        $searchValue;
}

if ($statusParam !== '') {
    $sql .= " AND au.status = :status";

    $params['status'] =
        $statusParam;
}

if ($resultParam !== '') {
    $sql .= " AND au.result = :result";

    $params['result'] =
        $resultParam;
}

$sql .= " ORDER BY au.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$auditRows =
    $stmt->fetchAll();

?>

<table
    class="asset-table"
    id="auditTable"
>

<thead>

<tr>

<th>Audit ID</th>
<th>Asset</th>
<th>Auditor</th>
<th>Date</th>
<th>Status</th>
<th>Result</th>
<th>Actions</th>

</tr>

</thead>

<tbody>

<?php foreach ($auditRows as $item): ?>

<?php
$auditPayload = htmlspecialchars(
    json_encode($item, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE),
    ENT_QUOTES,
    'UTF-8'
);
?>

<tr class="audit-row" data-record="<?= $auditPayload ?>">

<td>
<?= htmlspecialchars($item['audit_id']) ?>
</td>

<td>
<?= htmlspecialchars($item['asset_name_snap']) ?>
</td>

<td>
<?= htmlspecialchars($item['auditor']) ?>
</td>

<td>
<?= htmlspecialchars($item['audit_date'] ?? '') ?>
</td>

<td>
<?= htmlspecialchars($item['status']) ?>
</td>

<td>
<?= htmlspecialchars($item['result']) ?>
</td>

<td>

<a
    href="view_audit.php?id=<?= (int) $item['id'] ?>"
    class="btn btn-primary"
    data-audit-action="view"
>
View
</a>

<a
    href="edit_audit.php?id=<?= (int) $item['id'] ?>"
    class="btn btn-warning"
    data-audit-action="edit"
>
Edit
</a>

<form method="POST" action="delete_audit.php" class="pcms-inline-action" onsubmit="return confirm('Delete this audit record?');">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
    <button type="submit" class="btn btn-danger">Delete</button>
</form>

</td>

</tr>

<?php endforeach; ?>

<?php if (empty($auditRows)): ?>

<tr>

<td
    colspan="7"
    style="text-align:center; padding:40px;"
>
No audit records found.
</td>

</tr>

<?php endif; ?>

</tbody>

</table>
