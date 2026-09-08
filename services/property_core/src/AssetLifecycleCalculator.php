<?php

final class AssetLifecycleCalculator
{
    public const LIFECYCLES = [
        'Active',
        'Aging',
        'Retirement Review',
        'Useful Life Not Configured',
        'Age Not Recorded',
    ];

    public static function ageInMonths(
        ?string $acquisitionDate,
        ?DateTimeImmutable $asOf = null
    ): ?int {
        $acquisitionDate = trim((string) $acquisitionDate);

        if ($acquisitionDate === '') {
            return null;
        }

        $acquired = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $acquisitionDate,
            new DateTimeZone('Asia/Manila')
        );
        $errors = DateTimeImmutable::getLastErrors();

        if (
            !$acquired ||
            (is_array($errors) && (
                $errors['warning_count'] > 0 ||
                $errors['error_count'] > 0
            ))
        ) {
            return null;
        }

        $asOf ??= new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));

        if ($acquired > $asOf) {
            return 0;
        }

        $difference = $acquired->diff($asOf);
        return ($difference->y * 12) + $difference->m;
    }

    public static function classify(
        ?int $ageMonths,
        ?int $usefulLifeMonths,
        int $agingThresholdPercent
    ): string {
        if ($ageMonths === null) {
            return 'Age Not Recorded';
        }

        if ($usefulLifeMonths === null || $usefulLifeMonths < 1) {
            return 'Useful Life Not Configured';
        }

        $agingThresholdPercent = max(1, min(99, $agingThresholdPercent));

        if ($ageMonths >= $usefulLifeMonths) {
            return 'Retirement Review';
        }

        return ($ageMonths * 100) >=
            ($usefulLifeMonths * $agingThresholdPercent)
                ? 'Aging'
                : 'Active';
    }

    public static function durationLabel(?int $months): string
    {
        if ($months === null) {
            return 'Not recorded';
        }

        $years = intdiv(max(0, $months), 12);
        $remainingMonths = max(0, $months) % 12;

        if ($years === 0) {
            return $remainingMonths . 'm';
        }

        if ($remainingMonths === 0) {
            return $years . 'y';
        }

        return $years . 'y ' . $remainingMonths . 'm';
    }
}
