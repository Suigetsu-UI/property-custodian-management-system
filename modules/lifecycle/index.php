<?php

require_once __DIR__ . '/../../auth/check_auth.php';
require_once __DIR__ . '/../../includes/property_core_gateway.php';

requireAdministrator();

$configuration = [
    'aging_threshold_percent' => 80,
    'categories' => [],
    'history' => [],
];
$assetCategories = [];
$serviceAvailable = true;

try {
    $client = getPropertyCoreServiceClient();
    $configuration = $client->lifecycleConfiguration();
    $assetCategories = $client->assetFilters()['categories'] ?? [];
} catch (Throwable $error) {
    $serviceAvailable = false;
}

$configuredCategories = is_array($configuration['categories'] ?? null)
    ? $configuration['categories']
    : [];
$categoryNames = array_values(array_unique(array_filter(array_merge(
    $assetCategories,
    array_map(
        static fn (array $row): string => trim((string) ($row['category'] ?? '')),
        $configuredCategories
    )
))));
natcasesort($categoryNames);

$messages = [
    'threshold_saved' => ['success-message', 'The Aging warning threshold was updated.'],
    'category_saved' => ['success-message', 'The expected Asset lifespan for this category was saved.'],
    'invalid_threshold' => ['error-message', 'Enter an Aging threshold from 1% to 99%.'],
    'invalid_category' => ['error-message', 'Enter a valid Asset category and expected lifespan.'],
    'save_failed' => ['error-message', 'The lifecycle configuration could not be saved. Please try again.'],
];
$message = $messages[(string) ($_GET['message'] ?? '')] ?? null;
$agingThreshold = (int) ($configuration['aging_threshold_percent'] ?? 80);

$formatLifecycleDuration = static function (mixed $value): string {
    if ($value === null || $value === '' || !is_numeric($value)) {
        return 'Not configured';
    }

    $totalMonths = max(0, (int) $value);
    $years = intdiv($totalMonths, 12);
    $months = $totalMonths % 12;
    $parts = [];

    if ($years > 0) {
        $parts[] = $years . ' ' . ($years === 1 ? 'year' : 'years');
    }
    if ($months > 0 || $parts === []) {
        $parts[] = $months . ' ' . ($months === 1 ? 'month' : 'months');
    }

    return implode(', ', $parts);
};

$formatLifecycleDate = static function (mixed $value): string {
    $rawValue = trim((string) $value);
    if ($rawValue === '') {
        return 'Not recorded';
    }

    try {
        return (new DateTimeImmutable($rawValue))
            ->setTimezone(new DateTimeZone('Asia/Manila'))
            ->format('M j, Y · g:i A');
    } catch (Throwable $error) {
        return $rawValue;
    }
};

$lifecycleEventLabels = [
    'CATEGORY_USEFUL_LIFE' => 'Expected Asset Lifespan Updated',
    'AGING_THRESHOLD' => 'Aging Warning Threshold Updated',
];

include __DIR__ . '/../../includes/header.php';
?>

