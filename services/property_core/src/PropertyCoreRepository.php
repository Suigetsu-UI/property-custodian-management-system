<?php

require_once __DIR__ . '/PropertyCoreDomainException.php';
require_once __DIR__ . '/PropertyCoreStore.php';
require_once __DIR__ . '/PropertyCoreLifecycleRules.php';
require_once __DIR__ . '/PropertyCoreTransactionRunner.php';
require_once __DIR__ . '/AssetLifecycleCalculator.php';

final class PropertyCoreRepository implements PropertyCoreStore
{
    private readonly PropertyCoreTransactionRunner $transactionRunner;
    private const ASSET_STATUSES = [
        'Available', 'Assigned', 'Under Maintenance', 'Lost',
    ];
    private const INVENTORY_COLUMNS =
        'inventory_id, asset_name, category, quantity, condition';
    private const ASSET_LIST_COLUMNS =
        'asset_id, asset_name, category, acquisition_date, custodian, status,
         asset_age_months, useful_life_months, aging_threshold_percent,
         lifecycle';

    public function __construct(
        private readonly PDO $pdo,
        private readonly bool $writesAllowed = false
    ) {
        $this->transactionRunner =
            PropertyCoreTransactionRunner::forPdo($pdo);
    }

    public function listInventory(array $filters): array
    {
        [$page, $perPage] = $this->paginationInput($filters);
        $search = $this->search($filters['search'] ?? '');
        $category = trim((string) ($filters['category'] ?? ''));
        $condition = trim((string) ($filters['condition'] ?? ''));
        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = "lower(
                coalesce(inventory_id, '') || ' ' ||
                coalesce(asset_name, '') || ' ' ||
                coalesce(category, '') || ' ' ||
                coalesce(condition, '')
            ) LIKE :search";
            $params['search'] = '%' . $this->lower($search) . '%';
        }
        foreach (
            ['category' => $category, 'condition' => $condition]
            as $column => $value
        ) {
            if ($value !== '') {
                $where[] = $column . ' = :' . $column;
                $params[$column] = $value;
            }
        }

        return $this->pagedList(
            'inventory',
            self::INVENTORY_COLUMNS,
            $where,
            $params,
            $page,
            $perPage,
            [
                'search' => $search,
                'category' => $category,
                'condition' => $condition,
            ],
            [$this, 'normalizeInventory']
        );
    }

    public function findInventory(string $businessId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT ' . self::INVENTORY_COLUMNS . ', created_at, updated_at
             FROM inventory WHERE inventory_id = :inventory_id'
        );
        $statement->execute(['inventory_id' => $businessId]);
        $record = $statement->fetch();

        if (!$record) {
            throw new PropertyCoreDomainException(
                'INVENTORY_NOT_FOUND',
                'The requested Inventory record was not found.',
                404
            );
        }
        return $this->normalizeInventory($record);
    }

    public function inventorySummary(): array
    {
        $row = $this->pdo->query(
            "SELECT COUNT(*) AS total_items,
                COALESCE(SUM(quantity), 0) AS total_quantity,
                COUNT(*) FILTER (WHERE quantity > 0) AS available_items,
                COUNT(*) FILTER (WHERE quantity = 0) AS zero_stock_items,
                COUNT(*) FILTER (
                    WHERE quantity BETWEEN 1 AND 5
                ) AS low_stock_items
             FROM inventory"
        )->fetch();

        return [
            'total_items' => (int) ($row['total_items'] ?? 0),
            'total_quantity' => (int) ($row['total_quantity'] ?? 0),
            'available_items' => (int) ($row['available_items'] ?? 0),
            'zero_stock_items' => (int) ($row['zero_stock_items'] ?? 0),
            'low_stock_items' => (int) ($row['low_stock_items'] ?? 0),
        ];
    }

    public function inventoryOptions(array $filters): array
    {
        $search = $this->search($filters['search'] ?? '');
        $limit = $this->limit($filters['limit'] ?? 10, 20);

        if ($this->length($search) < 2) {
            return $this->optionEnvelope([], $search, $limit);
        }

        $statement = $this->pdo->prepare(
            "SELECT " . self::INVENTORY_COLUMNS . "
             FROM inventory
             WHERE quantity > 0
               AND lower(
                    coalesce(inventory_id, '') || ' ' ||
                    coalesce(asset_name, '') || ' ' ||
                    coalesce(category, '') || ' ' ||
                    coalesce(condition, '')
               ) LIKE :search
             ORDER BY lower(asset_name), lower(category), id
             LIMIT :limit"
        );
        $statement->bindValue(
            ':search',
            '%' . $this->lower($search) . '%',
            PDO::PARAM_STR
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return $this->optionEnvelope(
            array_map([$this, 'normalizeInventory'], $statement->fetchAll()),
            $search,
            $limit
        );
    }

    public function nextInventoryBusinessId(): string
    {
        $this->assertWritesAllowed();
        return $this->nextBusinessId('inventory_id_seq', 'INV');
    }

    public function createInventory(array $input, string $actor): array
    {
        $this->assertWritesAllowed();
        $record = PropertyCoreLifecycleRules::inventoryInput($input, true);

        try {
            return $this->transaction(function () use ($record, $actor): array {
                $duplicate = $this->pdo->prepare(
                    "SELECT 1 FROM inventory
                     WHERE lower(asset_name) = lower(:asset_name)
                       AND lower(category) = lower(:category)
                     LIMIT 1"
                );
                $duplicate->execute([
                    'asset_name' => $record['asset_name'],
                    'category' => $record['category'],
                ]);

                if ($duplicate->fetch()) {
                    throw new PropertyCoreDomainException(
                        'DUPLICATE_INVENTORY_ITEM',
                        'An Inventory item with the same name and category already exists.',
                        409
                    );
                }

                $insert = $this->pdo->prepare(
                    "INSERT INTO inventory (
                        inventory_id, asset_name, category, quantity, condition
                     ) VALUES (
                        :inventory_id, :asset_name, :category,
                        :quantity, :condition
                     )"
                );
                $insert->execute($record);
                $this->recordEvent([
                    'module' => 'Inventory',
                    'event_type' => 'Added',
                    'business_id' => $record['inventory_id'],
                    'record_name_snap' => $record['asset_name'],
                    'category_snap' => $record['category'],
                    'event_date' => $this->today(),
                    'quantity_delta' => $record['quantity'],
                    'performed_by' => $actor,
                ]);
                return $this->findInventory($record['inventory_id']);
            });
        } catch (PDOException $error) {
            if ($error->getCode() === '23505') {
                throw new PropertyCoreDomainException(
                    'DUPLICATE_INVENTORY_ITEM',
                    'That Inventory ID or logical item already exists.',
                    409
                );
            }
            throw $error;
        }
    }

    public function updateInventory(
        string $businessId,
        array $input,
        string $actor
    ): array {
        $this->assertWritesAllowed();
        PropertyCoreLifecycleRules::businessId(
            $businessId,
            'INV',
            'INVALID_INVENTORY_ID'
        );
        $record = PropertyCoreLifecycleRules::inventoryInput($input, false);

        try {
            return $this->transaction(
                function () use ($businessId, $record, $actor): array {
                    $lock = $this->pdo->prepare(
                        "SELECT id, inventory_id, asset_name, category,
                                quantity, condition
                         FROM inventory
                         WHERE inventory_id = :inventory_id
                         FOR UPDATE"
                    );
                    $lock->execute(['inventory_id' => $businessId]);
                    $existing = $lock->fetch();

                    if (!$existing) {
                        throw new PropertyCoreDomainException(
                            'INVENTORY_NOT_FOUND',
                            'The requested Inventory record was not found.',
                            404
                        );
                    }

                    $duplicate = $this->pdo->prepare(
                        "SELECT 1 FROM inventory
                         WHERE lower(asset_name) = lower(:asset_name)
                           AND lower(category) = lower(:category)
                           AND id <> :id
                         LIMIT 1"
                    );
                    $duplicate->execute([
                        'asset_name' => $record['asset_name'],
                        'category' => $record['category'],
                        'id' => $existing['id'],
                    ]);

                    if ($duplicate->fetch()) {
                        throw new PropertyCoreDomainException(
                            'DUPLICATE_INVENTORY_ITEM',
                            'An Inventory item with the same name and category already exists.',
                            409
                        );
                    }

                    $update = $this->pdo->prepare(
                        "UPDATE inventory SET
                            asset_name = :asset_name,
                            category = :category,
                            quantity = :quantity,
                            condition = :condition,
                            updated_at = now()
                         WHERE id = :id"
                    );
                    $update->execute($record + ['id' => $existing['id']]);
                    $delta = $record['quantity'] - (int) $existing['quantity'];
                    $inventoryChanged = false;

                    foreach (
                        ['asset_name', 'category', 'quantity', 'condition']
                        as $field
                    ) {
                        if (
                            (string) ($existing[$field] ?? '') !==
                            (string) ($record[$field] ?? '')
                        ) {
                            $inventoryChanged = true;
                            break;
                        }
                    }

                    if ($inventoryChanged) {
                        $this->recordEvent([
                            'module' => 'Inventory',
                            'event_type' => $delta !== 0
                                ? 'Adjusted'
                                : 'Updated',
                            'business_id' => $businessId,
                            'record_name_snap' => $record['asset_name'],
                            'category_snap' => $record['category'],
                            'event_date' => $this->today(),
                            'quantity_delta' => $delta !== 0 ? $delta : null,
                            'performed_by' => $actor,
                            'description' => $delta !== 0
                                ? 'Manual Inventory quantity adjustment.'
                                : 'Inventory details updated.',
                        ]);
                    }

                    return $this->findInventory($businessId);
                }
            );
        } catch (PDOException $error) {
            if ($error->getCode() === '23505') {
                throw new PropertyCoreDomainException(
                    'DUPLICATE_INVENTORY_ITEM',
                    'An Inventory item with the same name and category already exists.',
                    409
                );
            }
            throw $error;
        }
    }

    public function deleteInventory(string $businessId, string $actor): array
    {
        $this->assertWritesAllowed();
        PropertyCoreLifecycleRules::businessId(
            $businessId,
            'INV',
            'INVALID_INVENTORY_ID'
        );

        try {
            return $this->transaction(
                function () use ($businessId, $actor): array {
                    $lock = $this->pdo->prepare(
                        "SELECT id, inventory_id, asset_name, category, quantity
                         FROM inventory
                         WHERE inventory_id = :inventory_id
                         FOR UPDATE"
                    );
                    $lock->execute(['inventory_id' => $businessId]);
                    $inventory = $lock->fetch();

                    if (!$inventory) {
                        throw new PropertyCoreDomainException(
                            'INVENTORY_NOT_FOUND',
                            'The requested Inventory record was not found.',
                            404
                        );
                    }

                    $linked = $this->pdo->prepare(
                        'SELECT 1 FROM assets WHERE inventory_id = :id LIMIT 1'
                    );
                    $linked->execute(['id' => $inventory['id']]);

                    if ($linked->fetch()) {
                        throw new PropertyCoreDomainException(
                            'INVENTORY_LINKED_TO_ASSETS',
                            'Inventory used by registered Assets cannot be deleted.',
                            409
                        );
                    }

                    $this->recordEvent([
                        'module' => 'Inventory',
                        'event_type' => 'Deleted',
                        'business_id' => $businessId,
                        'record_name_snap' => $inventory['asset_name'],
                        'category_snap' => $inventory['category'],
                        'event_date' => $this->today(),
                        'quantity_delta' => -(int) $inventory['quantity'],
                        'performed_by' => $actor,
                    ]);
                    $delete = $this->pdo->prepare(
                        'DELETE FROM inventory WHERE id = :id'
                    );
                    $delete->execute(['id' => $inventory['id']]);
                    return ['inventory_id' => $businessId, 'deleted' => true];
                }
            );
        } catch (PDOException $error) {
            if ($error->getCode() === '23503') {
                throw new PropertyCoreDomainException(
                    'INVENTORY_LINKED_TO_ASSETS',
                    'Inventory used by registered Assets cannot be deleted.',
                    409
                );
            }
            throw $error;
        }
    }

    public function listAssets(array $filters): array
    {
        [$page, $perPage] = $this->paginationInput($filters);
        $search = $this->search($filters['search'] ?? '');
        $category = trim((string) ($filters['category'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $location = trim((string) ($filters['location'] ?? ''));
        $lifecycle = trim((string) ($filters['lifecycle'] ?? ''));
        $this->validateAssetStatus($status);
        $this->validateAssetLifecycle($lifecycle);
        $where = [];
        $params = ['analysis_date' => $this->today()];

        if ($search !== '') {
            $where[] = "lower(
                coalesce(asset_id, '') || ' ' ||
                coalesce(asset_name, '') || ' ' ||
                coalesce(brand, '') || ' ' ||
                coalesce(model, '') || ' ' ||
                coalesce(serial_number, '') || ' ' ||
                coalesce(custodian, '') || ' ' ||
                coalesce(employee_id, '') || ' ' ||
                coalesce(department, '') || ' ' ||
                coalesce(supplier, '')
            ) LIKE :search";
            $params['search'] = '%' . $this->lower($search) . '%';
        }
        foreach (
            [
                'category' => $category,
                'status' => $status,
                'location' => $location,
            ] as $column => $value
        ) {
            if ($value !== '') {
                $where[] = $column . ' = :' . $column;
                $params[$column] = $value;
            }
        }

        if ($lifecycle !== '') {
            $where[] = 'lifecycle = :lifecycle';
            $params['lifecycle'] = $lifecycle;
        }

        return $this->pagedList(
            $this->assetLifecycleReadModel(),
            self::ASSET_LIST_COLUMNS,
            $where,
            $params,
            $page,
            $perPage,
            [
                'search' => $search,
                'category' => $category,
                'status' => $status,
                'location' => $location,
                'lifecycle' => $lifecycle,
            ],
            [$this, 'normalizeAssetLifecycleRecord']
        );
    }

    public function findAsset(string $businessId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT a.asset_id, a.asset_name, a.category, a.brand, a.model,
                a.serial_number, a.acquisition_date, a.purchase_cost,
                a.supplier, a.location, a.remarks, a.status, a.custodian,
                a.employee_id, a.department, a.date_assigned,
                a.created_at, a.updated_at,
                i.inventory_id AS source_inventory_id
             FROM assets a
             JOIN inventory i ON i.id = a.inventory_id
             WHERE a.asset_id = :asset_id"
        );
        $statement->execute(['asset_id' => $businessId]);
        $record = $statement->fetch();

        if (!$record) {
            throw new PropertyCoreDomainException(
                'ASSET_NOT_FOUND',
                'The requested Asset record was not found.',
                404
            );
        }
        $record['purchase_cost'] = $record['purchase_cost'] === null
            ? null
            : (float) $record['purchase_cost'];
        return $this->enrichAssetLifecycle($record);
    }

    public function assetSummary(): array
    {
        $counts = $this->pdo->query(
            "SELECT COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'Available') AS available,
                COUNT(*) FILTER (WHERE status = 'Assigned') AS assigned,
                COUNT(*) FILTER (
                    WHERE status = 'Under Maintenance'
                ) AS under_maintenance,
                COUNT(*) FILTER (WHERE status = 'Lost') AS lost
             FROM assets"
        )->fetch();
        $rows = $this->pdo->query(
            "SELECT category, COUNT(*) AS record_count
             FROM assets GROUP BY category ORDER BY category LIMIT 100"
        )->fetchAll();
        $byCategory = [];
        foreach ($rows as $row) {
            $byCategory[(string) $row['category']] =
                (int) $row['record_count'];
        }

        return [
            'total' => (int) ($counts['total'] ?? 0),
            'available' => (int) ($counts['available'] ?? 0),
            'assigned' => (int) ($counts['assigned'] ?? 0),
            'under_maintenance' =>
                (int) ($counts['under_maintenance'] ?? 0),
            'lost' => (int) ($counts['lost'] ?? 0),
            'by_category' => $byCategory,
        ];
    }

    public function assetOptions(array $filters): array
    {
        $search = $this->search($filters['search'] ?? '');
        $limit = $this->limit($filters['limit'] ?? 10, 20);
        $status = trim((string) ($filters['status'] ?? ''));
        $this->validateAssetStatus($status);

        if ($this->length($search) < 2) {
            return $this->optionEnvelope([], $search, $limit);
        }

        $statusSql = $status === '' ? '' : ' AND status = :status';
        $statement = $this->pdo->prepare(
            "SELECT id AS asset_row_id, asset_id, asset_name, category,
                serial_number, location, custodian, status
             FROM assets
             WHERE lower(
                coalesce(asset_id, '') || ' ' ||
                coalesce(asset_name, '') || ' ' ||
                coalesce(serial_number, '') || ' ' ||
                coalesce(custodian, '')
             ) LIKE :search" . $statusSql . "
             ORDER BY lower(asset_name), asset_id LIMIT :limit"
        );
        $statement->bindValue(
            ':search',
            '%' . $this->lower($search) . '%',
            PDO::PARAM_STR
        );
        if ($status !== '') {
            $statement->bindValue(':status', $status, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        $options = $statement->fetchAll();
        foreach ($options as &$option) {
            $option['asset_row_id'] = (int) $option['asset_row_id'];
        }
        unset($option);
        return $this->optionEnvelope($options, $search, $limit);
    }

    public function assetFilters(): array
    {
        return [
            'categories' => $this->distinctAssetValues('category'),
            'locations' => $this->distinctAssetValues('location'),
            'statuses' => self::ASSET_STATUSES,
            'lifecycles' => AssetLifecycleCalculator::LIFECYCLES,
        ];
    }

    public function lifecycleConfiguration(): array
    {
        $settings = $this->pdo->query(
            "SELECT aging_threshold_percent, updated_by, updated_at
             FROM asset_lifecycle_settings WHERE id = 1"
        )->fetch();
        $categories = $this->pdo->query(
            "SELECT category, useful_life_months, remarks,
                    updated_by, updated_at
             FROM asset_category_useful_life
             ORDER BY lower(category), id"
        )->fetchAll();
        $events = $this->pdo->query(
            "SELECT setting_type, category, old_value, new_value,
                    changed_by, description, created_at
             FROM asset_lifecycle_config_events
             ORDER BY created_at DESC, id DESC
             LIMIT 50"
        )->fetchAll();

        foreach ($categories as &$category) {
            $category['useful_life_months'] =
                (int) $category['useful_life_months'];
        }
        unset($category);

        return [
            'aging_threshold_percent' =>
                (int) ($settings['aging_threshold_percent'] ?? 80),
            'updated_by' => $settings['updated_by'] ?? null,
            'updated_at' => $settings['updated_at'] ?? null,
            'categories' => $categories,
            'history' => $events,
        ];
    }

    public function updateLifecycleSettings(array $input, string $actor): array
    {
        $this->assertWritesAllowed();
        $threshold = filter_var(
            $input['aging_threshold_percent'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 99]]
        );

        if ($threshold === false) {
            throw new PropertyCoreDomainException(
                'INVALID_AGING_THRESHOLD',
                'The Aging threshold must be between 1 and 99 percent.',
                422
            );
        }

        return $this->transaction(function () use ($threshold, $actor): array {
            $lock = $this->pdo->query(
                "SELECT aging_threshold_percent
                 FROM asset_lifecycle_settings
                 WHERE id = 1 FOR UPDATE"
            );
            $oldThreshold = (int) $lock->fetchColumn();

            $update = $this->pdo->prepare(
                "UPDATE asset_lifecycle_settings
                 SET aging_threshold_percent = :threshold,
                     updated_by = :updated_by,
                     updated_at = now()
                 WHERE id = 1"
            );
            $update->execute([
                'threshold' => $threshold,
                'updated_by' => $actor,
            ]);

            if ($oldThreshold !== (int) $threshold) {
                $this->recordLifecycleConfigEvent([
                    'setting_type' => 'AGING_THRESHOLD',
                    'old_value' => (string) $oldThreshold,
                    'new_value' => (string) $threshold,
                    'changed_by' => $actor,
                    'description' => 'Aging warning threshold updated.',
                ]);
            }

            return $this->lifecycleConfiguration();
        });
    }

    public function saveCategoryUsefulLife(array $input, string $actor): array
    {
        $this->assertWritesAllowed();
        $category = trim((string) ($input['category'] ?? ''));
        $months = filter_var(
            $input['useful_life_months'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 1200]]
        );
        $remarks = trim((string) ($input['remarks'] ?? ''));

        if ($category === '' || $this->length($category) > 100) {
            throw new PropertyCoreDomainException(
                'INVALID_LIFECYCLE_CATEGORY',
                'A category of up to 100 characters is required.',
                422
            );
        }
        if ($months === false) {
            throw new PropertyCoreDomainException(
                'INVALID_USEFUL_LIFE',
                'Expected useful life must be between 1 and 1200 months.',
                422
            );
        }
        if ($this->length($remarks) > 1000) {
            throw new PropertyCoreDomainException(
                'INVALID_LIFECYCLE_REMARKS',
                'Remarks must not exceed 1000 characters.',
                422
            );
        }

        return $this->transaction(
            function () use ($category, $months, $remarks, $actor): array {
                $lookup = $this->pdo->prepare(
                    "SELECT id, useful_life_months
                     FROM asset_category_useful_life
                     WHERE lower(category) = lower(:category)
                     FOR UPDATE"
                );
                $lookup->execute(['category' => $category]);
                $existing = $lookup->fetch();

                if ($existing) {
                    $statement = $this->pdo->prepare(
                        "UPDATE asset_category_useful_life
                         SET category = :category,
                             useful_life_months = :months,
                             remarks = :remarks,
                             updated_by = :updated_by,
                             updated_at = now()
                         WHERE id = :id"
                    );
                    $statement->execute([
                        'category' => $category,
                        'months' => $months,
                        'remarks' => $remarks === '' ? null : $remarks,
                        'updated_by' => $actor,
                        'id' => $existing['id'],
                    ]);
                    $oldValue = (string) $existing['useful_life_months'];
                } else {
                    $statement = $this->pdo->prepare(
                        "INSERT INTO asset_category_useful_life (
                            category, useful_life_months, remarks,
                            updated_by, updated_at
                         ) VALUES (
                            :category, :months, :remarks,
                            :updated_by, now()
                         )"
                    );
                    $statement->execute([
                        'category' => $category,
                        'months' => $months,
                        'remarks' => $remarks === '' ? null : $remarks,
                        'updated_by' => $actor,
                    ]);
                    $oldValue = null;
                }

                if ($oldValue !== (string) $months) {
                    $this->recordLifecycleConfigEvent([
                        'setting_type' => 'CATEGORY_USEFUL_LIFE',
                        'category' => $category,
                        'old_value' => $oldValue,
                        'new_value' => (string) $months,
                        'changed_by' => $actor,
                        'description' => 'Category expected useful life saved.',
                    ]);
                }

                return $this->lifecycleConfiguration();
            }
        );
    }

    public function assetSuggestions(array $filters): array
    {
        $field = trim((string) ($filters['field'] ?? ''));
        $search = $this->search($filters['search'] ?? '');
        $limit = $this->limit($filters['limit'] ?? 10, 10);

        if (!in_array($field, ['brand', 'model', 'supplier'], true)) {
            throw new PropertyCoreDomainException(
                'INVALID_SUGGESTION_FIELD',
                'The requested Asset suggestion field is invalid.',
                400
            );
        }
        if ($this->length($search) < 2) {
            return [
                'suggestions' => [],
                'field' => $field,
                'search' => $search,
                'minimum_search_length' => 2,
            ];
        }

        $statement = $this->pdo->prepare(
            "SELECT DISTINCT trim({$field}) AS value
             FROM assets
             WHERE {$field} IS NOT NULL AND trim({$field}) <> ''
               AND lower({$field}) LIKE :search
             ORDER BY value LIMIT :limit"
        );
        $statement->bindValue(
            ':search',
            $this->lower($search) . '%',
            PDO::PARAM_STR
        );
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->execute();
        return [
            'suggestions' => array_map(
                static fn (array $row): string => (string) $row['value'],
                $statement->fetchAll()
            ),
            'field' => $field,
            'search' => $search,
            'minimum_search_length' => 2,
        ];
    }

    public function nextAssetBusinessId(): string
    {
        $this->assertWritesAllowed();
        return $this->nextBusinessId('asset_id_seq', 'AST');
    }

    public function registerAsset(array $input, string $actor): array
    {
        $this->assertWritesAllowed();
        $record = PropertyCoreLifecycleRules::assetRegistrationInput($input);

        try {
            return $this->transaction(function () use ($record, $actor): array {
                $inventoryLock = $this->pdo->prepare(
                    "SELECT id, inventory_id, asset_name, category, quantity
                     FROM inventory
                     WHERE inventory_id = :inventory_id
                     FOR UPDATE"
                );
                $inventoryLock->execute([
                    'inventory_id' => $record['inventory_id'],
                ]);
                $inventory = $inventoryLock->fetch();

                if (!$inventory) {
                    throw new PropertyCoreDomainException(
                        'INVENTORY_NOT_FOUND',
                        'The selected Inventory record was not found.',
                        404
                    );
                }
                if ((int) $inventory['quantity'] <= 0) {
                    throw new PropertyCoreDomainException(
                        'INSUFFICIENT_INVENTORY_QUANTITY',
                        'The selected Inventory item has no available quantity.',
                        409
                    );
                }

                $insert = $this->pdo->prepare(
                    "INSERT INTO assets (
                        asset_id, asset_name, category, brand, model,
                        serial_number, acquisition_date, purchase_cost,
                        supplier, location, remarks, inventory_id, status
                     ) VALUES (
                        :asset_id, :asset_name, :category, :brand, :model,
                        :serial_number, :acquisition_date, :purchase_cost,
                        :supplier, :location, :remarks, :inventory_row_id,
                        'Available'
                     )"
                );
                $insert->execute([
                    'asset_id' => $record['asset_id'],
                    'asset_name' => $inventory['asset_name'],
                    'category' => $inventory['category'],
                    'brand' => $record['brand'],
                    'model' => $record['model'],
                    'serial_number' => $record['serial_number'],
                    'acquisition_date' => $record['acquisition_date'],
                    'purchase_cost' => $record['purchase_cost'],
                    'supplier' => $record['supplier'],
                    'location' => $record['location'],
                    'remarks' => $record['remarks'],
                    'inventory_row_id' => $inventory['id'],
                ]);

                $decrement = $this->pdo->prepare(
                    "UPDATE inventory
                     SET quantity = quantity - 1, updated_at = now()
                     WHERE id = :id AND quantity > 0"
                );
                $decrement->execute(['id' => $inventory['id']]);

                if ($decrement->rowCount() !== 1) {
                    throw new PropertyCoreDomainException(
                        'INSUFFICIENT_INVENTORY_QUANTITY',
                        'The selected Inventory item has no available quantity.',
                        409
                    );
                }

                $date = $this->today();
                $this->recordEvent([
                    'module' => 'Asset Registry',
                    'event_type' => 'Registered',
                    'business_id' => $record['asset_id'],
                    'related_business_id' => $inventory['inventory_id'],
                    'record_name_snap' => $inventory['asset_name'],
                    'category_snap' => $inventory['category'],
                    'event_date' => $date,
                    'to_status' => 'Available',
                    'performed_by' => $actor,
                ]);
                $this->recordEvent([
                    'module' => 'Inventory',
                    'event_type' => 'Stock Decreased',
                    'business_id' => $inventory['inventory_id'],
                    'related_business_id' => $record['asset_id'],
                    'record_name_snap' => $inventory['asset_name'],
                    'category_snap' => $inventory['category'],
                    'event_date' => $date,
                    'quantity_delta' => -1,
                    'performed_by' => $actor,
                    'description' =>
                        'Inventory unit registered as an individual Asset.',
                ]);
                return $this->findAsset($record['asset_id']);
            });
        } catch (PDOException $error) {
            if ($error->getCode() === '23505') {
                throw new PropertyCoreDomainException(
                    'DUPLICATE_ASSET_ID',
                    'The Asset ID has already been used.',
                    409
                );
            }
            if ($error->getCode() === '23514') {
                throw new PropertyCoreDomainException(
                    'INSUFFICIENT_INVENTORY_QUANTITY',
                    'Inventory quantity cannot be reduced below zero.',
                    409
                );
            }
            throw $error;
        }
    }

    public function updateAsset(
        string $businessId,
        array $input,
        string $actor
    ): array {
        $this->assertWritesAllowed();
        PropertyCoreLifecycleRules::businessId(
            $businessId,
            'AST',
            'INVALID_ASSET_ID'
        );
        $record = PropertyCoreLifecycleRules::assetUpdateInput($input);

        return $this->transaction(function () use (
            $businessId,
            $record,
            $actor
        ): array {
            $lock = $this->pdo->prepare(
                "SELECT id, asset_name, category, brand, model,
                        serial_number, supplier, location, remarks, status
                 FROM assets
                 WHERE asset_id = :asset_id
                 FOR UPDATE"
            );
            $lock->execute(['asset_id' => $businessId]);
            $asset = $lock->fetch();

            if (!$asset) {
                throw new PropertyCoreDomainException(
                    'ASSET_NOT_FOUND',
                    'The requested Asset record was not found.',
                    404
                );
            }

            $assetChanged = false;

            foreach (array_keys($record) as $field) {
                if (
                    (string) ($asset[$field] ?? '') !==
                    (string) ($record[$field] ?? '')
                ) {
                    $assetChanged = true;
                    break;
                }
            }

            $update = $this->pdo->prepare(
                "UPDATE assets SET
                    asset_name = :asset_name,
                    category = :category,
                    brand = :brand,
                    model = :model,
                    serial_number = :serial_number,
                    supplier = :supplier,
                    location = :location,
                    remarks = :remarks,
                    updated_at = now()
                 WHERE id = :id"
            );
            $update->execute($record + ['id' => $asset['id']]);

            if ($assetChanged) {
                $this->recordEvent([
                    'module' => 'Asset Registry',
                    'event_type' => 'Updated',
                    'business_id' => $businessId,
                    'record_name_snap' => $record['asset_name'],
                    'category_snap' => $record['category'],
                    'event_date' => $this->today(),
                    'performed_by' => $actor,
                    'description' => 'Asset details updated.',
                ]);
            }

            return $this->findAsset($businessId);
        });
    }

    public function assignAsset(
        string $businessId,
        array $input,
        string $actor
    ): array {
        $this->assertWritesAllowed();
        PropertyCoreLifecycleRules::businessId(
            $businessId,
            'AST',
            'INVALID_ASSET_ID'
        );
        $record = PropertyCoreLifecycleRules::assignmentInput($input);

        return $this->transaction(
            function () use ($businessId, $record, $actor): array {
                $asset = $this->lockAsset($businessId);
                PropertyCoreLifecycleRules::assertAssignable(
                    $asset,
                    $this->hasActiveMaintenance((int) $asset['id'])
                );
                $update = $this->pdo->prepare(
                    "UPDATE assets SET
                        employee_id = :employee_id,
                        custodian = :custodian,
                        department = :department,
                        date_assigned = :date_assigned,
                        status = 'Assigned',
                        updated_at = now()
                     WHERE id = :id"
                );
                $update->execute($record + ['id' => $asset['id']]);
                $this->recordEvent([
                    'module' => 'Asset Registry',
                    'event_type' => 'Assigned',
                    'business_id' => $businessId,
                    'related_business_id' => $record['employee_id'],
                    'record_name_snap' => $asset['asset_name'],
                    'category_snap' => $asset['category'],
                    'event_date' => $record['date_assigned'],
                    'from_status' => $asset['status'],
                    'to_status' => 'Assigned',
                    'performed_by' => $actor,
                    'description' => 'Assigned to ' . $record['custodian'] .
                        ' (' . $record['department'] . ').',
                ]);
                return $this->findAsset($businessId);
            }
        );
    }

    public function returnAsset(string $businessId, string $actor): array
    {
        $this->assertWritesAllowed();
        PropertyCoreLifecycleRules::businessId(
            $businessId,
            'AST',
            'INVALID_ASSET_ID'
        );

        return $this->transaction(function () use ($businessId, $actor): array {
            $asset = $this->lockAsset($businessId);
            PropertyCoreLifecycleRules::assertReturnable(
                $asset,
                $this->hasActiveMaintenance((int) $asset['id'])
            );
            $update = $this->pdo->prepare(
                "UPDATE assets SET
                    employee_id = NULL,
                    custodian = NULL,
                    department = NULL,
                    date_assigned = NULL,
                    status = 'Available',
                    updated_at = now()
                 WHERE id = :id"
            );
            $update->execute(['id' => $asset['id']]);
            $this->recordEvent([
                'module' => 'Asset Registry',
                'event_type' => 'Returned',
                'business_id' => $businessId,
                'related_business_id' => $asset['employee_id'] ?? null,
                'record_name_snap' => $asset['asset_name'],
                'category_snap' => $asset['category'],
                'event_date' => $this->today(),
                'from_status' => $asset['status'],
                'to_status' => 'Available',
                'performed_by' => $actor,
                'description' => !empty($asset['custodian'])
                    ? 'Returned by ' . $asset['custodian'] . '.'
                    : 'Asset returned to available custody.',
            ]);
            return $this->findAsset($businessId);
        });
    }

    public function deleteAsset(string $businessId, string $actor): array
    {
        $this->assertWritesAllowed();
        PropertyCoreLifecycleRules::businessId(
            $businessId,
            'AST',
            'INVALID_ASSET_ID'
        );

        try {
            return $this->transaction(
                function () use ($businessId, $actor): array {
                    $relationship = $this->pdo->prepare(
                        'SELECT inventory_id FROM assets WHERE asset_id = :id'
                    );
                    $relationship->execute(['id' => $businessId]);
                    $inventoryRowId = $relationship->fetchColumn();

                    if ($inventoryRowId === false) {
                        throw new PropertyCoreDomainException(
                            'ASSET_NOT_FOUND',
                            'The requested Asset record was not found.',
                            404
                        );
                    }

                    // Canonical two-row lock order: Inventory, then Asset.
                    $inventoryLock = $this->pdo->prepare(
                        "SELECT id, inventory_id, asset_name, category
                         FROM inventory WHERE id = :id FOR UPDATE"
                    );
                    $inventoryLock->execute(['id' => $inventoryRowId]);
                    $inventory = $inventoryLock->fetch();

                    if (!$inventory) {
                        throw new RuntimeException(
                            'ASSET_INVENTORY_LINK_MISSING'
                        );
                    }

                    $asset = $this->lockAsset($businessId);

                    if ((int) $asset['inventory_id'] !== (int) $inventory['id']) {
                        throw new PropertyCoreDomainException(
                            'ASSET_STATE_CHANGED',
                            'The Asset relationship changed; retry the operation.',
                            409
                        );
                    }

                    $activeMaintenance = $this->hasActiveMaintenance(
                        (int) $asset['id']
                    );
                    $history = $this->pdo->prepare(
                        "SELECT EXISTS(
                            SELECT 1 FROM maintenance WHERE asset_id = :mid
                         ) OR EXISTS(
                            SELECT 1 FROM audits WHERE asset_id = :aid
                         ) AS has_history"
                    );
                    $history->execute([
                        'mid' => $asset['id'],
                        'aid' => $asset['id'],
                    ]);
                    $hasHistory = filter_var(
                        $history->fetchColumn(),
                        FILTER_VALIDATE_BOOLEAN
                    );
                    PropertyCoreLifecycleRules::assertDeletable(
                        $asset,
                        $activeMaintenance,
                        $hasHistory
                    );

                    $date = $this->today();
                    $this->recordEvent([
                        'module' => 'Asset Registry',
                        'event_type' => 'Deleted',
                        'business_id' => $businessId,
                        'related_business_id' => $inventory['inventory_id'],
                        'record_name_snap' => $asset['asset_name'],
                        'category_snap' => $asset['category'],
                        'event_date' => $date,
                        'from_status' => $asset['status'],
                        'performed_by' => $actor,
                    ]);
                    $this->recordEvent([
                        'module' => 'Inventory',
                        'event_type' => 'Stock Increased',
                        'business_id' => $inventory['inventory_id'],
                        'related_business_id' => $businessId,
                        'record_name_snap' => $inventory['asset_name'],
                        'category_snap' => $inventory['category'],
                        'event_date' => $date,
                        'quantity_delta' => 1,
                        'performed_by' => $actor,
                        'description' =>
                            'Deleted Asset restored one Inventory unit.',
                    ]);
                    $delete = $this->pdo->prepare(
                        'DELETE FROM assets WHERE id = :id'
                    );
                    $delete->execute(['id' => $asset['id']]);
                    $restore = $this->pdo->prepare(
                        "UPDATE inventory
                         SET quantity = quantity + 1, updated_at = now()
                         WHERE id = :id"
                    );
                    $restore->execute(['id' => $inventory['id']]);
                    return ['asset_id' => $businessId, 'deleted' => true];
                }
            );
        } catch (PDOException $error) {
            if ($error->getCode() === '23503') {
                throw new PropertyCoreDomainException(
                    'ASSET_HISTORY_DELETE_FORBIDDEN',
                    'An Asset with Maintenance or Audit history cannot be deleted.',
                    409
                );
            }
            throw $error;
        }
    }

    private function pagedList(
        string $table,
        string $columns,
        array $where,
        array $params,
        int $page,
        int $perPage,
        array $filters,
        ?callable $normalizer = null
    ): array {
        $whereSql = $where === []
            ? ''
            : ' WHERE ' . implode(' AND ', $where);
        $count = $this->pdo->prepare(
            'SELECT COUNT(*) FROM ' . $table . $whereSql
        );
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $statement = $this->pdo->prepare(
            'SELECT ' . $columns . ' FROM ' . $table . $whereSql .
            ' ORDER BY id LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $name => $value) {
            $statement->bindValue(':' . $name, $value, PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        $records = $statement->fetchAll();
        if ($normalizer !== null) {
            $records = array_map($normalizer, $records);
        }
        return [
            'records' => $records,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'filters' => $filters,
        ];
    }

    private function distinctAssetValues(string $column): array
    {
        if (!in_array($column, ['category', 'location'], true)) {
            throw new LogicException('Unsupported Asset filter column.');
        }
        $statement = $this->pdo->query(
            "SELECT DISTINCT trim({$column}) AS value
             FROM assets
             WHERE {$column} IS NOT NULL AND trim({$column}) <> ''
             ORDER BY value LIMIT 100"
        );
        return array_map(
            static fn (array $row): string => (string) $row['value'],
            $statement->fetchAll()
        );
    }

    private function paginationInput(array $filters): array
    {
        return [
            max(1, (int) ($filters['page'] ?? 1)),
            $this->limit($filters['per_page'] ?? 25, 50),
        ];
    }

    private function optionEnvelope(
        array $options,
        string $search,
        int $limit
    ): array {
        return [
            'options' => $options,
            'search' => $search,
            'limit' => $limit,
            'minimum_search_length' => 2,
        ];
    }

    private function validateAssetStatus(string $status): void
    {
        if ($status !== '' && !in_array($status, self::ASSET_STATUSES, true)) {
            throw new PropertyCoreDomainException(
                'INVALID_ASSET_STATUS_FILTER',
                'The requested Asset status filter is invalid.',
                400
            );
        }
    }

    private function validateAssetLifecycle(string $lifecycle): void
    {
        if (
            $lifecycle !== '' &&
            !in_array($lifecycle, AssetLifecycleCalculator::LIFECYCLES, true)
        ) {
            throw new PropertyCoreDomainException(
                'INVALID_ASSET_LIFECYCLE_FILTER',
                'The requested Asset lifecycle filter is invalid.',
                400
            );
        }
    }

    private function assetLifecycleReadModel(): string
    {
        return "(
            SELECT age_model.*,
                CASE
                    WHEN age_model.acquisition_date IS NULL
                        THEN 'Age Not Recorded'
                    WHEN age_model.useful_life_months IS NULL
                        THEN 'Useful Life Not Configured'
                    WHEN age_model.asset_age_months >=
                         age_model.useful_life_months
                        THEN 'Retirement Review'
                    WHEN age_model.asset_age_months * 100 >=
                         age_model.useful_life_months *
                         age_model.aging_threshold_percent
                        THEN 'Aging'
                    ELSE 'Active'
                END AS lifecycle
            FROM (
                SELECT a.id, a.asset_id, a.asset_name, a.category,
                    a.brand, a.model, a.serial_number,
                    a.acquisition_date, a.supplier, a.location,
                    a.custodian, a.employee_id, a.department, a.status,
                    policy.useful_life_months,
                    settings.aging_threshold_percent,
                    CASE
                        WHEN a.acquisition_date IS NULL THEN NULL
                        ELSE GREATEST(
                            0,
                            (
                                EXTRACT(YEAR FROM age(
                                    context.analysis_date,
                                    a.acquisition_date
                                )) * 12 +
                                EXTRACT(MONTH FROM age(
                                    context.analysis_date,
                                    a.acquisition_date
                                ))
                            )::integer
                        )
                    END AS asset_age_months
                FROM assets a
                CROSS JOIN asset_lifecycle_settings settings
                CROSS JOIN (
                    SELECT CAST(:analysis_date AS date) AS analysis_date
                ) context
                LEFT JOIN asset_category_useful_life policy
                    ON lower(policy.category) = lower(a.category)
                WHERE settings.id = 1
            ) age_model
        ) asset_lifecycle";
    }

    private function normalizeAssetLifecycleRecord(array $record): array
    {
        foreach (
            [
                'asset_age_months',
                'useful_life_months',
                'aging_threshold_percent',
            ] as $field
        ) {
            $record[$field] = $record[$field] === null
                ? null
                : (int) $record[$field];
        }
        $record['asset_age'] = AssetLifecycleCalculator::durationLabel(
            $record['asset_age_months']
        );
        $record['useful_life'] = AssetLifecycleCalculator::durationLabel(
            $record['useful_life_months']
        );
        return $record;
    }

    private function enrichAssetLifecycle(array $record): array
    {
        $statement = $this->pdo->prepare(
            "SELECT useful_life_months
             FROM asset_category_useful_life
             WHERE lower(category) = lower(:category)
             LIMIT 1"
        );
        $statement->execute(['category' => $record['category']]);
        $usefulLife = $statement->fetchColumn();
        $threshold = (int) $this->pdo->query(
            "SELECT aging_threshold_percent
             FROM asset_lifecycle_settings WHERE id = 1"
        )->fetchColumn();
        $ageMonths = AssetLifecycleCalculator::ageInMonths(
            $record['acquisition_date'] ?? null
        );

        $record['asset_age_months'] = $ageMonths;
        $record['asset_age'] = AssetLifecycleCalculator::durationLabel(
            $ageMonths
        );
        $record['useful_life_months'] = $usefulLife === false
            ? null
            : (int) $usefulLife;
        $record['useful_life'] = AssetLifecycleCalculator::durationLabel(
            $record['useful_life_months']
        );
        $record['aging_threshold_percent'] = $threshold;
        $record['lifecycle'] = AssetLifecycleCalculator::classify(
            $ageMonths,
            $record['useful_life_months'],
            $threshold
        );

        return $record;
    }

    private function recordLifecycleConfigEvent(array $event): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO asset_lifecycle_config_events (
                setting_type, category, old_value, new_value,
                changed_by, description
             ) VALUES (
                :setting_type, :category, :old_value, :new_value,
                :changed_by, :description
             )"
        );
        $statement->execute([
            'setting_type' => $event['setting_type'],
            'category' => $event['category'] ?? null,
            'old_value' => $event['old_value'] ?? null,
            'new_value' => $event['new_value'],
            'changed_by' => $event['changed_by'],
            'description' => $event['description'] ?? null,
        ]);
    }

    private function search(mixed $value): string
    {
        $value = trim((string) $value);
        if ($this->length($value) > 200) {
            throw new PropertyCoreDomainException(
                'SEARCH_TOO_LONG',
                'Search text must not exceed 200 characters.',
                400
            );
        }
        return $value;
    }

    private function limit(mixed $value, int $maximum): int
    {
        return max(1, min($maximum, (int) $value));
    }

    private function lower(string $value): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }

    private function normalizeInventory(array $record): array
    {
        $record['quantity'] = (int) $record['quantity'];
        return $record;
    }

    private function lockAsset(string $businessId): array
    {
        $statement = $this->pdo->prepare(
            "SELECT id, asset_id, inventory_id, asset_name, category,
                    status, custodian, employee_id, department, date_assigned
             FROM assets
             WHERE asset_id = :asset_id
             FOR UPDATE"
        );
        $statement->execute(['asset_id' => $businessId]);
        $asset = $statement->fetch();

        if (!$asset) {
            throw new PropertyCoreDomainException(
                'ASSET_NOT_FOUND',
                'The requested Asset record was not found.',
                404
            );
        }

        return $asset;
    }

    private function hasActiveMaintenance(int $assetRowId): bool
    {
        $statement = $this->pdo->prepare(
            "SELECT 1 FROM maintenance
             WHERE asset_id = :asset_id
               AND status <> 'Completed'
             LIMIT 1"
        );
        $statement->execute(['asset_id' => $assetRowId]);
        return (bool) $statement->fetch();
    }

    private function nextBusinessId(string $sequence, string $prefix): string
    {
        if (!in_array(
            $sequence,
            ['inventory_id_seq', 'asset_id_seq'],
            true
        )) {
            throw new LogicException('Unsupported business-ID sequence.');
        }

        $number = $this->pdo->query(
            "SELECT nextval('{$sequence}')"
        )->fetchColumn();

        if ($number === false) {
            throw new RuntimeException('BUSINESS_ID_GENERATION_FAILED');
        }

        return $prefix . '-' . str_pad(
            (string) ((int) $number),
            6,
            '0',
            STR_PAD_LEFT
        );
    }

    private function recordEvent(array $event): void
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO property_events (
                module, event_type, business_id, related_business_id,
                record_name_snap, category_snap, event_date, quantity_delta,
                from_status, to_status, outcome, performed_by, description
             ) VALUES (
                :module, :event_type, :business_id, :related_business_id,
                :record_name_snap, :category_snap, :event_date,
                :quantity_delta, :from_status, :to_status, :outcome,
                :performed_by, :description
             )"
        );
        $statement->execute([
            'module' => $event['module'],
            'event_type' => $event['event_type'],
            'business_id' => $event['business_id'],
            'related_business_id' =>
                $event['related_business_id'] ?? null,
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

    private function transaction(callable $operation): mixed
    {
        return $this->transactionRunner->run($operation);
    }

    private function today(): string
    {
        return (new DateTimeImmutable(
            'now',
            new DateTimeZone('Asia/Manila')
        ))->format('Y-m-d');
    }

    private function assertWritesAllowed(): void
    {
        if (!$this->writesAllowed) {
            throw new PropertyCoreDomainException(
                'PROPERTY_CORE_READ_ONLY_GATE',
                'Property Core lifecycle changes are not enabled in this environment.',
                503
            );
        }
    }
}
