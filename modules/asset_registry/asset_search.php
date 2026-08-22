<div class="search-toolbar">

<input
    type="text"
    id="searchInput"
    placeholder="Search assets or custodians..."
    aria-label="Search assets"
>

<select id="categoryFilter" aria-label="Filter by category">

<option value="">All Categories</option>

<option>Computer</option>
<option>Furniture</option>
<option>Laboratory Equipment</option>
<option>Office Equipment</option>
<option>Electronics</option>

</select>

<select id="statusFilter" aria-label="Filter by status">

<option value="">All Status</option>

<option>Available</option>
<option>Assigned</option>
<option>Under Maintenance</option>
<option>Lost</option>

</select>

<select id="locationFilter" aria-label="Filter by location">

<option value="">All Locations</option>

</select>

<button
    type="button"
    id="openAssetModal"
>
    Register Asset
</button>

</div>

<div class="asset-filter-summary">

<span id="assetResultCount" aria-live="polite">
    Showing 0 Assets
</span>

<button
    type="button"
    id="clearAssetFilters"
    class="btn btn-outline"
    disabled
>
    Clear Filters
</button>

</div>
