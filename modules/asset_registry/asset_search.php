<form method="GET" action="index.php" class="search-toolbar" id="assetFilterForm">
    <input
        type="search"
        id="searchInput"
        name="search"
        value="<?= htmlspecialchars($assetFilters['search']) ?>"
        placeholder="Search Assets or Custodians..."
        aria-label="Search Assets"
    >

    <select id="categoryFilter" name="category" aria-label="Filter by category">
        <option value="">All Categories</option>
        <?php foreach (($assetFilterOptions['categories'] ?? []) as $category): ?>
        <option value="<?= htmlspecialchars($category) ?>" <?= $assetFilters['category'] === $category ? 'selected' : '' ?>>
            <?= htmlspecialchars($category) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <select id="statusFilter" name="status" aria-label="Filter by status">
        <option value="">All Status</option>
        <?php foreach (($assetFilterOptions['statuses'] ?? []) as $status): ?>
        <option value="<?= htmlspecialchars($status) ?>" <?= $assetFilters['status'] === $status ? 'selected' : '' ?>>
            <?= htmlspecialchars($status) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <select id="locationFilter" name="location" aria-label="Filter by location">
        <option value="">All Locations</option>
        <?php foreach (($assetFilterOptions['locations'] ?? []) as $location): ?>
        <option value="<?= htmlspecialchars($location) ?>" <?= $assetFilters['location'] === $location ? 'selected' : '' ?>>
            <?= htmlspecialchars($location) ?>
        </option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-outline">Search</button>
    <a href="index.php" class="btn btn-outline">Clear Filters</a>
    <button
        type="button"
        id="openAssetModal"
        class="btn btn-primary"
        <?= !$assetServiceAvailable ? 'disabled' : '' ?>
    >
        Register Asset
    </button>
</form>
