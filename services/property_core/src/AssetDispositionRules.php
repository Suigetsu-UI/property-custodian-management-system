<?php

final class AssetDispositionRules
{
    public const STATUSES = [
        'Pending Institutional Approval',
        'Approved for Sale/Bidding',
        'Sold',
        'Rejected',
        'Cancelled',
    ];

    public const METHODS = ['Sale', 'Bidding'];

    public static function requestInput(array $input): array
    {
        return [
            'reason' => self::requiredString($input, 'reason', 3, 2000),
            'proposed_method' => self::allowedValue(
                $input,
                'proposed_method',
                self::METHODS
            ),
            'request_remarks' => self::optionalString(
                $input,
                'request_remarks',
                2000
            ),
        ];
    }

    public static function approvalInput(array $input, string $today): array
    {
        return [
            'institutional_approval_reference' => self::requiredString(
                $input,
                'institutional_approval_reference',
                3,
                150
            ),
            'institutional_approver_name' => self::requiredString(
                $input,
                'institutional_approver_name',
                2,
                150
            ),
            'institutional_approver_position' => self::requiredString(
                $input,
                'institutional_approver_position',
                2,
                150
            ),
            'institutional_approval_date' => self::dateNotAfter(
                $input['institutional_approval_date'] ?? null,
                $today
            ),
            'approval_remarks' => self::optionalString(
                $input,
                'approval_remarks',
                2000
            ),
        ];
    }

    public static function completionInput(array $input): array
    {
        return [
            'completion_reference' => self::requiredString(
                $input,
                'completion_reference',
                3,
                150
            ),
            'completion_remarks' => self::optionalString(
                $input,
                'completion_remarks',
                2000
            ),
        ];
    }

    public static function decisionInput(array $input): array
    {
        return [
            'status_note' => self::requiredString(
                $input,
                'status_note',
                3,
                2000
            ),
        ];
    }

    public static function requestBlockers(
        array $asset,
        bool $hasOpenDisposition
    ): array {
        $blockers = [];

        if (($asset['status'] ?? '') === 'Sold') {
            $blockers[] = 'The Asset is already Sold.';
        }
        if ($hasOpenDisposition) {
            $blockers[] = 'An open disposition request already exists.';
        }

        return $blockers;
    }

    public static function approvalBlockers(
        array $asset,
        bool $hasActiveMaintenance,
        bool $hasOpenAudit,
        ?array $latestCompletedAudit
    ): array {
        $blockers = [];
        $status = (string) ($asset['status'] ?? '');

        if ($status !== 'Available') {
            $blockers[] = match ($status) {
                'Assigned' => 'The Asset must be returned before approval.',
                'Lost' => 'A Lost Asset cannot proceed to sale or bidding.',
                'Sold' => 'The Asset is already Sold.',
                'Under Maintenance' =>
                    'An Asset under Maintenance cannot proceed to approval.',
                default => 'The Asset must be Available before approval.',
            };
        }
        if ($hasActiveMaintenance) {
            $blockers[] = 'Scheduled or In Progress Maintenance must be completed.';
        }
        if ($hasOpenAudit) {
            $blockers[] = 'Scheduled or Ongoing Audit work must be completed.';
        }
        if ($latestCompletedAudit === null) {
            $blockers[] = 'A completed Audit is required as condition evidence.';
        } elseif (($latestCompletedAudit['result'] ?? '') !== 'Verified') {
            $blockers[] = sprintf(
                'The latest completed Audit result is %s, not Verified.',
                (string) ($latestCompletedAudit['result'] ?? 'unknown')
            );
        }

        return array_values(array_unique($blockers));
    }

    public static function assertTransition(
        string $currentStatus,
        string $action
    ): void {
        $allowed = match ($action) {
            'approve', 'reject' => ['Pending Institutional Approval'],
            'complete' => ['Approved for Sale/Bidding'],
            'cancel' => [
                'Pending Institutional Approval',
                'Approved for Sale/Bidding',
            ],
            default => [],
        };

        if (!in_array($currentStatus, $allowed, true)) {
            throw new PropertyCoreDomainException(
                'INVALID_DISPOSITION_TRANSITION',
                'The requested disposition transition is not allowed.',
                409
            );
        }
    }

    public static function assertNoBlockers(array $blockers): void
    {
        if ($blockers !== []) {
            throw new PropertyCoreDomainException(
                'ASSET_DISPOSITION_NOT_ELIGIBLE',
                implode(' ', $blockers),
                409
            );
        }
    }

    private static function requiredString(
        array $input,
        string $field,
        int $minimum,
        int $maximum
    ): string {
        $value = trim((string) ($input[$field] ?? ''));
        $length = function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);

        if ($length < $minimum || $length > $maximum) {
            throw new PropertyCoreDomainException(
                'INVALID_DISPOSITION_INPUT',
                'One or more disposition fields are invalid.',
                422
            );
        }

        return $value;
    }

    private static function optionalString(
        array $input,
        string $field,
        int $maximum
    ): ?string {
        $value = trim((string) ($input[$field] ?? ''));

        if ($value === '') {
            return null;
        }

        return self::requiredString($input, $field, 1, $maximum);
    }

    private static function allowedValue(
        array $input,
        string $field,
        array $allowed
    ): string {
        $value = trim((string) ($input[$field] ?? ''));

        if (!in_array($value, $allowed, true)) {
            throw new PropertyCoreDomainException(
                'INVALID_DISPOSITION_INPUT',
                'One or more disposition fields are invalid.',
                422
            );
        }

        return $value;
    }

    private static function dateNotAfter(mixed $value, string $today): string
    {
        $date = trim((string) $value);
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $todayDate = DateTimeImmutable::createFromFormat('!Y-m-d', $today);

        if (
            !$parsed ||
            $parsed->format('Y-m-d') !== $date ||
            !$todayDate ||
            $parsed > $todayDate
        ) {
            throw new PropertyCoreDomainException(
                'INVALID_DISPOSITION_INPUT',
                'A valid institutional approval date not after today is required.',
                422
            );
        }

        return $date;
    }
}
