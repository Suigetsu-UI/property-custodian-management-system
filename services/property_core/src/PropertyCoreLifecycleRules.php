<?php

require_once __DIR__ . '/PropertyCoreDomainException.php';

final class PropertyCoreLifecycleRules
{
    public static function inventoryInput(
        array $input,
        bool $creating
    ): array {
        $quantity = filter_var(
            $input['quantity'] ?? null,
            FILTER_VALIDATE_INT
        );
        $record = [
            'asset_name' => self::requiredString(
                $input,
                'asset_name',
                150
            ),
            'category' => self::requiredString(
                $input,
                'category',
                100
            ),
            'condition' => self::requiredString(
                $input,
                'condition',
                50
            ),
            'quantity' => $quantity === false ? -1 : (int) $quantity,
        ];

        if (
            ($creating && $record['quantity'] <= 0) ||
            (!$creating && $record['quantity'] < 0)
        ) {
            throw new PropertyCoreDomainException(
                'INVALID_INVENTORY_QUANTITY',
                $creating
                    ? 'New Inventory quantity must be greater than zero.'
                    : 'Inventory quantity cannot be negative.',
                422
            );
        }

        if ($creating) {
            $record['inventory_id'] = self::businessId(
                $input['inventory_id'] ?? '',
                'INV',
                'INVALID_INVENTORY_ID'
            );
        }

        return $record;
    }

    public static function assetRegistrationInput(array $input): array
    {
        $costRaw = trim((string) ($input['purchase_cost'] ?? ''));

        if (
            $costRaw === '' ||
            !is_numeric($costRaw) ||
            (float) $costRaw < 0
        ) {
            throw new PropertyCoreDomainException(
                'INVALID_PURCHASE_COST',
                'Purchase cost must be a number greater than or equal to zero.',
                422
            );
        }

        return [
            'asset_id' => self::businessId(
                $input['asset_id'] ?? '',
                'AST',
                'INVALID_ASSET_ID'
            ),
            'inventory_id' => self::businessId(
                $input['inventory_id'] ?? '',
                'INV',
                'INVALID_INVENTORY_ID'
            ),
            'brand' => self::optionalString($input, 'brand', 100),
            'model' => self::optionalString($input, 'model', 100),
            'serial_number' => self::optionalString(
                $input,
                'serial_number',
                100
            ),
            'acquisition_date' => self::date(
                $input['acquisition_date'] ?? null,
                'ACQUISITION_DATE_REQUIRED',
                'A valid Acquisition Date is required.'
            ),
            'purchase_cost' => (float) $costRaw,
            'supplier' => self::optionalString($input, 'supplier', 150),
            'location' => self::optionalString($input, 'location', 150),
            'remarks' => self::optionalString($input, 'remarks', 5000),
        ];
    }

    public static function assetUpdateInput(array $input): array
    {
        return [
            'asset_name' => self::requiredString(
                $input,
                'asset_name',
                150
            ),
            'category' => self::requiredString(
                $input,
                'category',
                100
            ),
            'brand' => self::optionalString($input, 'brand', 100),
            'model' => self::optionalString($input, 'model', 100),
            'serial_number' => self::optionalString(
                $input,
                'serial_number',
                100
            ),
            'supplier' => self::optionalString($input, 'supplier', 150),
            'location' => self::optionalString($input, 'location', 150),
            'remarks' => self::optionalString($input, 'remarks', 5000),
        ];
    }

    public static function assignmentInput(array $input): array
    {
        return [
            'employee_id' => self::requiredString(
                $input,
                'employee_id',
                50
            ),
            'custodian' => self::requiredString(
                $input,
                'custodian',
                150
            ),
            'department' => self::requiredString(
                $input,
                'department',
                150
            ),
            'date_assigned' => self::date(
                $input['date_assigned'] ?? null,
                'ASSIGNMENT_DATE_REQUIRED',
                'A valid Assignment Date is required.'
            ),
        ];
    }