<div class="layout">
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<main class="main-content lifecycle-settings-page">
    <header class="lifecycle-settings-heading">
        <div>
            <p class="section-heading">System Administrator Configuration</p>
            <h1>Asset Lifecycle Settings</h1>
            <p>
                Configure when Assets enter the Aging stage and set their expected
                service lifespan by category. These settings provide decision
                support and never authorize disposal automatically.
            </p>
        </div>
        <div class="lifecycle-settings-icon" aria-hidden="true">
            <i class="fas fa-hourglass-half"></i>
        </div>
    </header>

    <?php if (!$serviceAvailable): ?>
    <div class="error-message" role="alert">
        Property Core service is temporarily unavailable. Lifecycle settings cannot be loaded.
    </div>
    <?php elseif ($message !== null): ?>
    <div class="<?= htmlspecialchars($message[0]) ?>" role="status">
        <?= htmlspecialchars($message[1]) ?>
    </div>
    <?php endif; ?>

    <section class="lifecycle-settings-card" aria-labelledby="threshold-title">
        <div class="lifecycle-settings-card-header">
            <div>
                <p class="section-heading">Lifecycle Warning</p>
                <h2 id="threshold-title">Aging Threshold</h2>
            </div>
            <span class="pcms-status-badge" data-status="active">
                <?= $agingThreshold ?>%
            </span>
        </div>

        <form method="POST" action="update_threshold.php" class="lifecycle-settings-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-row">
                <label for="agingThreshold">Aging warning percentage</label>
                <div class="lifecycle-percent-field">
                    <input
                        id="agingThreshold"
                        type="number"
                        name="aging_threshold_percent"
                        min="1"
                        max="99"
                        value="<?= $agingThreshold ?>"
                        required
                        <?= !$serviceAvailable ? 'disabled' : '' ?>
                    >
                    <span aria-hidden="true">%</span>
                </div>
                <small>
                    Assets enter Aging at this percentage of their configured expected lifespan.
                    Retirement Review begins at 100%.
                </small>
            </div>
            <button type="submit" class="btn btn-primary" <?= !$serviceAvailable ? 'disabled' : '' ?>>
                Save Threshold
            </button>
        </form>

        <div class="lifecycle-threshold-guide" aria-label="Lifecycle stage guide">
            <div data-stage="active">
                <span>Below <?= $agingThreshold ?>%</span>
                <strong>Active</strong>
                <small>Within the normal lifespan range</small>
            </div>
            <div data-stage="aging">
                <span><?= $agingThreshold ?>%–99%</span>
                <strong>Aging</strong>
                <small>Approaching the expected lifespan</small>
            </div>
            <div data-stage="review">
                <span>100% or more</span>
                <strong>Retirement Review</strong>
                <small>Requires inspection and human review</small>
            </div>
        </div>
    </section>

    <section class="lifecycle-settings-card" aria-labelledby="category-life-title">
        <div class="lifecycle-settings-card-header">
            <div>
                <p class="section-heading">Institutional Values</p>
                <h2 id="category-life-title">Expected Asset Lifespan by Category</h2>
            </div>
        </div>

        <div class="pcms-form-notice pcms-form-notice--warning" role="note">
            Set how long Assets in each category are normally expected to remain in service.
            This is advisory and never approves sale, bidding, or disposal.
        </div>

        <form method="POST" action="save_category.php" class="pcms-form-grid lifecycle-category-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(getAccessCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-row">
                <label for="lifecycleCategory">Asset category</label>
                <input
                    id="lifecycleCategory"
                    type="text"
                    name="category"
                    list="assetCategoryOptions"
                    maxlength="100"
                    required
                    <?= !$serviceAvailable ? 'disabled' : '' ?>
                >
                <small>Example: Computer, Furniture, or Office Equipment.</small>
                <datalist id="assetCategoryOptions">
                    <?php foreach ($categoryNames as $categoryName): ?>
                    <option value="<?= htmlspecialchars($categoryName) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-row">
                <label for="usefulLifeYears">Expected years</label>
                <input id="usefulLifeYears" type="number" name="years" min="0" max="100" value="0" required <?= !$serviceAvailable ? 'disabled' : '' ?>>
            </div>
            <div class="form-row">
                <label for="usefulLifeMonths">Additional months</label>
                <input id="usefulLifeMonths" type="number" name="months" min="0" max="11" value="0" required <?= !$serviceAvailable ? 'disabled' : '' ?>>
            </div>
            <div class="form-row pcms-form-span-2">
                <label for="lifecycleRemarks">Policy reference or notes</label>
                <textarea id="lifecycleRemarks" name="remarks" maxlength="1000" rows="3" placeholder="Optional: identify the policy, approval, or reason for this lifespan." <?= !$serviceAvailable ? 'disabled' : '' ?>></textarea>
            </div>
            <div class="pcms-form-span-2">
                <button type="submit" class="btn btn-primary" <?= !$serviceAvailable ? 'disabled' : '' ?>>
                    Save Expected Lifespan
                </button>
            </div>
        </form>
    </section>

    <section class="lifecycle-settings-card" aria-labelledby="configured-title">
        <div class="lifecycle-settings-card-header">
            <div>
                <p class="section-heading">Current Configuration</p>
                <h2 id="configured-title">Category Lifespan Settings</h2>
            </div>
        </div>
        <div class="pcms-table-scroll pcms-card-table-shell" role="region" aria-label="Configured expected Asset lifespans" tabindex="0">
            <table class="asset-table pcms-mobile-card-table" id="lifecycleTable">
                <thead><tr><th>Category</th><th>Expected Lifespan</th><th>Policy / Notes</th><th>Updated By</th><th>Last Updated</th></tr></thead>
                <tbody>
                <?php foreach ($configuredCategories as $row): ?>
                    <?php
                    $totalMonths = (int) ($row['useful_life_months'] ?? 0);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($row['category'] ?? '')) ?></td>
                        <td><strong><?= htmlspecialchars($formatLifecycleDuration($totalMonths)) ?></strong><br><small><?= $totalMonths ?> months total</small></td>
                        <td><?= htmlspecialchars((string) ($row['remarks'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['updated_by'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars($formatLifecycleDate($row['updated_at'] ?? null)) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($configuredCategories === []): ?>
                    <tr><td colspan="5" class="pcms-empty-cell">No expected Asset lifespans have been configured yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="lifecycle-settings-card" aria-labelledby="history-title">
        <div class="lifecycle-settings-card-header">
            <div>
                <p class="section-heading">Accountability</p>
                <h2 id="history-title">Configuration History</h2>
            </div>
        </div>
        <div class="lifecycle-history-list">
        <?php foreach (($configuration['history'] ?? []) as $event): ?>
            <?php
            $settingType = (string) ($event['setting_type'] ?? '');
            $eventLabel = $lifecycleEventLabels[$settingType] ?? 'Lifecycle Setting Updated';
            $isCategoryLifespan = $settingType === 'CATEGORY_USEFUL_LIFE';
            $oldValue = $isCategoryLifespan
                ? $formatLifecycleDuration($event['old_value'] ?? null)
                : (($event['old_value'] ?? '') !== '' ? (string) $event['old_value'] . '%' : 'Not configured');
            $newValue = $isCategoryLifespan
                ? $formatLifecycleDuration($event['new_value'] ?? null)
                : (string) ($event['new_value'] ?? '') . '%';
            ?>
            <article>
                <div class="lifecycle-history-title">
                    <strong><?= htmlspecialchars($eventLabel) ?></strong>
                    <span><?= htmlspecialchars((string) ($event['category'] ?? 'All Asset categories')) ?></span>
                </div>
                <div class="lifecycle-history-change" aria-label="Changed from <?= htmlspecialchars($oldValue) ?> to <?= htmlspecialchars($newValue) ?>">
                    <span><?= htmlspecialchars($oldValue) ?></span>
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                    <strong><?= htmlspecialchars($newValue) ?></strong>
                </div>
                <p class="lifecycle-history-meta">
                    <span>Changed by <?= htmlspecialchars((string) ($event['changed_by'] ?? 'System')) ?></span>
                    <time><?= htmlspecialchars($formatLifecycleDate($event['created_at'] ?? null)) ?></time>
                </p>
            </article>
        <?php endforeach; ?>
        <?php if (($configuration['history'] ?? []) === []): ?>
            <p class="lifecycle-empty-copy">No configuration changes have been recorded.</p>
        <?php endif; ?>
        </div>
    </section>
</main>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
