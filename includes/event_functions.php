<?php

/*
|--------------------------------------------------------------------------
| Property Event History Helper
|--------------------------------------------------------------------------
| Records meaningful PCMS business events for historical reporting.
|
| IMPORTANT:
| This helper does not begin, commit, or roll back transactions.
| Callers must invoke it inside the same PDO transaction as the
| business operation being recorded.
*/

function recordPropertyEvent(
    PDO $pdo,
    array $event
): void {
    $allowedModules = [
        'Procurement',
        'Inventory',
        'Asset Registry',
        'Maintenance',
        'Audit'
    ];

    $module =
        trim((string) ($event['module'] ?? ''));

    $eventType =
        trim((string) ($event['event_type'] ?? ''));

    $businessId =
        trim((string) ($event['business_id'] ?? ''));

    $eventDate =
        trim((string) ($event['event_date'] ?? ''));

    if (
        !in_array($module, $allowedModules, true) ||
        $eventType === '' ||
        $businessId === '' ||
        !isValidPropertyEventDate($eventDate)
    ) {
        throw new InvalidArgumentException(
            'Invalid property event data.'
        );
    }

    $quantityDelta = null;

    if (
        array_key_exists('quantity_delta', $event) &&
        $event['quantity_delta'] !== null &&
        $event['quantity_delta'] !== ''
    ) {
        $validatedDelta = filter_var(
            $event['quantity_delta'],
            FILTER_VALIDATE_INT
        );

        if ($validatedDelta === false) {
            throw new InvalidArgumentException(
                'Invalid property event quantity delta.'
            );
        }

        $quantityDelta = (int) $validatedDelta;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO property_events (
            module,
            event_type,
            business_id,
            related_business_id,
            record_name_snap,
            category_snap,
            event_date,
            quantity_delta,
            from_status,
            to_status,
            outcome,
            performed_by,
            description
         )
         VALUES (
            :module,
            :event_type,
            :business_id,
            :related_business_id,
            :record_name_snap,
            :category_snap,
            :event_date,
            :quantity_delta,
            :from_status,
            :to_status,
            :outcome,
            :performed_by,
            :description
         )"
    );

    $stmt->execute([
        'module' =>
            $module,

        'event_type' =>
            $eventType,

        'business_id' =>
            $businessId,

        'related_business_id' =>
            nullableEventString(
                $event['related_business_id'] ?? null
            ),

        'record_name_snap' =>
            nullableEventString(
                $event['record_name_snap'] ?? null
            ),

        'category_snap' =>
            nullableEventString(
                $event['category_snap'] ?? null
            ),

        'event_date' =>
            $eventDate,

        'quantity_delta' =>
            $quantityDelta,

        'from_status' =>
            nullableEventString(
                $event['from_status'] ?? null
            ),

        'to_status' =>
            nullableEventString(
                $event['to_status'] ?? null
            ),

        'outcome' =>
            nullableEventString(
                $event['outcome'] ?? null
            ),

        'performed_by' =>
            nullableEventString(
                $event['performed_by'] ?? null
            ),

        'description' =>
            nullableEventString(
                $event['description'] ?? null
            )
    ]);
}

function nullableEventString(
    mixed $value
): ?string {
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);

    return $value !== ''
        ? $value
        : null;
}

function isValidPropertyEventDate(
    string $date
): bool {
    $parts = explode('-', $date);

    if (count($parts) !== 3) {
        return false;
    }

    [$year, $month, $day] = $parts;

    if (
        !ctype_digit($year) ||
        !ctype_digit($month) ||
        !ctype_digit($day)
    ) {
        return false;
    }

    return checkdate(
        (int) $month,
        (int) $day,
        (int) $year
    );
}

function getPropertyEventActor(): ?string
{
    return nullableEventString(
        $_SESSION['user']['employee_id'] ?? null
    );
}

function getPropertyEventToday(): string
{
    $timezone = new DateTimeZone('Asia/Manila');

    return (new DateTimeImmutable(
        'now',
        $timezone
    ))->format('Y-m-d');
}

function recordInventoryStockMovement(
    PDO $pdo,
    string $inventoryBusinessId,
    string $assetName,
    string $category,
    int $quantityDelta,
    string $eventDate,
    ?string $relatedBusinessId,
    ?string $performedBy,
    string $description
): void {
    if ($quantityDelta === 0) {
        return;
    }

    recordPropertyEvent(
        $pdo,
        [
            'module' => 'Inventory',

            'event_type' =>
                $quantityDelta > 0
                    ? 'Stock Increased'
                    : 'Stock Decreased',

            'business_id' =>
                $inventoryBusinessId,

            'related_business_id' =>
                $relatedBusinessId,

            'record_name_snap' =>
                $assetName,

            'category_snap' =>
                $category,

            'event_date' =>
                $eventDate,

            'quantity_delta' =>
                $quantityDelta,

            'performed_by' =>
                $performedBy,

            'description' =>
                $description
        ]
    );
}

function currentPropertyEventActor(): ?string
{
    return getPropertyEventActor();
}

function currentPropertyEventDate(): string
{
    return getPropertyEventToday();
}

function recordInventoryMovementByLogicalItem(
    PDO $pdo,
    string $assetName,
    string $category,
    int $quantityDelta,
    string $eventDate,
    ?string $relatedBusinessId = null,
    ?string $description = null
): void {
    if ($quantityDelta === 0) {
        return;
    }

    $stmt = $pdo->prepare(
        "SELECT inventory_id, asset_name, category
         FROM inventory
         WHERE lower(asset_name) = lower(:asset_name)
           AND lower(category) = lower(:category)
         LIMIT 1"
    );

    $stmt->execute([
        'asset_name' => $assetName,
        'category' => $category,
    ]);

    $inventory = $stmt->fetch();

    if (!$inventory) {
        throw new RuntimeException(
            'INVENTORY_EVENT_TARGET_MISSING'
        );
    }

    recordInventoryStockMovement(
        $pdo,
        $inventory['inventory_id'],
        $inventory['asset_name'],
        $inventory['category'],
        $quantityDelta,
        $eventDate,
        $relatedBusinessId,
        currentPropertyEventActor(),
        $description ?? 'Inventory stock movement.'
    );
}

function recordAssetStatusChangeEvent(
    PDO $pdo,
    array $asset,
    string $newStatus,
    string $eventDate,
    ?string $relatedBusinessId = null,
    ?string $description = null
): void {
    $oldStatus =
        trim((string) ($asset['status'] ?? ''));

    if (
        $oldStatus === '' ||
        $newStatus === '' ||
        $oldStatus === $newStatus
    ) {
        return;
    }

    $assetBusinessId =
        trim((string) ($asset['asset_id'] ?? ''));

    if ($assetBusinessId === '') {
        throw new RuntimeException(
            'ASSET_EVENT_TARGET_MISSING'
        );
    }

    recordPropertyEvent($pdo, [
        'module' => 'Asset Registry',
        'event_type' => 'Status Changed',
        'business_id' => $assetBusinessId,
        'related_business_id' => $relatedBusinessId,
        'record_name_snap' => $asset['asset_name'] ?? null,
        'category_snap' => $asset['category'] ?? null,
        'event_date' => $eventDate,
        'from_status' => $oldStatus,
        'to_status' => $newStatus,
        'performed_by' => currentPropertyEventActor(),
        'description' => $description,
    ]);
}
