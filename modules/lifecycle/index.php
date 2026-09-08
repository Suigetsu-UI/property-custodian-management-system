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
    'category_saved' => ['success-message', 'The category useful-life configuration was saved.'],
    'invalid_threshold' => ['error-message', 'Enter an Aging threshold from 1% to 99%.'],
    'invalid_category' => ['error-message', 'Enter a valid category and useful-life duration.'],
    'save_failed' => ['error-message', 'The lifecycle configuration could not be saved. Please try again.'],
];
$message = $messages[(string) ($_GET['message'] ?? '')] ?? null;

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
                Configure when Assets enter the Aging stage and record approved
                useful-life periods by category. These settings provide decision
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
                <?= (int) ($configuration['aging_threshold_percent'] ?? 80) ?>%
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
                        value="<?= (int) ($configuration['aging_threshold_percent'] ?? 80) ?>"
                        required
                        <?= !$serviceAvailable ? 'disabled' : '' ?>
                    >
                    <span aria-hidden="true">%</span>
                </div>
                <small>
                    Assets enter Aging at this percentage of their configured useful life.
                    Retirement Review begins at 100%.
                </small>
            </div>
            <button type="submit" class="btn btn-primary" <?= !$serviceAvailable ? 'disabled' : '' ?>>
                Save Threshold
            </button>
        </form>
    </section>

    <section class="lifecycle-settings-card" aria-labelledby="category-life-title">
        <div class="lifecycle-settings-card-header">
            <div>
                <p class="section-heading">Institutional Values</p>
                <h2 id="category-life-title">Category Useful Life</h2>
            </div>
        </div>

        <div class="pcms-form-notice pcms-form-notice--warning" role="note">
            Categories without an approved value remain <strong>Useful Life Not Configured</strong>.
            Saving a value here does not approve sale, bidding, or disposal.
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
                <datalist id="assetCategoryOptions">
                    <?php foreach ($categoryNames as $categoryName): ?>
                    <option value="<?= htmlspecialchars($categoryName) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-row">
                <label for="usefulLifeYears">Years</label>
                <input id="usefulLifeYears" type="number" name="years" min="0" max="100" value="0" required <?= !$serviceAvailable ? 'disabled' : '' ?>>
            </div>
            <div class="form-row">
                <label for="usefulLifeMonths">Additional months</label>
                <input id="usefulLifeMonths" type="number" name="months" min="0" max="11" value="0" required <?= !$serviceAvailable ? 'disabled' : '' ?>>
            </div>
            <div class="form-row pcms-form-span-2">
                <label for="lifecycleRemarks">Approval reference or remarks</label>
                <textarea id="lifecycleRemarks" name="remarks" maxlength="1000" rows="3" <?= !$serviceAvailable ? 'disabled' : '' ?>></textarea>
            </div>
            <div class="pcms-form-span-2">
                <button type="submit" class="btn btn-primary" <?= !$serviceAvailable ? 'disabled' : '' ?>>
                    Save Category Useful Life
                </button>
            </div>
        </form>
    </section>

    <section class="lifecycle-settings-card" aria-labelledby="configured-title">
        <div class="lifecycle-settings-card-header">
            <div>
                <p class="section-heading">Current Configuration</p>
                <h2 id="configured-title">Configured Categories</h2>
            </div>
        </div>
        <div class="pcms-table-scroll" role="region" aria-label="Configured useful-life categories" tabindex="0">
            <table class="asset-table">
                <thead><tr><th>Category</th><th>Useful Life</th><th>Remarks</th><th>Updated By</th><th>Last Updated</th></tr></thead>
                <tbody>
                <?php foreach ($configuredCategories as $row): ?>
                    <?php
                    $totalMonths = (int) ($row['useful_life_months'] ?? 0);
                    $years = intdiv($totalMonths, 12);
                    $months = $totalMonths % 12;
                    $duration = trim(($years > 0 ? $years . 'y ' : '') . ($months > 0 ? $months . 'm' : ''));
                    ?>
                    <tr>
                        <td><?= htmlspecialchars((string) ($row['category'] ?? '')) ?></td>
                        <td><?= htmlspecialchars($duration !== '' ? $duration : $totalMonths . 'm') ?> <small>(<?= $totalMonths ?> months)</small></td>
                        <td><?= htmlspecialchars((string) ($row['remarks'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['updated_by'] ?? '—')) ?></td>
                        <td><?= htmlspecialchars((string) ($row['updated_at'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($configuredCategories === []): ?>
                    <tr><td colspan="5" class="pcms-empty-cell">No category useful-life values have been configured.</td></tr>
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
            <article>
                <strong><?= htmlspecialchars((string) ($event['setting_type'] ?? 'Configuration Changed')) ?></strong>
                <span><?= htmlspecialchars((string) ($event['category'] ?? 'System setting')) ?></span>
                <p>
                    <?= htmlspecialchars((string) ($event['old_value'] ?? 'Not configured')) ?>
                    → <?= htmlspecialchars((string) ($event['new_value'] ?? '')) ?>
                    · <?= htmlspecialchars((string) ($event['changed_by'] ?? '')) ?>
                    · <?= htmlspecialchars((string) ($event['created_at'] ?? '')) ?>
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