    public static function assertAssignable(
        array $asset,
        bool $activeMaintenance
    ): void {
        self::assertNotMaintenanceOrLost($asset, $activeMaintenance);

        if (($asset['status'] ?? '') !== 'Available') {
            throw new PropertyCoreDomainException(
                'ASSET_NOT_ASSIGNABLE',
                'Only an Available Asset can be assigned.',
                409
            );
        }
    }

    public static function assertReturnable(
        array $asset,
        bool $activeMaintenance
    ): void {
        self::assertNotMaintenanceOrLost($asset, $activeMaintenance);

        if (($asset['status'] ?? '') !== 'Assigned') {
            throw new PropertyCoreDomainException(
                'ASSET_NOT_RETURNABLE',
                'Only an Assigned Asset can be returned.',
                409
            );
        }
    }

    public static function assertDeletable(
        array $asset,
        bool $activeMaintenance,
        bool $hasHistory
    ): void {
        self::assertNotMaintenanceOrLost($asset, $activeMaintenance);

        if (($asset['status'] ?? '') === 'Assigned') {
            throw new PropertyCoreDomainException(
                'ASSIGNED_ASSET_DELETE_FORBIDDEN',
                'An Assigned Asset must be returned before deletion.',
                409
            );
        }

        if (($asset['status'] ?? '') !== 'Available') {
            throw new PropertyCoreDomainException(
                'ASSET_DELETE_FORBIDDEN',
                'This Asset is not eligible for deletion.',
                409
            );
        }

        if ($hasHistory) {
            throw new PropertyCoreDomainException(
                'ASSET_HISTORY_DELETE_FORBIDDEN',
                'An Asset with Maintenance or Audit history cannot be deleted.',
                409
            );
        }
    }

    public static function businessId(
        mixed $value,
        string $prefix,
        string $code
    ): string {
        $value = trim((string) $value);

        if (preg_match('/^' . preg_quote($prefix, '/') . '-\d{6}$/', $value) !== 1) {
            throw new PropertyCoreDomainException(
                $code,
                "The {$prefix} business ID format is invalid.",
                422
            );
        }

        return $value;
    }

    private static function assertNotMaintenanceOrLost(
        array $asset,
        bool $activeMaintenance
    ): void {
        if (
            ($asset['status'] ?? '') === 'Under Maintenance' ||
            $activeMaintenance
        ) {
            throw new PropertyCoreDomainException(
                'ASSET_UNDER_MAINTENANCE',
                'An Asset under active Maintenance cannot be changed.',
                409
            );
        }

        if (($asset['status'] ?? '') === 'Lost') {
            throw new PropertyCoreDomainException(
                'LOST_ASSET_CHANGE_FORBIDDEN',
                'An Asset marked Lost cannot be changed by this operation.',
                409
            );
        }
    }

    private static function requiredString(
        array $input,
        string $field,
        int $maximum
    ): string {
        $value = trim((string) ($input[$field] ?? ''));

        if ($value === '' || self::length($value) > $maximum) {
            throw new PropertyCoreDomainException(
                'INVALID_PROPERTY_CORE_INPUT',
                'One or more required Property Core fields are invalid.',
                422
            );
        }

        return $value;
    }

    private static function optionalString(
        array $input,
        string $field,
        int $maximum
    ): string {
        $value = trim((string) ($input[$field] ?? ''));

        if (self::length($value) > $maximum) {
            throw new PropertyCoreDomainException(
                'INVALID_PROPERTY_CORE_INPUT',
                'One or more Property Core fields exceed their allowed length.',
                422
            );
        }

        return $value;
    }

    private static function date(
        mixed $value,
        string $code,
        string $message
    ): string {
        $value = trim((string) ($value ?? ''));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$date ||
            ($errors !== false && (
                $errors['warning_count'] > 0 ||
                $errors['error_count'] > 0
            )) ||
            $date->format('Y-m-d') !== $value
        ) {
            throw new PropertyCoreDomainException($code, $message, 422);
        }

        return $value;
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
