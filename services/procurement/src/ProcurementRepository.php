<?php

require_once __DIR__ . '/ProcurementDomainException.php';
require_once __DIR__ . '/ProcurementStore.php';

final class ProcurementRepository implements ProcurementStore
{
    private const ALLOWED_TRANSITIONS = [
        'Pending' => ['Pending', 'Approved', 'Rejected', 'Delivered'],
        'Approved' => ['Pending', 'Approved', 'Rejected', 'Delivered'],
        'Rejected' => ['Pending', 'Approved', 'Rejected'],
        'Delivered' => ['Delivered'],
    ];

    private const RECORD_COLUMNS =
        'id, procurement_id, item_name, category, quantity, supplier, ' .
        'requested_by, request_date, status, approved_by, approval_date, ' .
        'delivery_date, remarks, delivered_quantity, created_at, updated_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($filters['per_page'] ?? 25)));
        $search = trim((string) ($filters['search'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $supplier = trim((string) ($filters['supplier'] ?? ''));

        if (
            $status !== '' &&
            !array_key_exists($status, self::ALLOWED_TRANSITIONS)
        ) {
            throw new ProcurementDomainException(
                'INVALID_STATUS_FILTER',
                'The requested Procurement status filter is invalid.',
                400
            );
        }

        $where = [];
        $parameters = [];

        if ($search !== '') {
            $where[] = "(
                lower(procurement_id) LIKE :search
                OR lower(item_name) LIKE :search
                OR lower(supplier) LIKE :search
                OR lower(category) LIKE :search
                OR lower(COALESCE(requested_by, '')) LIKE :search
            )";
            $normalizedSearch = function_exists('mb_strtolower')
                ? mb_strtolower($search, 'UTF-8')
                : strtolower($search);
            $parameters['search'] = '%' . $normalizedSearch . '%';
        }

        if ($status !== '') {
            $where[] = 'status = :status';
            $parameters['status'] = $status;
        }

        if ($supplier !== '') {
            $where[] = 'supplier = :supplier';
            $parameters['supplier'] = $supplier;
        }

        $whereSql = $where === []
            ? ''
            : ' WHERE ' . implode(' AND ', $where);
        $countStatement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM procurement' . $whereSql
        );
        $countStatement->execute($parameters);
        $total = (int) $countStatement->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $statement = $this->pdo->prepare(
            'SELECT ' . self::RECORD_COLUMNS .
            ' FROM procurement' . $whereSql .
            ' ORDER BY id ASC LIMIT :limit OFFSET :offset'
        );

        foreach ($parameters as $name => $value) {
            $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }

        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        $records = array_map(
            [$this, 'normalizeOutputRecord'],
            $statement->fetchAll()
        );

        $supplierStatement = $this->pdo->query(
            "SELECT DISTINCT supplier
             FROM procurement
             WHERE supplier IS NOT NULL
               AND trim(supplier) <> ''
             ORDER BY supplier ASC
             LIMIT 100"
        );

        return [
            'records' => $records,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'filters' => [
                'search' => $search,
                'status' => $status,
                'supplier' => $supplier,
                'suppliers' => array_values(array_filter(array_map(
                    static fn (array $row): string =>
                        trim((string) ($row['supplier'] ?? '')),
                    $supplierStatement->fetchAll()
                ))),
            ],
        ];
    }

    public function find(string $businessId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::RECORD_COLUMNS .
            ' FROM procurement WHERE procurement_id = :procurement_id'
        );
        $statement->execute(['procurement_id' => $businessId]);
        $record = $statement->fetch();

        if (!$record) {
            throw new ProcurementDomainException(
                'PROCUREMENT_NOT_FOUND',
                'The requested procurement record was not found.',
                404
            );
        }

