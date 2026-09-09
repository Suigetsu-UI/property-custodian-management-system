<?php

require_once "../../auth/check_auth.php";
require_once __DIR__ . "/../../includes/database.php";
require_once __DIR__ . "/../../includes/event_functions.php";

requireValidAccessCsrfPost();

$id = filter_var(
    $_POST['id'] ?? null,
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

$pdo = null;

try {
    $pdo = getDbConnection();

    $pdo->beginTransaction();

    $auditStmt = $pdo->prepare(
        "SELECT
            id,
            asset_id,
            audit_id,
            asset_name_snap,
            category_snap,
            result,
            status
         FROM audits
         WHERE id = :id
         FOR UPDATE"
    );

    $auditStmt->execute([
        'id' => $id
    ]);

    $audit = $auditStmt->fetch();

    if (!$audit) {
        $pdo->rollBack();

        header("Location: index.php");
        exit;
    }

    $assetStmt = $pdo->prepare(
        "SELECT
            id,
            asset_id,
            asset_name,
            category,
            custodian,
            status
         FROM assets
         WHERE id = :id
         FOR UPDATE"
    );

    $assetStmt->execute([
        'id' => $audit['asset_id']
    ]);

    $asset = $assetStmt->fetch();

    if (!$asset) {
        throw new RuntimeException(
            'AUDIT_ASSET_MISSING'
        );
    }

    if (($asset['status'] ?? '') === 'Sold') {
        $pdo->rollBack();
        header("Location: index.php?error=asset_sold");
        exit;
    }

    $delete = $pdo->prepare(
        "DELETE FROM audits
         WHERE id = :id"
    );

    $delete->execute([
        'id' => $id
    ]);

    /*
     * Existing rule:
     * active Maintenance continues to own Asset status.
     */
    $activeMaintenanceStmt = $pdo->prepare(
        "SELECT 1
         FROM maintenance
         WHERE asset_id = :asset_id
           AND status <> 'Completed'
         LIMIT 1"
    );

    $activeMaintenanceStmt->execute([
        'asset_id' => $audit['asset_id']
    ]);

    $hasActiveMaintenance =
        (bool) $activeMaintenanceStmt->fetch();

    $newAssetStatus = null;

    if (!$hasActiveMaintenance) {

        /*
         * The old session helper effectively used the last remaining
         * Audit result. DB internal id gives us deterministic insertion
         * order for the equivalent behavior.
         */
        $remainingStmt = $pdo->prepare(
            "SELECT result
             FROM audits
             WHERE asset_id = :asset_id
             ORDER BY id DESC
             LIMIT 1"
        );

        $remainingStmt->execute([
            'asset_id' => $audit['asset_id']
        ]);

        $remaining =
            $remainingStmt->fetch();

        $remainingResult =
            $remaining['result'] ?? null;

        if ($remainingResult === 'Missing') {
            $newAssetStatus = 'Lost';

        } elseif ($remainingResult === 'Damaged') {
            $newAssetStatus = 'Under Maintenance';

        } else {
            /*
             * No remaining Missing/Damaged finding.
             * Restore according to custody.
             */
            $newAssetStatus =
                !empty($asset['custodian'])
                    ? 'Assigned'
                    : 'Available';
        }

        $updateAsset = $pdo->prepare(
            "UPDATE assets
             SET
                status = :status,
                updated_at = now()
             WHERE id = :id"
        );

        $updateAsset->execute([
            'status' => $newAssetStatus,
            'id' => $audit['asset_id']
        ]);
    }

    recordPropertyEvent($pdo, [
        'module' => 'Audit',
        'event_type' => 'Deleted',
        'business_id' => $audit['audit_id'],
        'related_business_id' => $asset['asset_id'],
        'record_name_snap' => $audit['asset_name_snap'],
        'category_snap' => $audit['category_snap'],
        'event_date' => currentPropertyEventDate(),
        'from_status' => $audit['status'],
        'outcome' => $audit['result'],
        'performed_by' => currentPropertyEventActor(),
    ]);

    if ($newAssetStatus !== null) {
        recordAssetStatusChangeEvent(
            $pdo,
            $asset,
            $newAssetStatus,
            currentPropertyEventDate(),
            $audit['audit_id'],
            'Asset status recalculated after Audit deletion.'
        );
    }

    $pdo->commit();

} catch (Throwable $e) {

    if (
        $pdo instanceof PDO &&
        $pdo->inTransaction()
    ) {
        $pdo->rollBack();
    }

    header("Location: index.php?error=save_failed");
    exit;
}

header("Location: index.php");
exit;
