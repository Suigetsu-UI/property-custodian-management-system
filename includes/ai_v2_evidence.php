<?php

require_once __DIR__ . '/ai_insight_functions.php';

const AI_V2_INTERPRETATION_VERSION = 'AIDSV2.0';

function resolveAiEvidenceAssetBusinessId(
    array $event,
    array $assetInternalByBusiness,
    array $maintenanceAssetBusiness,
    array $auditAssetBusiness
): ?string {
    $module = (string) ($event['module'] ?? '');
    $businessId = (string) ($event['business_id'] ?? '');
    $relatedId = (string) ($event['related_business_id'] ?? '');

    if ($module === 'Asset Registry') {
        return isset($assetInternalByBusiness[$businessId])
            ? $businessId
            : null;
    }

    if (isset($assetInternalByBusiness[$relatedId])) {
        return $relatedId;
    }

    return match ($module) {
        'Maintenance' => $maintenanceAssetBusiness[$businessId] ?? null,
        'Audit' => $auditAssetBusiness[$businessId] ?? null,
        default => null,
    };
}

/**
 * Load the complete, read-only evidence portfolio in four bulk queries.
 * The explicit Manila analysis date is bound into every historical query.
 */
function loadAiV2Evidence(PDO $pdo, string $analysisDate): array
{
    if (!parseAiInsightDate($analysisDate)) {
        throw new InvalidArgumentException('A valid analysis date is required.');
    }

    $assets = $pdo->query(
        "SELECT
            id, asset_id, asset_name, category, brand, model,
            acquisition_date, custodian, status
         FROM assets
         ORDER BY asset_id"
    )->fetchAll();

    $maintenanceStatement = $pdo->prepare(
        "SELECT
            m.id, m.maintenance_id, m.asset_id,
            m.maintenance_type, m.scheduled_date, m.status
         FROM maintenance m
         WHERE m.scheduled_date IS NULL
            OR m.scheduled_date <= :analysis_date
         ORDER BY m.scheduled_date, m.id"
    );
    $maintenanceStatement->execute(['analysis_date' => $analysisDate]);
    $maintenanceRows = $maintenanceStatement->fetchAll();

    $auditStatement = $pdo->prepare(
        "SELECT
            au.id, au.audit_id, au.asset_id,
            au.audit_date, au.result, au.status
         FROM audits au
         WHERE au.audit_date IS NULL
            OR au.audit_date <= :analysis_date
         ORDER BY au.audit_date, au.id"
    );
    $auditStatement->execute(['analysis_date' => $analysisDate]);
    $auditRows = $auditStatement->fetchAll();

    $eventStatement = $pdo->prepare(
        "SELECT
            id, module, event_type, business_id,
            related_business_id, record_name_snap,
            category_snap, event_date, from_status,
            to_status, outcome, description
         FROM property_events
         WHERE event_date <= :analysis_date
           AND module IN ('Asset Registry', 'Maintenance', 'Audit')
         ORDER BY event_date, id"
    );
    $eventStatement->execute(['analysis_date' => $analysisDate]);
    $events = $eventStatement->fetchAll();

    $assetInternalByBusiness = [];
    $assetBusinessByInternal = [];

    foreach ($assets as $asset) {
        $internalId = (int) $asset['id'];
        $businessId = (string) $asset['asset_id'];
        $assetInternalByBusiness[$businessId] = $internalId;
        $assetBusinessByInternal[$internalId] = $businessId;
    }

    $maintenanceByAsset = [];
    $maintenanceAssetBusiness = [];

    foreach ($maintenanceRows as $row) {
        $internalId = (int) $row['asset_id'];
        $maintenanceByAsset[$internalId][] = $row;
        $assetBusinessId = $assetBusinessByInternal[$internalId] ?? null;

        if ($assetBusinessId !== null) {
            $maintenanceAssetBusiness[(string) $row['maintenance_id']] =
                $assetBusinessId;
        }
    }

    $auditsByAsset = [];
    $auditAssetBusiness = [];

    foreach ($auditRows as $row) {
        $internalId = (int) $row['asset_id'];
        $auditsByAsset[$internalId][] = $row;
        $assetBusinessId = $assetBusinessByInternal[$internalId] ?? null;

        if ($assetBusinessId !== null) {
            $auditAssetBusiness[(string) $row['audit_id']] = $assetBusinessId;
        }
    }

    $eventsByAsset = [];

    foreach ($events as $event) {
        $assetBusinessId = resolveAiEvidenceAssetBusinessId(
            $event,
            $assetInternalByBusiness,
            $maintenanceAssetBusiness,
            $auditAssetBusiness
        );

        if ($assetBusinessId === null) {
            continue;
        }

        $internalId = $assetInternalByBusiness[$assetBusinessId] ?? null;

        if ($internalId === null) {
            continue;
        }

        $event['resolved_asset_id'] = $assetBusinessId;
        $eventsByAsset[$internalId][] = $event;
    }

    return [
        'assets' => $assets,
        'maintenance_by_asset' => $maintenanceByAsset,
        'audits_by_asset' => $auditsByAsset,
        'events_by_asset' => $eventsByAsset,
    ];
}

function findAiV2AssetEvidence(array $evidence, string $assetBusinessId): ?array
{
    foreach ($evidence['assets'] ?? [] as $asset) {
        if (($asset['asset_id'] ?? '') !== $assetBusinessId) {
            continue;
        }

        $internalId = (int) $asset['id'];

        return [
            'asset' => $asset,
            'maintenance' => $evidence['maintenance_by_asset'][$internalId] ?? [],
            'audits' => $evidence['audits_by_asset'][$internalId] ?? [],
            'events' => $evidence['events_by_asset'][$internalId] ?? [],
        ];
    }

    return null;
}