        return $this->normalizeOutputRecord($record);
    }

    public function nextBusinessId(): string
    {
        $row = $this->pdo->query(
            "SELECT nextval('procurement_id_seq') AS number"
        )->fetch();

        if (!$row || !isset($row['number'])) {
            throw new RuntimeException('Business ID generation failed.');
        }

        return 'PRC-' . str_pad(
            (string) ((int) $row['number']),
            6,
            '0',
            STR_PAD_LEFT
        );
    }

    public function create(array $input, string $actor): array
    {
        $record = $this->normalizeInput($input, true);
        $businessId = $record['procurement_id'];

        if (preg_match('/^PRC-\d{6}$/', $businessId) !== 1) {
            throw new ProcurementDomainException(
                'INVALID_PROCUREMENT_ID',
                'The Procurement ID format is invalid.',
                422
            );
        }

        try {
            $this->pdo->beginTransaction();

            $duplicate = $this->pdo->prepare(
                'SELECT 1 FROM procurement WHERE procurement_id = :id'
            );
            $duplicate->execute(['id' => $businessId]);

            if ($duplicate->fetch()) {
                throw new ProcurementDomainException(
                    'DUPLICATE_PROCUREMENT_ID',
                    'The Procurement ID has already been used.',
                    409
                );
            }

            $deliveredQuantity = 0;

            if ($record['status'] === 'Delivered') {
                $this->adjustInventoryStock(
                    $record['item_name'],
                    $record['category'],
                    $record['quantity']
                );
                $deliveredQuantity = $record['quantity'];
            }

            $statement = $this->pdo->prepare(
                "INSERT INTO procurement (
                    procurement_id, item_name, category, quantity, supplier,
                    requested_by, request_date, status, approved_by,
                    approval_date, delivery_date, remarks, delivered_quantity
                 ) VALUES (
                    :procurement_id, :item_name, :category, :quantity, :supplier,
                    :requested_by, :request_date, :status, :approved_by,
                    :approval_date, :delivery_date, :remarks,
                    :delivered_quantity
                 )"
            );
            $statement->execute($record + [
                'delivered_quantity' => $deliveredQuantity,
            ]);

            $this->recordPropertyEvent([
                'module' => 'Procurement',
                'event_type' => 'Requested',
                'business_id' => $businessId,
                'record_name_snap' => $record['item_name'],
                'category_snap' => $record['category'],
                'event_date' => $record['request_date'] ?? $this->today(),
                'to_status' => 'Pending',
                'performed_by' => $actor,
            ]);

            if ($record['status'] !== 'Pending') {
                $this->recordStatusEvent(
                    $record,
                    $businessId,
                    'Pending',
                    $record['status'],
                    $actor
                );
            }

            if ($record['status'] === 'Delivered') {
                $this->recordInventoryMovement(
                    $record['item_name'],
                    $record['category'],
                    $record['quantity'],
                    $record['delivery_date'],
                    $businessId,
                    $actor,
                    'Procurement delivery received.'
                );
            }

            $this->pdo->commit();

            return $this->find($businessId);
        } catch (Throwable $error) {
            $this->rollBackIfNeeded();

            if ($error instanceof ProcurementDomainException) {
                throw $error;
            }

            if (
                $error instanceof PDOException &&
                $error->getCode() === '23505'
            ) {
                throw new ProcurementDomainException(
                    'DUPLICATE_PROCUREMENT_ID',
                    'The Procurement ID has already been used.',
                    409
                );
            }

            throw $error;
        }
    }

    public function update(
        string $businessId,
        array $input,
        string $actor
    ): array {
        $record = $this->normalizeInput($input, false);

        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare(
                "SELECT procurement_id, item_name, category, quantity,
                        supplier, requested_by, request_date, status,
                        approved_by, approval_date, delivery_date, remarks,
                        delivered_quantity
                 FROM procurement
                 WHERE procurement_id = :procurement_id
                 FOR UPDATE"
            );
            $statement->execute(['procurement_id' => $businessId]);
            $existing = $statement->fetch();

            if (!$existing) {
                throw new ProcurementDomainException(
                    'PROCUREMENT_NOT_FOUND',
                    'The requested procurement record was not found.',
                    404
                );
            }

            $previousStatus = (string) $existing['status'];

            if (!in_array(
                $record['status'],
                self::ALLOWED_TRANSITIONS[$previousStatus] ?? [],
                true
            )) {
                throw new ProcurementDomainException(
                    'INVALID_STATUS_TRANSITION',
                    'That status change is not allowed for this procurement record.',
                    409
                );
            }

            $oldDelivered = (int) $existing['delivered_quantity'];
            $newDelivered = $oldDelivered;
            $procurementChanged = false;

            foreach (array_keys($record) as $field) {
                if (
                    (string) ($existing[$field] ?? '') !==
                    (string) ($record[$field] ?? '')
                ) {
                    $procurementChanged = true;
                    break;
                }
            }

            if ($record['status'] === 'Delivered') {
                $this->transferInventoryStock(
                    (string) $existing['item_name'],
                    (string) $existing['category'],
                    $oldDelivered,
                    $record['item_name'],
                    $record['category'],
                    $record['quantity']
                );
                $newDelivered = $record['quantity'];
                $sameItem =
                    strcasecmp((string) $existing['item_name'], $record['item_name']) === 0 &&
                    strcasecmp((string) $existing['category'], $record['category']) === 0;

                if ($sameItem) {
                    $this->recordInventoryMovement(
                        $record['item_name'],
                        $record['category'],
                        $record['quantity'] - $oldDelivered,
                        $record['delivery_date'],
                        $businessId,
                        $actor,
                        'Procurement delivery adjustment.'
                    );
                } else {
                    if ($oldDelivered > 0) {
                        $this->recordInventoryMovement(
                            (string) $existing['item_name'],
                            (string) $existing['category'],
                            -$oldDelivered,
                            $this->today(),
                            $businessId,
                            $actor,
                            'Procurement delivered item changed.'
                        );
                    }

                    $this->recordInventoryMovement(
                        $record['item_name'],
                        $record['category'],
                        $record['quantity'],
                        $record['delivery_date'],
                        $businessId,
                        $actor,
                        'Procurement delivery received.'
                    );
                }
            }

            $update = $this->pdo->prepare(
                "UPDATE procurement SET
                    item_name = :item_name,
                    category = :category,
                    quantity = :quantity,
                    supplier = :supplier,
                    requested_by = :requested_by,
                    request_date = :request_date,
                    status = :status,
                    approved_by = :approved_by,
                    approval_date = :approval_date,
                    delivery_date = :delivery_date,
                    remarks = :remarks,
                    delivered_quantity = :delivered_quantity,
                    updated_at = now()
                 WHERE procurement_id = :business_id"
            );
            $update->execute([
                'item_name' => $record['item_name'],
                'category' => $record['category'],
                'quantity' => $record['quantity'],
                'supplier' => $record['supplier'],
                'requested_by' => $record['requested_by'],
                'request_date' => $record['request_date'],
                'status' => $record['status'],
                'approved_by' => $record['approved_by'],
                'approval_date' => $record['approval_date'],
                'delivery_date' => $record['delivery_date'],
                'remarks' => $record['remarks'],
                'delivered_quantity' => $newDelivered,
                'business_id' => $businessId,
            ]);

            if ($previousStatus !== $record['status']) {
                $this->recordStatusEvent(
                    $record,
                    $businessId,
                    $previousStatus,
                    $record['status'],
                    $actor
                );
            } elseif ($procurementChanged) {
                $this->recordPropertyEvent([
                    'module' => 'Procurement',
                    'event_type' => 'Updated',
                    'business_id' => $businessId,
                    'record_name_snap' => $record['item_name'],
                    'category_snap' => $record['category'],
                    'event_date' => $this->today(),
                    'performed_by' => $actor,
                    'description' => 'Procurement details updated.',
                ]);
            }

            $this->pdo->commit();

            return $this->find($businessId);
        } catch (Throwable $error) {
            $this->rollBackIfNeeded();
            throw $error;
        }
    }

    public function delete(string $businessId, string $actor): array
    {
        try {
            $this->pdo->beginTransaction();
            $statement = $this->pdo->prepare(
                "SELECT procurement_id, item_name, category, status,
                        delivered_quantity
                 FROM procurement
                 WHERE procurement_id = :procurement_id
                 FOR UPDATE"
            );
            $statement->execute(['procurement_id' => $businessId]);
            $record = $statement->fetch();

            if (!$record) {
                throw new ProcurementDomainException(
                    'PROCUREMENT_NOT_FOUND',
                    'The requested procurement record was not found.',
                    404
                );
            }

            if ((int) $record['delivered_quantity'] > 0) {
                throw new ProcurementDomainException(
                    'DELIVERED_PROCUREMENT_DELETE_FORBIDDEN',
                    'A delivered procurement record cannot be deleted.',
                    409
                );
            }

            $this->recordPropertyEvent([
                'module' => 'Procurement',
                'event_type' => 'Deleted',
                'business_id' => $businessId,
                'record_name_snap' => $record['item_name'],
                'category_snap' => $record['category'],
                'event_date' => $this->today(),
                'from_status' => $record['status'],
                'performed_by' => $actor,
            ]);

            $delete = $this->pdo->prepare(
                'DELETE FROM procurement WHERE procurement_id = :id'
            );
            $delete->execute(['id' => $businessId]);
            $this->pdo->commit();

            return ['procurement_id' => $businessId, 'deleted' => true];
        } catch (Throwable $error) {
            $this->rollBackIfNeeded();
            throw $error;
        }
    }

    public function summary(): array
    {
        $counts = $this->pdo->query(
            "SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'Pending') AS pending,
                COUNT(*) FILTER (WHERE status = 'Approved') AS approved,
                COUNT(*) FILTER (WHERE status = 'Delivered') AS delivered,
                COUNT(*) FILTER (WHERE status = 'Rejected') AS rejected,
                COUNT(*) FILTER (
                    WHERE status IN ('Pending', 'Approved')
                ) AS open
             FROM procurement"
        )->fetch();

        $suppliers = $this->pdo->query(
            "SELECT
                CASE WHEN supplier IS NULL OR trim(supplier) = ''
                     THEN 'Unknown' ELSE supplier END AS supplier_name,
                COUNT(*) AS record_count
             FROM procurement
             GROUP BY supplier_name
             ORDER BY supplier_name ASC
             LIMIT 100"
        )->fetchAll();
        $bySupplier = [];

        foreach ($suppliers as $row) {
            $bySupplier[(string) $row['supplier_name']] =
                (int) $row['record_count'];
        }

        $recent = $this->pdo->query(
            "SELECT procurement_id, item_name, quantity, supplier, status
             FROM procurement ORDER BY id DESC LIMIT 5"
        )->fetchAll();
        $delivered = $this->pdo->query(
            "SELECT procurement_id, item_name, category, quantity, status
             FROM procurement
             WHERE status = 'Delivered'
             ORDER BY id DESC LIMIT 5"
        )->fetchAll();

        foreach ([$recent, $delivered] as &$records) {
            foreach ($records as &$record) {
                $record['quantity'] = (int) $record['quantity'];
            }
            unset($record);
        }
        unset($records);

        return [
            'total' => (int) ($counts['total'] ?? 0),
            'pending' => (int) ($counts['pending'] ?? 0),
            'approved' => (int) ($counts['approved'] ?? 0),
            'delivered' => (int) ($counts['delivered'] ?? 0),
            'rejected' => (int) ($counts['rejected'] ?? 0),
            'open' => (int) ($counts['open'] ?? 0),
            'by_supplier' => $bySupplier,
            'recent' => $recent,
            'recently_delivered' => $delivered,
        ];
    }

    private function normalizeInput(array $input, bool $creating): array
    {
        $status = trim((string) ($input['status'] ?? ''));

        if (!array_key_exists($status, self::ALLOWED_TRANSITIONS)) {
            throw new ProcurementDomainException(
                'INVALID_STATUS',
                'The submitted Procurement status is invalid.',
                422
            );
        }

        $quantity = filter_var(
            $input['quantity'] ?? null,
            FILTER_VALIDATE_INT
        );
        $record = [
            'item_name' => trim((string) ($input['item_name'] ?? '')),
            'category' => trim((string) ($input['category'] ?? '')),
            'quantity' => $quantity === false ? 0 : (int) $quantity,
            'supplier' => trim((string) ($input['supplier'] ?? '')),
            'requested_by' => $this->nullableString($input['requested_by'] ?? null),
            'request_date' => $this->nullableDate($input['request_date'] ?? null),
            'status' => $status,
            'approved_by' => $this->nullableString($input['approved_by'] ?? null),
            'approval_date' => $this->nullableDate($input['approval_date'] ?? null),
            'delivery_date' => $this->nullableDate($input['delivery_date'] ?? null),
            'remarks' => $this->nullableString($input['remarks'] ?? null),
        ];

        if (
            $record['quantity'] <= 0 ||
            $record['item_name'] === '' ||
            $record['category'] === '' ||
            $record['supplier'] === ''
        ) {
            throw new ProcurementDomainException(
                'INVALID_PROCUREMENT_INPUT',
                'Item, category, positive quantity, and supplier are required.',
                422
            );
        }

        if ($record['status'] === 'Delivered' && $record['delivery_date'] === null) {
            throw new ProcurementDomainException(
                'DELIVERY_DATE_REQUIRED',
                'A Delivery Date is required when status is Delivered.',
                422
            );
        }

        if ($record['status'] !== 'Delivered') {
            $record['delivery_date'] = null;
        }

        if ($creating) {
            $record['procurement_id'] = trim((string) (
                $input['procurement_id'] ?? ''
            ));
        }

        return $record;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function nullableDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$date ||
            ($errors !== false && (
                $errors['warning_count'] > 0 || $errors['error_count'] > 0
            )) ||
            $date->format('Y-m-d') !== $value
        ) {
            throw new ProcurementDomainException(
                'INVALID_DATE',
                'One or more submitted dates are invalid.',
                422
            );
        }

        return $value;
    }

    private function normalizeOutputRecord(array $record): array
    {
        $record['id'] = (int) $record['id'];
        $record['quantity'] = (int) $record['quantity'];
        $record['delivered_quantity'] = (int) $record['delivered_quantity'];
        return $record;
    }

    private function adjustInventoryStock(
        string $assetName,
        string $category,
        int $delta
    ): void {
        if ($delta === 0) {
            return;
        }

        $select = $this->pdo->prepare(
            "SELECT id, quantity FROM inventory
             WHERE lower(asset_name) = lower(:asset_name)
               AND lower(category) = lower(:category)
             FOR UPDATE"
        );
        $select->execute([
            'asset_name' => $assetName,
            'category' => $category,
        ]);
        $row = $select->fetch();

        if ($row) {
            $newQuantity = (int) $row['quantity'] + $delta;

            if ($newQuantity < 0) {
                throw new ProcurementDomainException(
                    'INVENTORY_NEGATIVE',
                    'This change would reduce Inventory below zero.',
                    409
                );
            }

            $update = $this->pdo->prepare(
                'UPDATE inventory SET quantity = :quantity, updated_at = now() WHERE id = :id'
            );
            $update->execute([
                'quantity' => $newQuantity,
                'id' => $row['id'],
            ]);
            return;
        }

        if ($delta < 0) {
            throw new ProcurementDomainException(
                'INVENTORY_NEGATIVE',
                'This change would reduce Inventory below zero.',
                409
            );
        }

        $inventoryNumber = (int) $this->pdo->query(
            "SELECT nextval('inventory_id_seq')"
        )->fetchColumn();
        $inventoryId = 'INV-' . str_pad(
            (string) $inventoryNumber,
            6,
            '0',
            STR_PAD_LEFT
        );
        $insert = $this->pdo->prepare(
            "INSERT INTO inventory (
                inventory_id, asset_name, category, quantity, condition
             ) VALUES (
                :inventory_id, :asset_name, :category, :quantity, 'Good'
             ) ON CONFLICT DO NOTHING RETURNING id"
        );
        $insert->execute([
            'inventory_id' => $inventoryId,
            'asset_name' => $assetName,
            'category' => $category,
            'quantity' => $delta,
        ]);

        if ($insert->fetch()) {
            return;
        }

        $select->execute([
            'asset_name' => $assetName,
            'category' => $category,
        ]);
        $row = $select->fetch();

        if (!$row) {
            throw new RuntimeException('Inventory update failed.');
        }

        $update = $this->pdo->prepare(
            'UPDATE inventory SET quantity = :quantity, updated_at = now() WHERE id = :id'
        );
        $update->execute([
            'quantity' => (int) $row['quantity'] + $delta,
            'id' => $row['id'],
        ]);
    }

    private function transferInventoryStock(
        string $oldName,
        string $oldCategory,
        int $oldQuantity,
        string $newName,
        string $newCategory,
        int $newQuantity
    ): void {
        if (
            strcasecmp($oldName, $newName) === 0 &&
            strcasecmp($oldCategory, $newCategory) === 0
        ) {
            $this->adjustInventoryStock(
                $newName,
                $newCategory,
                $newQuantity - $oldQuantity
            );
            return;
        }

        $items = [
            ['role' => 'old', 'name' => $oldName, 'category' => $oldCategory],
            ['role' => 'new', 'name' => $newName, 'category' => $newCategory],
        ];
        usort($items, static function (array $left, array $right): int {
            $name = strcasecmp($left['name'], $right['name']);
            return $name !== 0
                ? $name
                : strcasecmp($left['category'], $right['category']);
        });
        $select = $this->pdo->prepare(
            "SELECT id, quantity FROM inventory
             WHERE lower(asset_name) = lower(:asset_name)
               AND lower(category) = lower(:category)
             FOR UPDATE"
        );
        $rows = ['old' => null, 'new' => null];

        foreach ($items as $item) {
            $select->execute([
                'asset_name' => $item['name'],
                'category' => $item['category'],
            ]);
            $rows[$item['role']] = $select->fetch() ?: null;
        }

        if ($oldQuantity > 0) {
            $old = $rows['old'];
            $remaining = $old ? (int) $old['quantity'] - $oldQuantity : -1;

            if ($remaining < 0) {
                throw new ProcurementDomainException(
                    'INVENTORY_NEGATIVE',
                    'This change would reduce Inventory below zero.',
                    409
                );
            }

            $update = $this->pdo->prepare(
                'UPDATE inventory SET quantity = :quantity, updated_at = now() WHERE id = :id'
            );
            $update->execute(['quantity' => $remaining, 'id' => $old['id']]);
        }

        if ($newQuantity === 0) {
            return;
        }

        if ($rows['new']) {
            $update = $this->pdo->prepare(
                'UPDATE inventory SET quantity = :quantity, updated_at = now() WHERE id = :id'
            );
            $update->execute([
                'quantity' => (int) $rows['new']['quantity'] + $newQuantity,
                'id' => $rows['new']['id'],
            ]);
            return;
        }

        $this->adjustInventoryStock($newName, $newCategory, $newQuantity);
    }

    private function recordStatusEvent(
        array $record,
        string $businessId,
        string $fromStatus,
        string $toStatus,
        string $actor
    ): void {
        $eventType = match ($toStatus) {
            'Approved' => 'Approved',
            'Rejected' => 'Rejected',
            'Delivered' => 'Delivered',
            default => 'Status Changed',
        };
        $eventDate = match ($toStatus) {
            'Approved' => $record['approval_date'] ?? $this->today(),
            'Delivered' => $record['delivery_date'],
            default => $this->today(),
        };
        $this->recordPropertyEvent([
            'module' => 'Procurement',
            'event_type' => $eventType,
            'business_id' => $businessId,
            'record_name_snap' => $record['item_name'],
            'category_snap' => $record['category'],
            'event_date' => $eventDate,
            'quantity_delta' => $toStatus === 'Delivered'
                ? $record['quantity']
                : null,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'performed_by' => $actor,
        ]);
    }

    private function recordInventoryMovement(
        string $assetName,
        string $category,
        int $delta,
        string $eventDate,
        string $relatedId,
        string $actor,
        string $description
    ): void {
        if ($delta === 0) {
            return;
        }

        $statement = $this->pdo->prepare(
            "SELECT inventory_id, asset_name, category
             FROM inventory
             WHERE lower(asset_name) = lower(:asset_name)
               AND lower(category) = lower(:category)
             LIMIT 1"
        );
        $statement->execute([
            'asset_name' => $assetName,
            'category' => $category,
        ]);
        $inventory = $statement->fetch();

        if (!$inventory) {
            throw new RuntimeException('Inventory event target is missing.');
        }

        $this->recordPropertyEvent([
            'module' => 'Inventory',
            'event_type' => $delta > 0 ? 'Stock Increased' : 'Stock Decreased',
            'business_id' => $inventory['inventory_id'],
            'related_business_id' => $relatedId,
            'record_name_snap' => $inventory['asset_name'],
            'category_snap' => $inventory['category'],
            'event_date' => $eventDate,
            'quantity_delta' => $delta,
            'performed_by' => $actor,
            'description' => $description,
        ]);
    }

    private function recordPropertyEvent(array $event): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO property_events (
                module, event_type, business_id, related_business_id,
                record_name_snap, category_snap, event_date, quantity_delta,
                from_status, to_status, outcome, performed_by, description
             ) VALUES (
                :module, :event_type, :business_id, :related_business_id,
                :record_name_snap, :category_snap, :event_date, :quantity_delta,
                :from_status, :to_status, :outcome, :performed_by, :description
             )"
        );
        $statement->execute([
            'module' => $event['module'],
            'event_type' => $event['event_type'],
            'business_id' => $event['business_id'],
            'related_business_id' => $event['related_business_id'] ?? null,
            'record_name_snap' => $event['record_name_snap'] ?? null,
            'category_snap' => $event['category_snap'] ?? null,
            'event_date' => $event['event_date'],
            'quantity_delta' => $event['quantity_delta'] ?? null,
            'from_status' => $event['from_status'] ?? null,
            'to_status' => $event['to_status'] ?? null,
            'outcome' => $event['outcome'] ?? null,
            'performed_by' => $event['performed_by'] ?? null,
            'description' => $event['description'] ?? null,
        ]);
    }

    private function today(): string
    {
        return (new DateTimeImmutable(
            'now',
            new DateTimeZone('Asia/Manila')
        ))->format('Y-m-d');
    }

    private function rollBackIfNeeded(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
